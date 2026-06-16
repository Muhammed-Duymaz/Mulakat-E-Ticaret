<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: Addresses, Carts & Cart Items
 *
 * ADDRESSES
 *   A user can store multiple shipping/billing addresses.
 *   Orders snapshot the address at purchase time (JSON in orders table),
 *   so modifying an address later won't corrupt historical data.
 *
 * CARTS
 *   One active cart per user (or guest, via session_id).
 *   Database-driven so cart survives browser refresh / device switches.
 *
 * CART_ITEMS
 *   Each row = one line in the cart.
 *   Supports both variant and non-variant products.
 *   `saved_for_later` flag implements the "Save for Later" feature.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── addresses ────────────────────────────────────────────────────
        Schema::create('addresses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');

            $table->string('title', 60)->nullable()
                  ->comment('Label: "Home", "Office"');
            $table->string('recipient_name', 120);
            $table->string('phone', 30);
            $table->string('address_line1', 255);
            $table->string('address_line2', 255)->nullable();
            $table->string('city', 100);
            $table->string('state', 100)->nullable();
            $table->string('postal_code', 20);
            $table->string('country_code', 3)->default('TUR')
                  ->comment('ISO 3166-1 alpha-3');
            $table->boolean('is_default')->default(false);

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('user_id')
                  ->references('id')->on('users')
                  ->onDelete('cascade');

            $table->index(['user_id', 'is_default'], 'addr_user_default_idx');
        });

        // ── carts ─────────────────────────────────────────────────────────
        Schema::create('carts', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('user_id')->nullable()
                  ->comment('NULL for guest carts (use session_id)');
            $table->string('session_id', 100)->nullable()
                  ->comment('Guest cart identifier');

            // Coupon / discount tracking at cart level
            $table->string('coupon_code', 60)->nullable();
            $table->decimal('coupon_discount', 12, 2)->default(0.00);

            $table->timestamps();

            $table->foreign('user_id')
                  ->references('id')->on('users')
                  ->onDelete('cascade');

            $table->index('user_id',    'cart_user_idx');
            $table->index('session_id', 'cart_session_idx');
        });

        // ── cart_items ────────────────────────────────────────────────────
        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('cart_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('variant_id')->nullable()
                  ->comment('NULL = base product added (no variant selected)');

            $table->unsignedSmallInteger('quantity')->default(1);
            $table->decimal('unit_price', 12, 2)
                  ->comment('Price captured at add-to-cart; refreshed on cart view');

            $table->boolean('saved_for_later')->default(false)
                  ->comment('"Save for Later" wishlist-like feature');

            $table->timestamps();

            $table->foreign('cart_id')
                  ->references('id')->on('carts')
                  ->onDelete('cascade');

            $table->foreign('product_id')
                  ->references('id')->on('products')
                  ->onDelete('cascade');

            $table->foreign('variant_id')
                  ->references('id')->on('product_variants')
                  ->onDelete('cascade');

            // Prevent duplicate rows for same product+variant in one cart
            $table->unique(
                ['cart_id', 'product_id', 'variant_id'],
                'ci_cart_product_variant_uniq'
            );

            $table->index(['cart_id', 'saved_for_later'], 'ci_cart_saved_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cart_items');
        Schema::dropIfExists('carts');
        Schema::dropIfExists('addresses');
    }
};

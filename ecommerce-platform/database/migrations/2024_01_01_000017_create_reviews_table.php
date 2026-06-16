<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: Reviews
 *
 * Customers can rate and review products they have purchased.
 * Business rules enforced at the application layer:
 *   - Only verified purchasers can review (order_item_id FK).
 *   - One review per user per product (unique index).
 *   - Soft-deleted reviews are hidden but preserved for analytics.
 *
 * After a review is saved, the Product model's average_rating and
 * review_count are recalculated via a service or observer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('order_item_id')->nullable()
                  ->comment('Links to the purchased item — verifies purchase');

            $table->unsignedTinyInteger('rating')
                  ->comment('1–5 star rating');
            $table->string('title', 160)->nullable();
            $table->text('body')->nullable();

            // ── Media Attachments ─────────────────────────────────────────
            $table->json('images')->nullable()
                  ->comment('Array of uploaded review image paths');

            // ── Moderation ────────────────────────────────────────────────
            $table->enum('status', ['pending', 'approved', 'rejected'])
                  ->default('pending')->index()
                  ->comment('Moderation workflow; only approved reviews are public');
            $table->text('moderation_note')->nullable();

            // ── Engagement ────────────────────────────────────────────────
            $table->unsignedInteger('helpful_count')->default(0)
                  ->comment('"Was this review helpful?" upvotes');

            $table->boolean('is_verified_purchase')->default(false)
                  ->comment('True when order_item_id is present & order is delivered');

            $table->timestamps();
            $table->softDeletes();

            // ── FKs ───────────────────────────────────────────────────────
            $table->foreign('product_id')
                  ->references('id')->on('products')
                  ->onDelete('cascade');

            $table->foreign('user_id')
                  ->references('id')->on('users')
                  ->onDelete('cascade');

            $table->foreign('order_item_id')
                  ->references('id')->on('order_items')
                  ->onDelete('set null');

            // ── Indexes ───────────────────────────────────────────────────
            // One review per customer per product
            $table->unique(['product_id', 'user_id'], 'rev_product_user_uniq');

            $table->index(['product_id', 'status', 'rating'],
                          'rev_product_status_rating_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};

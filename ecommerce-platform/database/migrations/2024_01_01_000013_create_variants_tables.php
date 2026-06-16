<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: Variant Options, Values & Product Variants
 *
 * Architecture (Trendyol-style):
 *
 *   variant_options       → "Size", "Color", "Material"   (per product)
 *   variant_option_values → "Red", "XL", "Cotton"         (per option)
 *   product_variants      → a specific COMBINATION of values (e.g. Red + XL)
 *                           → holds its own sku, price, stock, images
 *   product_variant_option_value → pivot: variant ↔ values it combines
 *
 * Example (product = "Nike Air Max"):
 *   variant_options:       Color, Size
 *   variant_option_values: Red, Blue, XS, S, M, L
 *   product_variants:
 *     - sku=NAM-RED-S  price=1299 stock=10  [Color=Red, Size=S]
 *     - sku=NAM-RED-M  price=1299 stock=5   [Color=Red, Size=M]
 *     - sku=NAM-BLU-L  price=1399 stock=8   [Color=Blue, Size=L]
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── variant_options ──────────────────────────────────────────────
        Schema::create('variant_options', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->string('name', 80)
                  ->comment('e.g. "Color", "Size", "Material"');
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('product_id')
                  ->references('id')->on('products')
                  ->onDelete('cascade');

            $table->index(['product_id', 'sort_order'], 'vo_product_sort_idx');
        });

        // ── variant_option_values ────────────────────────────────────────
        Schema::create('variant_option_values', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('variant_option_id');
            $table->string('value', 120)
                  ->comment('e.g. "Red", "XL", "Cotton"');
            $table->string('color_hex', 7)->nullable()
                  ->comment('For color swatches: #FF0000');
            $table->string('image')->nullable()
                  ->comment('Optional swatch image override');
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('variant_option_id')
                  ->references('id')->on('variant_options')
                  ->onDelete('cascade');

            $table->index(['variant_option_id', 'sort_order'], 'vov_option_sort_idx');
        });

        // ── product_variants ─────────────────────────────────────────────
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');

            $table->string('sku', 120)->unique()
                  ->comment('Unique SKU per variant combination');

            // Variant-level pricing (NULL = inherit from parent product)
            $table->decimal('price', 12, 2)->nullable()
                  ->comment('Override price; NULL = use product.price');
            $table->decimal('discount_price', 12, 2)->nullable();

            // Variant-level stock
            $table->unsignedInteger('stock')->default(0);
            $table->unsignedSmallInteger('low_stock_threshold')->default(5);

            // Variant-level weight override (for shipping)
            $table->decimal('weight', 8, 2)->nullable();

            // Display
            $table->string('image')->nullable()
                  ->comment('Optional hero image for this variant');
            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('product_id')
                  ->references('id')->on('products')
                  ->onDelete('cascade');

            $table->index(['product_id', 'is_active'], 'pv_product_active_idx');
            $table->index(['product_id', 'stock'],      'pv_product_stock_idx');
        });

        // ── product_variant_option_value (pivot) ──────────────────────────
        Schema::create('product_variant_option_value', function (Blueprint $table) {
            $table->unsignedBigInteger('product_variant_id');
            $table->unsignedBigInteger('variant_option_value_id');

            $table->primary(
                ['product_variant_id', 'variant_option_value_id'],
                'pvov_primary'
            );

            $table->foreign('product_variant_id')
                  ->references('id')->on('product_variants')
                  ->onDelete('cascade');

            $table->foreign('variant_option_value_id')
                  ->references('id')->on('variant_option_values')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variant_option_value');
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('variant_option_values');
        Schema::dropIfExists('variant_options');
    }
};

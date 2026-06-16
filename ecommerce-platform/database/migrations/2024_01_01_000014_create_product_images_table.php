<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: Product Images
 *
 * Each product/variant can have multiple images.
 * - is_featured marks the gallery "hero" image shown in listings.
 * - variant_id links an image to a specific variant (NULL = product-level).
 * - sort_order controls the display sequence in the gallery.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_images', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('variant_id')->nullable()
                  ->comment('NULL = product-level image; set = variant-specific');

            $table->string('path', 500)
                  ->comment('Relative path in storage (e.g. products/abc.webp)');
            $table->string('alt_text', 255)->nullable()
                  ->comment('Accessibility & SEO alt attribute');
            $table->boolean('is_featured')->default(false)
                  ->comment('Primary listing/thumbnail image');
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->timestamps();

            $table->foreign('product_id')
                  ->references('id')->on('products')
                  ->onDelete('cascade');

            $table->foreign('variant_id')
                  ->references('id')->on('product_variants')
                  ->onDelete('set null');

            $table->index(['product_id', 'is_featured', 'sort_order'],
                          'pi_product_featured_sort_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_images');
    }
};

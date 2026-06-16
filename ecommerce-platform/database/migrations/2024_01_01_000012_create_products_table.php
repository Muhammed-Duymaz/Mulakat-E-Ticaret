<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: Products Table
 *
 * Core product record. Stock here reflects the base product-level stock
 * (used when the product has NO variants). When variants exist, each
 * ProductVariant row carries its own stock — the product-level stock
 * becomes a computed/denormalized sum managed by the application layer.
 *
 * Columns:
 *  - vendor_id          → nullable; who sells this product
 *  - category_id        → primary category (product can belong to many via pivot)
 *  - brand_id           → FK to brands
 *  - sku                → Stock Keeping Unit, unique per store
 *  - price              → base/regular price (DECIMAL for monetary accuracy)
 *  - discount_price     → sale price (NULL = no active sale)
 *  - stock              → base stock (ignored when variants exist)
 *  - has_variants       → flag to switch between variant/non-variant logic
 *  - weight             → grams, for shipping calculation
 *  - views              → incremented on product page load (for best-sellers)
 *  - status             → draft / active / archived
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();

            // ── Ownership ─────────────────────────────────────────────────
            $table->unsignedBigInteger('vendor_id')->nullable()
                  ->comment('NULL = platform-owned product');
            $table->unsignedBigInteger('category_id');
            $table->unsignedBigInteger('brand_id')->nullable();

            // ── Identity ──────────────────────────────────────────────────
            $table->string('name', 255);
            $table->string('slug', 300)->unique();
            $table->string('sku', 100)->unique();
            $table->text('short_description')->nullable();
            $table->longText('description')->nullable();

            // ── Pricing ───────────────────────────────────────────────────
            $table->decimal('price', 12, 2)
                  ->comment('Regular retail price');
            $table->decimal('discount_price', 12, 2)->nullable()
                  ->comment('Active sale price; NULL = no discount');
            $table->decimal('cost_price', 12, 2)->nullable()
                  ->comment('Internal cost — hidden from customers');

            // ── Stock & Shipping ──────────────────────────────────────────
            $table->unsignedInteger('stock')->default(0)
                  ->comment('Base stock — used only when has_variants = false');
            $table->boolean('has_variants')->default(false)
                  ->comment('True when ProductVariants carry the real stock');
            $table->unsignedSmallInteger('low_stock_threshold')->default(5)
                  ->comment('Notify vendor/admin when stock <= this value');
            $table->decimal('weight', 8, 2)->nullable()
                  ->comment('Weight in grams; used for shipping cost calculation');
            $table->decimal('length', 8, 2)->nullable(); // cm
            $table->decimal('width',  8, 2)->nullable();
            $table->decimal('height', 8, 2)->nullable();

            // ── SEO & Marketing ───────────────────────────────────────────
            $table->string('meta_title', 200)->nullable();
            $table->string('meta_description', 320)->nullable();
            $table->json('tags')->nullable()
                  ->comment('Searchable tags stored as JSON array');

            // ── Status & Metrics ──────────────────────────────────────────
            $table->enum('status', ['draft', 'active', 'archived'])
                  ->default('draft')
                  ->index();
            $table->unsignedBigInteger('views')->default(0)
                  ->comment('Incremented on product detail view; used for ranking');
            $table->decimal('average_rating', 3, 2)->default(0.00)
                  ->comment('Denormalized avg; recalculated on new review');
            $table->unsignedInteger('review_count')->default(0);

            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // ── Foreign Keys ──────────────────────────────────────────────
            $table->foreign('vendor_id')
                  ->references('id')->on('users')
                  ->onDelete('set null');

            $table->foreign('category_id')
                  ->references('id')->on('categories')
                  ->onDelete('restrict');

            $table->foreign('brand_id')
                  ->references('id')->on('brands')
                  ->onDelete('set null');

            // ── Composite Indexes for Filtering & Sorting ─────────────────
            $table->index(['status', 'category_id'],     'prod_status_cat_idx');
            $table->index(['status', 'brand_id'],        'prod_status_brand_idx');
            $table->index(['status', 'price'],           'prod_status_price_idx');
            $table->index(['status', 'views'],           'prod_status_views_idx');
            $table->index(['status', 'average_rating'],  'prod_status_rating_idx');
            $table->index(['vendor_id', 'status'],       'prod_vendor_status_idx');
            $table->index(['status', 'published_at'],    'prod_status_published_idx');
        });

        // ── Product ↔ Extra-Categories (many-to-many) ────────────────────
        Schema::create('category_product', function (Blueprint $table) {
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('category_id');

            $table->primary(['product_id', 'category_id']);

            $table->foreign('product_id')
                  ->references('id')->on('products')
                  ->onDelete('cascade');

            $table->foreign('category_id')
                  ->references('id')->on('categories')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_product');
        Schema::dropIfExists('products');
    }
};

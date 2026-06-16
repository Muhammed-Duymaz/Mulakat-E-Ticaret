<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: Brands Table
 *
 * Simple lookup table for product brands.
 * Vendor-linked brands can be associated through the products table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brands', function (Blueprint $table) {
            $table->id();

            $table->string('name', 120)->unique();
            $table->string('slug', 160)->unique();
            $table->string('logo')->nullable()
                  ->comment('Relative path to brand logo image');
            $table->string('website_url')->nullable();
            $table->text('description')->nullable();

            // ── SEO ──────────────────────────────────────────────────────
            $table->string('meta_title', 200)->nullable();
            $table->string('meta_description', 320)->nullable();

            $table->boolean('is_active')->default(true)->index();
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('brands');
    }
};

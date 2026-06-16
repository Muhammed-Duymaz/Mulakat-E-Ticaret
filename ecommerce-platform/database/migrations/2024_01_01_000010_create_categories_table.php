<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: Categories Table
 *
 * Supports infinite subcategory nesting via parent_id self-reference.
 * `depth` caches the nesting level (0 = root) to avoid recursive queries.
 * `path`  stores the materialized ancestor chain (e.g. "1/4/12") for
 *         fast subtree lookups: WHERE path LIKE '1/4/%'.
 * `sort_order` controls display ordering within the same parent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();

            // ── Hierarchy ────────────────────────────────────────────────
            $table->unsignedBigInteger('parent_id')->nullable()
                  ->comment('NULL = root category');
            $table->unsignedTinyInteger('depth')->default(0)
                  ->comment('Nesting level; 0 = top-level');
            $table->string('path', 500)->nullable()
                  ->comment('Materialized ancestor IDs, e.g. 1/4/12');

            // ── Identity ─────────────────────────────────────────────────
            $table->string('name', 160);
            $table->string('slug', 200)->unique();
            $table->text('description')->nullable();
            $table->string('image')->nullable();
            $table->string('icon')->nullable()
                  ->comment('CSS icon class or SVG name');

            // ── SEO ──────────────────────────────────────────────────────
            $table->string('meta_title', 200)->nullable();
            $table->string('meta_description', 320)->nullable();

            // ── Status & Order ───────────────────────────────────────────
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->timestamps();
            $table->softDeletes();

            // ── Constraints & Indexes ────────────────────────────────────
            $table->foreign('parent_id')
                  ->references('id')->on('categories')
                  ->onDelete('set null'); // Orphan children become root

            $table->index(['parent_id', 'is_active', 'sort_order'],
                          'cat_parent_active_sort_idx');
            // path index for subtree queries (prefix scan)
            $table->index('path', 'cat_path_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};

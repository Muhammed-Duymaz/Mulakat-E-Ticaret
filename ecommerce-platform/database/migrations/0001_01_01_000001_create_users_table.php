<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: Users Table
 *
 * Extends the default Laravel users table with:
 *  - role_id   → FK to roles (Admin / Vendor / Customer)
 *  - vendor-specific columns (store_name, store_slug, commission_rate)
 *  - Soft deletes for safe user removal
 *  - email_verified_at and remember_token as standard
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();

            // ── Identity ────────────────────────────────────────────────
            $table->unsignedBigInteger('role_id')->default(3) // 3 = customer
                  ->comment('FK → roles. 1=admin, 2=vendor, 3=customer');
            $table->string('name', 120);
            $table->string('email', 180)->unique();
            $table->string('phone', 30)->nullable()->unique();
            $table->string('avatar')->nullable();

            // ── Auth ────────────────────────────────────────────────────
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();

            // ── Vendor-specific (null for admin/customer) ────────────────
            $table->string('store_name', 160)->nullable();
            $table->string('store_slug', 160)->nullable()->unique();
            $table->text('store_description')->nullable();
            $table->string('store_logo')->nullable();
            $table->decimal('commission_rate', 5, 2)->default(0.00)
                  ->comment('Platform commission % for this vendor');
            $table->enum('vendor_status', ['pending', 'approved', 'suspended'])
                  ->nullable()
                  ->comment('Only relevant when role = vendor');

            // ── Status & Meta ────────────────────────────────────────────
            $table->boolean('is_active')->default(true)->index();

            $table->timestamps();
            $table->softDeletes();     // Safe deletion; preserves order history

            // ── Indexes ──────────────────────────────────────────────────
            $table->foreign('role_id')
                  ->references('id')->on('roles')
                  ->onDelete('restrict');  // Can't delete a role while users exist

            $table->index(['role_id', 'is_active']); // Common composite query
        });

        // ── User ↔ Permission overrides (beyond role defaults) ───────────
        Schema::create('user_permission', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('permission_id');
            $table->boolean('granted')->default(true)
                  ->comment('true = extra grant, false = explicit deny');

            $table->primary(['user_id', 'permission_id']);

            $table->foreign('user_id')
                  ->references('id')->on('users')
                  ->onDelete('cascade');

            $table->foreign('permission_id')
                  ->references('id')->on('permissions')
                  ->onDelete('cascade');
        });

        // ── Personal access tokens (Sanctum) ────────────────────────────
        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personal_access_tokens');
        Schema::dropIfExists('user_permission');
        Schema::dropIfExists('users');
    }
};

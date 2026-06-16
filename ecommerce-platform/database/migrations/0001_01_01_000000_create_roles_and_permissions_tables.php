<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: Roles & Permissions (Custom Lightweight RBAC)
 *
 * Supports Admin, Vendor, and Customer roles.
 * A role can have many permissions; a user belongs to one role,
 * but can also have individual permission overrides via the pivot table.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ----------------------------------------------------------------
        // roles
        // ----------------------------------------------------------------
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60)->unique();          // e.g. admin, vendor, customer
            $table->string('display_name', 120)->nullable(); // Human-readable
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // ----------------------------------------------------------------
        // permissions
        // ----------------------------------------------------------------
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120)->unique();          // e.g. products.create
            $table->string('display_name', 180)->nullable();
            $table->string('group', 60)->nullable()
                  ->index()                                 // Group permissions for UI (products, orders…)
                  ->comment('Logical group: products, orders, users, etc.');
            $table->timestamps();
        });

        // ----------------------------------------------------------------
        // role_permission (pivot)
        // ----------------------------------------------------------------
        Schema::create('role_permission', function (Blueprint $table) {
            $table->unsignedBigInteger('role_id');
            $table->unsignedBigInteger('permission_id');

            $table->primary(['role_id', 'permission_id']);

            $table->foreign('role_id')
                  ->references('id')->on('roles')
                  ->onDelete('cascade');

            $table->foreign('permission_id')
                  ->references('id')->on('permissions')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_permission');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
    }
};

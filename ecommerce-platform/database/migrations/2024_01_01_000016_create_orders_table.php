<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: Orders & Order Items
 *
 * ORDERS
 *   - order_number: human-readable unique code (e.g. ORD-2024-000123)
 *   - status pipeline: pending → paid → processing → shipped → delivered | cancelled | refunded
 *   - shipping_address: JSON snapshot — immune to address book changes
 *   - coupon_discount / shipping_fee / grand_total: pre-calculated at checkout
 *   - payment_method / payment_reference: stripe charge ID, iyzipay token, etc.
 *   - tracking_code: carrier tracking number for shipment
 *   - notes: customer delivery instructions
 *
 * ORDER_ITEMS
 *   - Snapshot the product name, SKU, variant label, and price AT purchase time.
 *     Even if the product is deleted or repriced, orders remain accurate.
 *   - vendor_id: used by the platform to split payouts to vendors
 *   - commission_rate: vendor commission captured at time of order
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── orders ────────────────────────────────────────────────────────
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');

            // ── Order Identity ─────────────────────────────────────────────
            $table->string('order_number', 30)->unique()
                  ->comment('Human-readable: ORD-2024-000123');

            // ── Status Pipeline ────────────────────────────────────────────
            $table->enum('status', [
                'pending',      // Created; awaiting payment
                'paid',         // Payment confirmed
                'processing',   // Vendor preparing shipment
                'shipped',      // Dispatched; tracking code available
                'delivered',    // Marked delivered
                'cancelled',    // Cancelled (pre-shipment)
                'refunded',     // Refund processed
            ])->default('pending')->index();

            // ── Address Snapshot (JSON) ────────────────────────────────────
            $table->json('shipping_address')
                  ->comment('Snapshot of address at checkout — never changes');

            // ── Financials ─────────────────────────────────────────────────
            $table->decimal('subtotal',        12, 2)->default(0.00);
            $table->decimal('coupon_discount', 12, 2)->default(0.00);
            $table->decimal('shipping_fee',    12, 2)->default(0.00);
            $table->decimal('tax_amount',      12, 2)->default(0.00);
            $table->decimal('grand_total',     12, 2)->default(0.00);
            $table->string('currency_code', 3)->default('TRY');

            // ── Payment ────────────────────────────────────────────────────
            $table->string('payment_method', 60)->nullable()
                  ->comment('stripe, iyzipay, cod, bank_transfer');
            $table->string('payment_reference', 120)->nullable()
                  ->comment('Gateway charge / transaction ID');
            $table->timestamp('paid_at')->nullable();

            // ── Shipping ───────────────────────────────────────────────────
            $table->string('shipping_carrier', 80)->nullable();
            $table->string('tracking_code', 120)->nullable()->index();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            // ── Misc ───────────────────────────────────────────────────────
            $table->string('coupon_code', 60)->nullable();
            $table->text('notes')->nullable()
                  ->comment('Customer delivery instructions');
            $table->text('admin_notes')->nullable()
                  ->comment('Internal notes — not visible to customer');

            $table->timestamps();
            $table->softDeletes();

            // ── FKs & Indexes ──────────────────────────────────────────────
            $table->foreign('user_id')
                  ->references('id')->on('users')
                  ->onDelete('restrict'); // Never hard-delete a user with orders

            $table->index(['user_id', 'status'],      'ord_user_status_idx');
            $table->index(['status', 'created_at'],   'ord_status_created_idx');
            $table->index('payment_reference',         'ord_payment_ref_idx');
        });

        // ── order_items ───────────────────────────────────────────────────
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');

            // ── Vendor Split ───────────────────────────────────────────────
            $table->unsignedBigInteger('vendor_id')->nullable()
                  ->comment('Vendor who owns this item — for payout splitting');
            $table->decimal('commission_rate', 5, 2)->default(0.00)
                  ->comment('Snapshot of vendor commission rate at purchase');

            // ── Product Snapshot (survives product deletion) ────────────────
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedBigInteger('variant_id')->nullable();
            $table->string('product_name', 255)
                  ->comment('Snapshot of product name');
            $table->string('product_sku',  100)
                  ->comment('Snapshot of SKU');
            $table->string('variant_label', 255)->nullable()
                  ->comment('e.g. "Color: Red | Size: XL"');
            $table->string('product_image', 500)->nullable()
                  ->comment('Snapshot of image path');

            // ── Pricing Snapshot ───────────────────────────────────────────
            $table->decimal('unit_price',   12, 2)
                  ->comment('Price per unit at time of purchase');
            $table->decimal('discount_price', 12, 2)->nullable()
                  ->comment('Discounted price captured at checkout');
            $table->unsignedSmallInteger('quantity');
            $table->decimal('line_total',   12, 2)
                  ->comment('unit_price (or discount_price) × quantity');

            // ── Per-item Status (for partial fulfilment) ───────────────────
            $table->enum('status', [
                'pending', 'processing', 'shipped', 'delivered', 'cancelled', 'refunded',
            ])->default('pending');

            $table->timestamps();

            $table->foreign('order_id')
                  ->references('id')->on('orders')
                  ->onDelete('cascade');

            $table->foreign('product_id')
                  ->references('id')->on('products')
                  ->onDelete('set null');

            $table->foreign('variant_id')
                  ->references('id')->on('product_variants')
                  ->onDelete('set null');

            $table->foreign('vendor_id')
                  ->references('id')->on('users')
                  ->onDelete('set null');

            $table->index(['order_id'],              'oi_order_idx');
            $table->index(['vendor_id', 'status'],   'oi_vendor_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};

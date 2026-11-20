<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Orders with a totals and billing snapshot (planning report H3.10, B4).
 *
 * Two separate status fields keep payment and delivery problems apart:
 *   payment_status:     pending_payment | paid | failed | cancelled | refunded | partially_refunded
 *   fulfillment_status: not_started | processing | fulfilled | needs_attention
 *
 * Financial records are never cascade-deleted (restrict on every foreign key).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number', 30)->unique();               // format pending (report J21)
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->char('currency', 3);
            $table->decimal('subtotal', 12, 2);
            $table->decimal('discount_total', 12, 2)->default(0);
            $table->decimal('tax_total', 12, 2)->default(0);
            $table->decimal('total', 12, 2);
            $table->decimal('tax_rate', 7, 4)->nullable();               // snapshot; tax rules pending (J6)
            $table->foreignId('promo_code_id')->nullable()->constrained('promo_codes')->restrictOnDelete();
            $table->string('promo_code', 50)->nullable();                // text snapshot of the code used
            $table->string('payment_status', 30)->default('pending_payment');
            $table->string('fulfillment_status', 30)->default('not_started');
            $table->string('billing_name');
            $table->string('billing_email');
            $table->char('billing_country', 2)->nullable();
            $table->string('billing_postal_code', 20)->nullable();
            $table->string('billing_gstin', 15)->nullable();
            $table->dateTime('expires_at')->nullable();                  // unpaid-order expiry (J7)
            $table->dateTime('paid_at')->nullable();
            $table->dateTime('fulfilled_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index('payment_status');
            $table->index('fulfillment_status');
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE `orders` ADD CONSTRAINT `chk_orders_amounts` CHECK (`subtotal` >= 0 AND `discount_total` >= 0 AND `tax_total` >= 0 AND `total` >= 0 AND `discount_total` <= `subtotal`)');
            DB::statement('ALTER TABLE `orders` ADD CONSTRAINT `chk_orders_tax_rate` CHECK (`tax_rate` IS NULL OR `tax_rate` >= 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};

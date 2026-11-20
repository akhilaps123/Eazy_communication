<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * eSIM Go order records (planning report G and H3.14).
 *
 * UNIQUE(order_id) is the duplicate-purchase guard: one provider order per
 * order. Retries update the same row (attempts++), they never add a new one.
 *
 * status: pending | submitted | completed | failed | needs_reconciliation
 *
 * No eSIM Go request is made by this migration; endpoint details remain to be
 * confirmed from the official documentation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provider_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained('orders')->restrictOnDelete();
            $table->string('provider', 30);                              // e.g. esimgo
            $table->string('our_reference', 64)->unique();               // our own reference for this purchase
            $table->string('provider_reference', 191)->nullable();       // provider's order reference, once known
            $table->string('status', 30)->default('pending');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->decimal('total_provider_cost', 12, 4)->nullable();
            $table->char('provider_currency', 3)->nullable();
            $table->json('request_payload')->nullable();                 // sanitised — never the API key
            $table->json('response_payload')->nullable();                // sanitised
            $table->dateTime('submitted_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'provider_reference']);
            $table->index('status');
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE `provider_orders` ADD CONSTRAINT `chk_provider_orders_cost` CHECK (`total_provider_cost` IS NULL OR `total_provider_cost` >= 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_orders');
    }
};

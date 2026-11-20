<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Gateway-independent payment attempts (planning report E and H3.12).
 * One order can have several attempts; at most one should succeed.
 *
 * status: created | pending | succeeded | failed | cancelled | refunded | partially_refunded
 *
 * (gateway, gateway_payment_id) is unique. gateway_payment_id is NULL until
 * the gateway issues an ID; MariaDB allows several NULLs in a unique index.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('gateway', 50);                               // gateway not yet decided (J1)
            $table->string('gateway_payment_id', 191)->nullable();
            $table->string('method', 30)->nullable();                    // card | bank | wallet ...
            $table->decimal('amount', 12, 2);
            $table->char('currency', 3);
            $table->string('status', 30)->default('created');
            $table->text('failure_reason')->nullable();
            $table->decimal('refunded_amount', 12, 2)->default(0);
            $table->json('gateway_response')->nullable();                // sanitised — never card data
            $table->dateTime('paid_at')->nullable();
            $table->timestamps();

            $table->unique(['gateway', 'gateway_payment_id']);
            $table->index(['order_id', 'status']);
            $table->index('status');
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE `payments` ADD CONSTRAINT `chk_payments_amounts` CHECK (`amount` >= 0 AND `refunded_amount` >= 0 AND `refunded_amount` <= `amount`)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};

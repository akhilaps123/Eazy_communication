<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 8 (Razorpay, test mode): two additive, nullable columns on the existing
 * payments table — no data is changed.
 *
 * - gateway_order_id: the gateway's order id (Razorpay "order_..."), created on the
 *   server before the customer pays. Signature checks and webhooks are matched
 *   against this stored value. Unique per gateway: one gateway order belongs to
 *   exactly one payment record.
 * - verified_at: when the server confirmed the payment with the gateway.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('gateway_order_id', 191)->nullable()->after('gateway');
            $table->dateTime('verified_at')->nullable()->after('paid_at');
            $table->unique(['gateway', 'gateway_order_id'], 'payments_gateway_order_unique');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique('payments_gateway_order_unique');
            $table->dropColumn(['gateway_order_id', 'verified_at']);
        });
    }
};

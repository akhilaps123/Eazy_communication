<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 9E — customer notifications per order (additive; no existing table changes).
 *
 * One row per order and notification type ("esim_ready"). The UNIQUE index is
 * the duplicate guard: a second "eSIM ready" email for the same order cannot
 * be queued, however many times fulfilment, reconciliation or a job retry
 * runs. Status: pending (queued) | sending (handed to the mailer, result not
 * confirmed) | sent (accepted by the mail transport) | failed.
 * No email address, message body or activation data is stored here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->restrictOnDelete();
            $table->string('type', 50);
            $table->string('status', 20);
            $table->unsignedInteger('attempts')->default(0);
            $table->dateTime('queued_at')->nullable();
            $table->dateTime('last_attempt_at')->nullable();
            $table->dateTime('sent_at')->nullable();
            $table->string('last_error', 500)->nullable();
            $table->timestamps();

            $table->unique(['order_id', 'type']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_notifications');
    }
};

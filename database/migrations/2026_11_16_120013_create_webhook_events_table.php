<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Raw payment-gateway notifications (planning report E2 and H3.13).
 * Every notification is stored before processing. The unique key
 * (gateway, event_id) is the duplicate-notification guard.
 *
 * processing_status: received | processed | ignored | failed
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('gateway', 50);
            $table->string('event_id', 191);
            $table->string('event_type', 100)->nullable();
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->boolean('signature_valid')->default(false);
            $table->json('payload');
            $table->string('processing_status', 20)->default('received');
            $table->dateTime('processed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->unique(['gateway', 'event_id']);
            $table->index('processing_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_events');
    }
};

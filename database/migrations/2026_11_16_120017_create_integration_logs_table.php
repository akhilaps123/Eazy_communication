<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Payment / eSIM Go / mail activity for troubleshooting (planning report H3.17).
 * Log rows are write-once, so only created_at is kept. Request/response bodies
 * must be sanitised before saving (no API keys, card data or activation codes).
 * Links to orders/payments become NULL rather than blocking or deleting.
 * Retention period: pending decision (report J22).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_logs', function (Blueprint $table) {
            $table->id();
            $table->string('service', 30);                    // esimgo | payment | mail
            $table->string('operation', 60);                  // e.g. catalogue_sync, create_order
            $table->string('http_method', 10)->nullable();
            $table->text('url')->nullable();                  // never includes keys
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->boolean('success')->default(false);
            $table->unsignedInteger('duration_ms')->nullable();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->foreignId('provider_order_id')->nullable()->constrained('provider_orders')->nullOnDelete();
            $table->json('request_body')->nullable();
            $table->json('response_body')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['service', 'created_at']);
            $table->index('success');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_logs');
    }
};

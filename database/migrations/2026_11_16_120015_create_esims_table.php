<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Purchased eSIMs (planning report H3.15).
 *
 * smdp_address and activation_code are stored ENCRYPTED by the Esim model
 * (Laravel "encrypted" cast, using APP_KEY) — hence TEXT columns, because the
 * encrypted value is much longer than the original. user_id is stored
 * directly so ownership can be checked before showing QR/activation details.
 *
 * status: assigned | installed | active | expired | ...
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('esims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('order_item_id')->constrained('order_items')->restrictOnDelete();
            $table->foreignId('provider_order_id')->constrained('provider_orders')->restrictOnDelete();
            $table->string('iccid', 32)->unique();
            $table->text('smdp_address')->nullable();        // encrypted
            $table->text('activation_code')->nullable();     // encrypted (matching ID)
            $table->string('qr_code_path')->nullable();      // private storage path, never public
            $table->string('bundle_code', 191)->nullable();
            $table->string('status', 30)->default('assigned');
            $table->dateTime('activated_at')->nullable();
            $table->dateTime('expires_at')->nullable();
            $table->dateTime('last_usage_synced_at')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('esims');
    }
};

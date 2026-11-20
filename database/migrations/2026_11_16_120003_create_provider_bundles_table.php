<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Local copy of the eSIM Go catalogue — wholesale/provider data only
 * (planning report H3.3). Customer-facing data lives in `plans`.
 *
 * Several columns are nullable because the exact catalogue response fields
 * are not yet confirmed from the official eSIM Go documentation; the full
 * original response is kept in `raw_payload`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provider_bundles', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 30);                          // e.g. esimgo
            $table->string('bundle_code', 191);                      // provider's bundle name/ID
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedInteger('data_amount_mb')->nullable();   // NULL for unlimited
            $table->boolean('is_unlimited')->default(false);
            $table->unsignedSmallInteger('duration_days')->nullable();
            $table->json('country_codes')->nullable();               // country list as received
            $table->string('speed', 50)->nullable();
            $table->decimal('provider_cost', 12, 4)->nullable();     // wholesale cost — never shown to customers
            $table->char('provider_currency', 3)->nullable();        // ISO 4217
            $table->boolean('is_available')->default(true);          // false when it disappears from the catalogue
            $table->json('raw_payload')->nullable();
            $table->dateTime('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'bundle_code']);
            $table->index('is_available');
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE `provider_bundles` ADD CONSTRAINT `chk_provider_bundles_cost` CHECK (`provider_cost` IS NULL OR `provider_cost` >= 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_bundles');
    }
};

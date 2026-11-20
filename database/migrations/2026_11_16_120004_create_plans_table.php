<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Customer-facing plans (planning report H3.4).
 *
 * One provider bundle -> at most one plan (ER diagram: one-to-zero-or-one,
 * enforced by the unique key on provider_bundle_id). The report lists this
 * as pending confirmation (I3.1); if one-to-many is approved later, a small
 * migration can drop the unique index.
 *
 * Plans start inactive so nothing is sold before it has an approved price.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_bundle_id')->unique()->constrained('provider_bundles')->restrictOnDelete();
            $table->string('slug', 150)->unique();
            $table->string('name');
            $table->string('display_title')->nullable();               // e.g. "Unlimited - 7 Days"
            $table->text('description')->nullable();
            $table->string('coverage_type', 20);                       // local | regional | global
            $table->string('region_name', 100)->nullable();            // e.g. "European Union"
            $table->string('data_type', 20);                           // fixed | unlimited
            $table->unsignedInteger('data_amount_mb')->nullable();     // NULL for unlimited
            $table->unsignedSmallInteger('validity_days');
            $table->string('network_speed', 50)->nullable();           // e.g. 5G, 4G/LTE
            $table->json('features')->nullable();                      // hotspot, voice/SMS, instant activation...
            $table->string('badge', 30)->nullable();                   // popular | best_seller
            $table->string('brand_label', 100)->nullable();            // pending decision (report J17)
            $table->boolean('is_active')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('coverage_type');
            $table->index('data_type');
            $table->index('validity_days');
            $table->index(['is_active', 'sort_order']);
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE `plans` ADD CONSTRAINT `chk_plans_coverage_type` CHECK (`coverage_type` IN ('local', 'regional', 'global'))");
            DB::statement("ALTER TABLE `plans` ADD CONSTRAINT `chk_plans_data_type` CHECK (`data_type` IN ('fixed', 'unlimited'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * eSIM usage snapshots (planning report H3.16). The newest row per eSIM
 * (by fetched_at) is the "current" usage. MB values are DECIMAL so fractions
 * of a megabyte reported by the provider are not lost.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('esim_usage', function (Blueprint $table) {
            $table->id();
            $table->foreignId('esim_id')->constrained('esims')->restrictOnDelete();
            $table->decimal('data_total_mb', 14, 2)->nullable();      // NULL for unlimited
            $table->decimal('data_used_mb', 14, 2)->nullable();
            $table->decimal('data_remaining_mb', 14, 2)->nullable();
            $table->boolean('is_unlimited')->default(false);
            $table->string('status', 30)->nullable();
            $table->dateTime('period_start')->nullable();
            $table->dateTime('period_end')->nullable();
            $table->dateTime('fetched_at');
            $table->json('raw_payload')->nullable();
            $table->timestamps();

            $table->index(['esim_id', 'fetched_at']);
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE `esim_usage` ADD CONSTRAINT `chk_esim_usage_values` CHECK ((`data_total_mb` IS NULL OR `data_total_mb` >= 0) AND (`data_used_mb` IS NULL OR `data_used_mb` >= 0) AND (`data_remaining_mb` IS NULL OR `data_remaining_mb` >= 0))');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('esim_usage');
    }
};

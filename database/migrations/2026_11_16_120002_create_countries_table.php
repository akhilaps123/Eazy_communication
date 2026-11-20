<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Destination countries (planning report H3.2).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('countries', function (Blueprint $table) {
            $table->id();
            $table->char('iso2', 2)->unique();          // e.g. JP
            $table->char('iso3', 3)->nullable();        // e.g. JPN
            $table->string('name', 100);
            $table->string('slug', 120)->unique();      // e.g. japan
            $table->string('region', 50)->nullable();   // e.g. Asia, Europe
            $table->string('flag_image')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_popular')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('region');
            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('countries');
    }
};

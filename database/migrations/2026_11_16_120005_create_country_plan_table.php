<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Many-to-many link between countries and plans (planning report H3.5).
 * A regional plan (e.g. Europe) links to every country it covers.
 *
 * Only link rows are removed by cascade; plans and countries themselves are
 * never deleted through this table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('country_plan', function (Blueprint $table) {
            $table->foreignId('country_id')->constrained('countries')->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained('plans')->cascadeOnDelete();

            $table->primary(['country_id', 'plan_id']);
            $table->index('plan_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('country_plan');
    }
};

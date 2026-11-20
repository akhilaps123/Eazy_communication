<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Selling-price rules with currency and history (planning report D and H3.6).
 *
 * pricing_method:
 *   manual          -> selling price = manual_price
 *   markup_percent  -> selling price = provider cost x (1 + markup_value / 100)
 *   markup_fixed    -> selling price = provider cost + markup_value
 *
 * No real prices or markup values are inserted by this migration.
 * New rows default to inactive + pending approval (approval rules: report J11).
 * "Not below provider cost" is enforced in the PlanPrice model, because the
 * cost lives in another table (a CHECK constraint cannot reference it).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained('plans')->restrictOnDelete();
            $table->char('currency', 3);                                   // ISO 4217 — currency not yet decided (J3)
            $table->string('pricing_method', 20);
            $table->decimal('manual_price', 12, 2)->nullable();
            $table->decimal('markup_value', 12, 4)->nullable();            // percent or fixed amount
            $table->decimal('compare_at_price', 12, 2)->nullable();        // crossed-out display price only
            $table->decimal('calculated_price', 12, 2)->nullable();        // stored result of the rule
            $table->dateTime('calculated_at')->nullable();
            $table->string('approval_status', 20)->default('pending');     // pending | approved | rejected
            $table->boolean('is_active')->default(false);
            $table->dateTime('effective_from');
            $table->dateTime('effective_to')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['plan_id', 'currency', 'effective_from']);
            $table->index(['plan_id', 'currency', 'is_active', 'effective_from'], 'plan_prices_lookup_index');
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            $checks = [
                'chk_plan_prices_method' => "`pricing_method` IN ('manual', 'markup_percent', 'markup_fixed')",
                'chk_plan_prices_manual_value' => "`pricing_method` <> 'manual' OR `manual_price` IS NOT NULL",
                'chk_plan_prices_markup_value' => "`pricing_method` = 'manual' OR `markup_value` IS NOT NULL",
                'chk_plan_prices_non_negative' => '(`manual_price` IS NULL OR `manual_price` >= 0) AND (`markup_value` IS NULL OR `markup_value` >= 0) AND (`compare_at_price` IS NULL OR `compare_at_price` >= 0) AND (`calculated_price` IS NULL OR `calculated_price` >= 0)',
                'chk_plan_prices_approval' => "`approval_status` IN ('pending', 'approved', 'rejected')",
                'chk_plan_prices_period' => '`effective_to` IS NULL OR `effective_to` > `effective_from`',
            ];
            foreach ($checks as $name => $expression) {
                DB::statement("ALTER TABLE `plan_prices` ADD CONSTRAINT `{$name}` CHECK ({$expression})");
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_prices');
    }
};

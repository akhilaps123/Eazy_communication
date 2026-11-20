<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Discount codes (planning report H3.9). Usage is counted from paid
 * orders.promo_code_id, so no separate redemption table is needed.
 * Codes start inactive. The unique key is case-insensitive under
 * utf8mb4_unicode_ci, so "SAVE10" and "save10" cannot both exist.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promo_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('description')->nullable();
            $table->string('discount_type', 10);                       // percent | fixed
            $table->decimal('discount_value', 12, 2);
            $table->char('currency', 3)->nullable();                   // required for fixed discounts
            $table->decimal('min_order_amount', 12, 2)->nullable();
            $table->decimal('max_discount_amount', 12, 2)->nullable();
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->unsignedInteger('max_redemptions')->nullable();
            $table->unsignedInteger('max_redemptions_per_user')->nullable();
            $table->boolean('is_active')->default(false);
            $table->timestamps();

            $table->index(['is_active', 'starts_at', 'ends_at']);
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            $checks = [
                'chk_promo_codes_type' => "`discount_type` IN ('percent', 'fixed')",
                'chk_promo_codes_value' => "`discount_value` > 0 AND (`discount_type` <> 'percent' OR `discount_value` <= 100)",
                'chk_promo_codes_fixed_currency' => "`discount_type` <> 'fixed' OR `currency` IS NOT NULL",
                'chk_promo_codes_amounts' => '(`min_order_amount` IS NULL OR `min_order_amount` >= 0) AND (`max_discount_amount` IS NULL OR `max_discount_amount` >= 0)',
                'chk_promo_codes_period' => '`starts_at` IS NULL OR `ends_at` IS NULL OR `ends_at` > `starts_at`',
            ];
            foreach ($checks as $name => $expression) {
                DB::statement("ALTER TABLE `promo_codes` ADD CONSTRAINT `{$name}` CHECK ({$expression})");
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('promo_codes');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Purchased plans with a price snapshot (planning report H3.11).
 * The snapshot columns are copied at checkout, so later plan or price changes
 * never alter historical orders. The OrderItem model also blocks edits to them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->restrictOnDelete();
            $table->foreignId('plan_id')->constrained('plans')->restrictOnDelete();
            $table->string('plan_name');                                 // snapshot
            $table->string('bundle_code', 191);                          // snapshot
            $table->decimal('unit_price', 12, 2);                        // snapshot (order currency)
            $table->unsignedSmallInteger('quantity');
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('line_total', 12, 2);
            $table->decimal('unit_provider_cost', 12, 4)->nullable();    // snapshot for margin reporting
            $table->char('provider_currency', 3)->nullable();
            $table->date('trip_start_date')->nullable();
            $table->timestamps();
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE `order_items` ADD CONSTRAINT `chk_order_items_quantity` CHECK (`quantity` >= 1)');
            DB::statement('ALTER TABLE `order_items` ADD CONSTRAINT `chk_order_items_amounts` CHECK (`unit_price` >= 0 AND `discount_amount` >= 0 AND `tax_amount` >= 0 AND `line_total` >= 0 AND (`unit_provider_cost` IS NULL OR `unit_provider_cost` >= 0))');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};

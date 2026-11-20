<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Plans in a cart (planning report H3.8).
 * Deliberately NO price column: prices are always recalculated on the server
 * from plan_prices, never trusted from the browser.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cart_id')->constrained('carts')->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained('plans')->restrictOnDelete();
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->date('trip_start_date')->nullable();
            $table->timestamps();

            $table->unique(['cart_id', 'plan_id', 'trip_start_date'], 'cart_items_cart_plan_date_unique');
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE `cart_items` ADD CONSTRAINT `chk_cart_items_quantity` CHECK (`quantity` >= 1)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cart_items');
    }
};

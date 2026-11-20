<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Extends Laravel's existing `users` table (planning report H3.1).
 * Customers and staff share this table; `role` tells them apart.
 * No duplicate user table is created.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 30)->nullable()->after('email');
            $table->char('country_code', 2)->nullable()->after('phone');            // ISO 3166-1 alpha-2
            $table->string('role', 20)->default('customer')->after('password');     // customer | admin
            $table->char('preferred_currency', 3)->nullable()->after('role');       // ISO 4217
            $table->string('preferred_language', 10)->nullable()->after('preferred_currency');
            $table->string('billing_gstin', 15)->nullable()->after('preferred_language');
            $table->boolean('marketing_opt_in')->default(false)->after('billing_gstin');
            $table->dateTime('last_login_at')->nullable()->after('marketing_opt_in');
            $table->softDeletes();                                                  // account deletion keeps order history

            $table->index('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role']);
            $table->dropSoftDeletes();
            $table->dropColumn([
                'phone',
                'country_code',
                'role',
                'preferred_currency',
                'preferred_language',
                'billing_gstin',
                'marketing_opt_in',
                'last_login_at',
            ]);
        });
    }
};

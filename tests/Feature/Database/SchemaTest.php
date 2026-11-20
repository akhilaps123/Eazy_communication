<?php

namespace Tests\Feature\Database;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

class SchemaTest extends DatabaseTestCase
{
    /** Business tables from the planning report (H1), plus Laravel's reused tables. */
    public const PLANNED_TABLES = [
        'users', 'password_reset_tokens', 'sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs',
        'countries', 'provider_bundles', 'plans', 'country_plan', 'plan_prices', 'carts', 'cart_items',
        'promo_codes', 'orders', 'order_items', 'payments', 'webhook_events', 'provider_orders',
        'esims', 'esim_usage', 'integration_logs',
    ];

    public function test_all_planned_tables_exist(): void
    {
        foreach (self::PLANNED_TABLES as $table) {
            $this->assertTrue(Schema::hasTable($table), "Missing table [{$table}]");
        }
    }

    public function test_no_duplicate_user_or_customer_table_exists(): void
    {
        $names = array_column(Schema::getTables(), 'name');

        $userLike = array_values(array_filter($names, fn ($name) => preg_match('/^(users?|customers?|accounts?)$/', $name)));
        $this->assertSame(['users'], $userLike);
    }

    public function test_users_table_keeps_laravel_columns_and_gains_profile_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('users', [
            // Laravel's original authentication columns (unchanged)
            'id', 'name', 'email', 'email_verified_at', 'password', 'remember_token', 'created_at', 'updated_at',
            // Approved additions (planning report H3.1)
            'phone', 'country_code', 'role', 'preferred_currency', 'preferred_language',
            'billing_gstin', 'marketing_opt_in', 'last_login_at', 'deleted_at',
        ]));
    }

    public function test_cart_items_have_no_price_column(): void
    {
        $this->assertFalse(Schema::hasColumn('cart_items', 'price'));
        $this->assertFalse(Schema::hasColumn('cart_items', 'unit_price'));
    }

    public function test_every_new_migration_rolls_back_cleanly_and_can_be_reapplied(): void
    {
        // Phase 3 business tables + Sanctum's personal_access_tokens (Phase 4 API).
        $newTables = [...array_slice(self::PLANNED_TABLES, 8), 'personal_access_tokens'];

        // Phase 9E order_notifications table (additive) rolls back on its own first.
        $this->assertTrue(Schema::hasTable('order_notifications'));
        Artisan::call('migrate:rollback', ['--step' => 1]);
        $this->assertFalse(Schema::hasTable('order_notifications'));
        $this->assertTrue(Schema::hasTable('orders'));

        // Phase 8 payment-columns migration rolls back on its own next.
        Artisan::call('migrate:rollback', ['--step' => 1]);
        $this->assertTrue(Schema::hasTable('payments'));
        $this->assertFalse(Schema::hasColumn('payments', 'gateway_order_id'));
        $this->assertFalse(Schema::hasColumn('payments', 'verified_at'));

        // Then 17 Phase 3 migrations + 1 Sanctum migration (in-memory test database only).
        Artisan::call('migrate:rollback', ['--step' => 18]);

        foreach ($newTables as $table) {
            $this->assertFalse(Schema::hasTable($table), "Table [{$table}] still exists after rollback");
        }
        $this->assertTrue(Schema::hasTable('users'), 'Laravel users table must survive rolling back Phase 3');
        $this->assertFalse(Schema::hasColumn('users', 'role'));
        $this->assertFalse(Schema::hasColumn('users', 'deleted_at'));

        $this->artisan('migrate')->assertSuccessful();

        foreach ($newTables as $table) {
            $this->assertTrue(Schema::hasTable($table), "Table [{$table}] missing after re-migrating");
        }
        $this->assertTrue(Schema::hasColumn('users', 'role'));
        $this->assertTrue(Schema::hasColumns('payments', ['gateway_order_id', 'verified_at']));
        $this->assertTrue(Schema::hasTable('order_notifications'));
    }
}

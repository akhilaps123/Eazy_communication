<?php

namespace Tests\Feature\Database;

use Tests\TestCase;

/**
 * Base class for schema tests.
 *
 * SAFETY: these tests create and roll back tables. They refuse to run unless
 * the connection is the in-memory SQLite database configured in phpunit.xml,
 * so they can never touch the real `eazy_communication` (or any other)
 * MySQL/MariaDB database. Laravel's RefreshDatabase trait is deliberately not
 * used, because it would migrate before this check could run.
 */
abstract class DatabaseTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $connection = config('database.default');
        $database = config("database.connections.{$connection}.database");

        if ($connection !== 'sqlite' || $database !== ':memory:') {
            $this->fail("Refusing to run database tests on [{$connection}:{$database}]. Expected in-memory SQLite.");
        }

        $this->artisan('migrate')->assertSuccessful();
    }
}

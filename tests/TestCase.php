<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Data-safety guard, applied BEFORE any trait (RefreshDatabase,
     * DatabaseMigrations…) touches the schema.
     *
     * The suite is destructive by design: it migrates the database from
     * scratch. This repository's local `.env` can legitimately be pointed at
     * the PRODUCTION shared PostgreSQL through the dev SSH tunnel
     * (`scripts/dev-use-prod-db.py`, DB_PORT 15433), and `php artisan test`
     * would then rebuild production. Refuse to start in that case: tests must
     * run against an obviously disposable database.
     */
    protected function setUpTraits()
    {
        $this->guardAgainstNonTestDatabase();

        return parent::setUpTraits();
    }

    private function guardAgainstNonTestDatabase(): void
    {
        $connection = (string) config('database.default');
        $database = (string) config("database.connections.{$connection}.database", '');

        $isolated = match (true) {
            $connection === 'sqlite' => $database === ':memory:'
                || str_contains($database, 'test'),
            in_array($connection, ['pgsql', 'mysql', 'mariadb'], true) => preg_match(
                '/(^|[_-])(test|testing|ci)([_-]|$)/i',
                $database,
            ) === 1,
            default => false,
        };

        if (! $isolated) {
            throw new \RuntimeException(
                "Refusing to run the test suite against [{$connection}] database \"{$database}\": "
                .'the suite drops and rebuilds every table and must only ever touch an isolated '
                .'test database (for example DB_DATABASE=ris_test).'
            );
        }
    }
}

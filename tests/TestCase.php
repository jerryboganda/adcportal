<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

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

        $this->enforceSqliteForeignKeys();
    }

    /**
     * Make SQLite enforce foreign keys, as production PostgreSQL does.
     *
     * SQLite ships with `foreign_keys` OFF, so the fast gate accepted rows that
     * name a user or a role which does not exist: a test could hardcode
     * `created_by => 1` and pass here while PostgreSQL rejected it with
     * appointments_created_by_foreign. That is precisely the class of bug this
     * repository's own StudyTokenAllocatorTest docblock describes as "SQLite
     * stayed green". Turning the pragma on makes the two engines agree, so a
     * latent violation fails on the fast job instead of surprising a deploy.
     */
    private function enforceSqliteForeignKeys(): void
    {
        if ((string) config('database.default') !== 'sqlite') {
            return;
        }

        DB::statement('PRAGMA foreign_keys = ON');
    }
}

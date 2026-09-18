<?php

namespace App\Providers;

use Illuminate\Console\Events\CommandStarting;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // The legacy `module` singleton (App\Classes\Module) was removed with
        // the single-clinic rewrite: the class never existed in this tree and
        // nothing resolves the binding. Do not reintroduce it.
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->environment('production')) {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }

        // ------------------------------------------------------------------
        // Local dev <-> production realtime link guard.
        //
        // Local dev may be pointed at the LIVE production Postgres through the
        // SSH tunnel (scripts/dev-tunnel.sh + scripts/dev-use-prod-db.py). In
        // that mode a habitual `migrate:fresh`, `migrate:refresh`, `db:wipe`
        // or `db:seed` would destroy real clinic data. Block those commands
        // whenever the pgsql connection targets the tunnel port unless the
        // operator opts in explicitly for a single run. Tests are unaffected:
        // phpunit.xml forces sqlite :memory:.
        // ------------------------------------------------------------------
        $linkedToProd = config('database.default') === 'pgsql'
            && (string) config('database.connections.pgsql.port') === '15433';

        if ($linkedToProd) {
            Event::listen(CommandStarting::class, function (CommandStarting $event): void {
                $destructive = ['migrate:fresh', 'migrate:refresh', 'db:wipe', 'db:seed'];

                // env() casts a shell-provided 'true' to boolean true; accept both.
                $override = filter_var(env('ALLOW_PROD_DESTRUCTIVE'), FILTER_VALIDATE_BOOL);

                if (! in_array($event->command, $destructive, true) || $override) {
                    return;
                }

                $out = $event->output;
                $out->writeln('<fg=white;bg=red> DESTRUCTIVE COMMAND BLOCKED </>');
                $out->writeln('This local app is linked to the LIVE production Postgres (dev tunnel).');
                $out->writeln(sprintf("  '<comment>%s</comment>' would destroy real data.", $event->command));
                $out->writeln('To proceed anyway (you take responsibility): ALLOW_PROD_DESTRUCTIVE=true php artisan '.(string) $event->command);
                $out->writeln('Safely apply schema changes instead: bash scripts/dev-migrate-prod.sh (auto pg_dump backup first)');
                $out->writeln('Or switch local dev back to its own DB:        python scripts/dev-use-local-db.py');

                exit(1);
            });
        }

        // Study domain facts → clinical notification center: NO manual
        // registration here. Laravel 11 event discovery binds
        // ProjectStudyNotification (union type-hint) to all five study
        // events automatically — `php artisan event:list` is the proof.
        // Registering manually as well would fire every fact TWICE.
        // The listener runs synchronously inside the workflow unit of
        // work — see ProjectStudyNotification for the atomicity contract.
    }

}

<?php

namespace App\Providers;

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

        // Study domain facts → clinical notification center: NO manual
        // registration here. Laravel 11 event discovery binds
        // ProjectStudyNotification (union type-hint) to all five study
        // events automatically — `php artisan event:list` is the proof.
        // Registering manually as well would fire every fact TWICE.
        // The listener runs synchronously inside the workflow unit of
        // work — see ProjectStudyNotification for the atomicity contract.
    }

}

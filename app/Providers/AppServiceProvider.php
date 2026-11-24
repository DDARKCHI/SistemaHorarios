<?php

namespace App\Providers;

use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::defaultView('paginacion.prosec');

        \Illuminate\Support\Facades\View::composer('*', function ($view) {
            $view->with('identidad', app(\App\Services\IdentidadVisual::class)->datos());
        });
    }
}

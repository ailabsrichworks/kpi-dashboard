<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;
use App\Services\ApprovalHierarchyService;
use App\Services\SupabaseService;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(
            ApprovalHierarchyService::class,
            function ($app) {

                return new ApprovalHierarchyService(
                    $app->make(
                        SupabaseService::class
                    )
                );
            }
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (
            app()->environment('production')
        ) {
            URL::forceScheme('https');
        }
    }
}

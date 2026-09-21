<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
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

        // Unread notification count for the sidebar bell — computed here via a
        // composer so every page that includes the sidebar gets it, instead of
        // every controller having to remember to pass it (see how spotty
        // $pendingApprovalCount is by comparison).
        View::composer('partials.sidebar', function ($view) {
            $unreadCount = 0;
            $employeeId  = session('employee_uuid');

            if ($employeeId) {
                try {
                    // Not filtered by `is_read => eq.false` — see the matching
                    // comment in HandleInertiaRequests::unreadNotificationCount()
                    // for why that silently undercounts NULL rows.
                    $rows = $this->app->make(SupabaseService::class)->get('notifications', [
                        'recipient_employee_id' => 'eq.' . $employeeId,
                        'select'                 => 'is_read',
                    ]) ?? [];
                    $unreadCount = count(array_filter($rows, fn ($row) => empty($row['is_read'])));
                } catch (\Throwable $e) {
                    // Sidebar must never break the whole page over a notification query.
                }
            }

            $view->with('unreadNotificationCount', $unreadCount);
        });
    }
}

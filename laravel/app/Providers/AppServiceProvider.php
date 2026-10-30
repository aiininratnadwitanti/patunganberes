<?php

namespace App\Providers;

use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        View::composer('*', function ($view) {
            if (! auth()->check()) {
                return;
            }

            $view->with($this->navigationData());
        });
    }

    /**
     * Data shared with every view for the authenticated navigation.
     * Memoised so the queries run once per request, not once per view.
     *
     * @return array<string, mixed>
     */
    private function navigationData(): array
    {
        return once(function () {
            $user = auth()->user();

            return [
                'navUser' => $user,
                'navNotifications' => $user->appNotifications()->take(4)->get(),
                'unreadCount' => $user->appNotifications()->where('is_read', false)->count(),
                'navDefaultBank' => $user->defaultBankAccount(),
            ];
        });
    }
}

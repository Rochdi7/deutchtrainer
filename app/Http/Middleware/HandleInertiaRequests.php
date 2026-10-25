<?php

namespace App\Http\Middleware;

use App\Services\StatsService;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user()?->only(['id', 'name', 'email', 'is_admin', 'locale', 'daily_goal', 'reminders_enabled', 'reminder_hour', 'timezone']),
            ],
            'push' => ['vapid_public_key' => config('services.webpush.public_key')],
            // Streak / XP / due words for the top stat bar (cheap: four small queries).
            'stats' => fn () => $request->user() ? app(StatsService::class)->headline($request->user()) : null,
            'locale' => app()->getLocale(),
            'locales' => SetLocale::SUPPORTED,
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }
}

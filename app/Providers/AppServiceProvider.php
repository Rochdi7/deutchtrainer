<?php

namespace App\Providers;

use App\Services\Import\AnthropicClient;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(AnthropicClient::class, fn () => AnthropicClient::fromConfig());
    }

    public function boot(): void
    {
        //
    }
}

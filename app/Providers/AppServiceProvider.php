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
        if (getenv('VERCEL') || getenv('VERCEL_ENV')) {
            config([
                'session.driver' => 'array',
                'cache.default' => 'array',
                'view.compiled' => '/tmp/views',
            ]);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (getenv('VERCEL') || getenv('VERCEL_ENV')) {
            foreach ([
                '/tmp/views',
                '/tmp/cache',
                '/tmp/sessions',
            ] as $directory) {
                if (! is_dir($directory)) {
                    mkdir($directory, 0777, true);
                }
            }
        }
    }
}


<?php

declare(strict_types=1);

namespace Monitor\Exceptions;

use Illuminate\Support\ServiceProvider;

class MonitorExceptionsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/monitor-exceptions.php',
            'monitor-exceptions',
        );
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/monitor-exceptions.php' => config_path('monitor-exceptions.php'),
            ], 'monitor-exceptions-config');
        }
    }
}

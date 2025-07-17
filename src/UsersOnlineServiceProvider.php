<?php

namespace SamuelTerra22\UsersOnline;

use Illuminate\Support\ServiceProvider;

class UsersOnlineServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/users-online.php' => config_path('users-online.php'),
            ], 'users-online-config');
        }
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/users-online.php',
            'users-online'
        );
    }
}

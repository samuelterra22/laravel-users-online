<?php

namespace SamuelTerra22\UsersOnline\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class UsersOnlineEventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        'Illuminate\Auth\Events\Logout' => [
            'SamuelTerra22\UsersOnline\Listeners\LogoutListener',
        ],
        'Illuminate\Auth\Events\Login' => [
            'SamuelTerra22\UsersOnline\Listeners\LoginListener',
        ],
    ];

    /**
     * Register any other events for your application.
     *
     * @return void
     */
    public function boot()
    {
        parent::boot();
    }
}

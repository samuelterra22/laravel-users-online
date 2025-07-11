<?php
declare(strict_types=1);

namespace SamuelTerra22\UsersOnline\Listeners;

use Illuminate\Auth\Events\Login;

class LoginListener
{
    /**
     * Handle the event.
     *
     * @param Login $event
     *
     * @return void
     */
    public function handle(Login $event): void
    {
        if ($event->user !== null) {
            $event->user->setCache(config('session.lifetime') * 60);
        }
    }
}

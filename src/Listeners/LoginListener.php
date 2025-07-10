<?php
declare(strict_types=1);

namespace SamuelTerra22\UsersOnline\Listeners;

class LoginListener
{
    /**
     * Handle the event.
     *
     * @param auth $event .login $event
     *
     * @return void
     */
    public function handle(auth $event)
    {
        if ($event->user !== null) {
            $event->user->setCache(config('session.lifetime') * 60);
        }
    }
}

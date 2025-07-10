<?php
declare(strict_types=1);

namespace SamuelTerra22\UsersOnline\Listeners;

class LogoutListener
{
    /**
     * Handle the event.
     *
     * @param auth.logout $event
     *
     * @return void
     */
    public function handle($event)
    {
        if ($event->user !== null) {
            $event->user->pullCache();
        }
    }
}

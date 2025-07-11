<?php
declare(strict_types=1);

namespace SamuelTerra22\UsersOnline\Listeners;

use Illuminate\Auth\Events\Logout;

class LogoutListener
{
    /**
     * Handle the event.
     *
     * @param Logout $event
     *
     * @return void
     */
    public function handle(Logout $event): void
    {
        if ($event->user !== null) {
            $event->user->pullCache();
        }
    }
}

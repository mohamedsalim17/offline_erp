<?php

namespace App\Listeners;

use App\Models\User;
use Illuminate\Auth\Events\Login;

class RecordUserLogin
{
    public function handle(Login $event): void
    {
        if ($event->user instanceof User) {
            $event->user->increment('login_count', 1, [
                'last_login_at' => now(),
            ]);
        }
    }
}

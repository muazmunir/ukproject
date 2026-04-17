<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\DB;

class MarkStaffOnlineOnLogin
{
    public function handle(Login $event): void
    {
        $u = $event->user;
        if (!$u) return;

        $role = strtolower(trim((string) ($u->role ?? '')));
        if (!in_array($role, ['admin','manager','superadmin'], true)) return;

        $nowUtc = now()->utc()->toDateTimeString();

        // On login: always mark presence ONLINE (prevents confusing offline badge)
        DB::table('users')->where('id', $u->id)->update([
            'support_presence'       => 'online',
            'support_presence_since' => $nowUtc,
            'last_activity_at'       => $nowUtc,
            'updated_at'             => now(),
        ]);
    }
}
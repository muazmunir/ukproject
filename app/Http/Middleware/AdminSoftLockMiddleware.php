<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\AdminActionLog;

class AdminSoftLockMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if (!auth()->check()) return $next($request);

        $user = auth()->user();

        $role = strtolower((string) ($user->role ?? ''));
        if (!in_array($role, ['admin', 'manager', 'superadmin'], true)) {
            return $next($request);
        }

        $routeName = optional($request->route())->getName();

        $allowed = [
            'admin.login',
            'admin.login.submit',
            'admin.logout',
            'admin.locked.notice',
            'admin.locked.soft',
            'admin.locked.soft.unlock',
            'admin.softlock.trigger',
        ];

        $isPageNav =
            $request->isMethod('get') &&
            !$request->ajax() &&
            !$request->expectsJson() &&
            (str_contains((string)$request->header('accept'), 'text/html') || $request->header('accept') === null);

        // treat anything not a page nav as background/polling
        $isBackground = !$isPageNav;

        // already locked
        if (session('admin_soft_locked') && !in_array($routeName, $allowed, true)) {
            if ($isPageNav) {
                session(['admin_intended_url' => $request->fullUrl()]);
            }
            return redirect()->route('admin.locked.soft');
        }

        // server-side idle backup (10 minutes)
        $last = $user->admin_last_seen_at;
        if ($last) {
            $idle = now()->diffInSeconds($last);

            if ($idle >= 600 && !session('admin_soft_locked') && !in_array($routeName, $allowed, true)) {

                DB::transaction(function () use ($user, $idle, $request) {
                    $user->forceFill([
                        'admin_soft_locked_at'  => now(),
                        'admin_soft_lock_count' => ((int)($user->admin_soft_lock_count ?? 0)) + 1,
                    ])->save();

                    AdminActionLog::create([
                        'admin_user_id' => (int)$user->id,
                        'action'        => 'soft_locked',
                        'target_type'   => 'user',
                        'target_id'     => (int)$user->id,
                        'meta'          => ['idle_seconds' => $idle, 'source' => 'server'],
                        'ip'            => $request->ip(),
                        'user_agent'    => substr((string)$request->userAgent(), 0, 500),
                    ]);
                });

                session(['admin_soft_locked' => true]);

                if ($isPageNav) {
                    session(['admin_intended_url' => $request->fullUrl()]);
                }

                return redirect()->route('admin.locked.soft');
            }
        }

        // ✅ update last_seen ONLY on real page navigations (not ajax/background)
        if (!in_array($routeName, ['admin.locked.soft', 'admin.locked.soft.unlock'], true) && $isPageNav) {
            $user->forceFill(['admin_last_seen_at' => now()])->save();
        }

        return $next($request);
    }
}

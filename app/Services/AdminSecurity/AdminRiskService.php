<?php

namespace App\Services\AdminSecurity;

use App\Models\Users;
use App\Models\Service; // ✅ adjust to your real model name (e.g. Services)
use App\Models\AdminActionLog;
use App\Models\AdminSecurityEvent;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AdminRiskService
{
    // thresholds (same as your current)
    protected int $limitShort = 3;
    protected int $windowShortMinutes = 3;

    protected int $limitLong = 10;
    protected int $windowLongHours = 24;

    /**
     * ✅ Call this when deleting a USER
     */
    public function recordUserDeletion(int $adminId, int $deletedUserId, ?string $deletedRole = null): void
    {
        DB::transaction(function () use ($adminId, $deletedUserId, $deletedRole) {

            $target = Users::withTrashed()->find($deletedUserId);

            $this->logDeletion(
                adminId: $adminId,
                action: 'delete_user',
                targetType: Users::class,
                targetId: $deletedUserId,
                meta: [
                    'target_name'  => $target ? (trim(($target->first_name ?? '').' '.($target->last_name ?? '')) ?: null) : null,
                    'target_email' => $target->email ?? null,
                    'target_role'  => $target->role ?? $deletedRole,
                    'deleted_role' => $deletedRole,
                ]
            );

            // ✅ same rules
            $this->evaluateDeletionRules($adminId, 'delete_user');
        });
    }

    /**
     * ✅ Call this when deleting a SERVICE
     */
    public function recordServiceDeletion(int $adminId, int $serviceId): void
    {
        DB::transaction(function () use ($adminId, $serviceId) {

            // If services are soft-deleted, keep withTrashed(). If not, use find().
            $svc = method_exists(Service::query(), 'withTrashed')
                ? Service::withTrashed()->find($serviceId)
                : Service::find($serviceId);

            $this->logDeletion(
                adminId: $adminId,
                action: 'delete_service',
                targetType: Service::class,
                targetId: $serviceId,
                meta: [
                    'service_title' => $svc->title ?? $svc->name ?? null,
                    'service_id'    => $serviceId,
                ]
            );

            // ✅ same rules for service deletions
            $this->evaluateDeletionRules($adminId, 'delete_service');
        });
    }

    /**
     * ✅ Shared log writer
     */
    protected function logDeletion(int $adminId, string $action, string $targetType, int $targetId, array $meta = []): void
    {
        AdminActionLog::create([
            'admin_user_id' => $adminId,
            'action'        => $action,
            'target_type'   => $targetType,
            'target_id'     => $targetId,
            'meta'          => $meta,
            'ip'            => request()->ip(),
            'user_agent'    => substr((string) request()->userAgent(), 0, 500),
        ]);
    }

    /**
     * ✅ Same rules, but per action type (user vs service)
     */
    protected function evaluateDeletionRules(int $adminId, string $action): void
    {
        $now = Carbon::now();

        $countShort = AdminActionLog::where('admin_user_id', $adminId)
            ->where('action', $action)
            ->where('created_at', '>=', $now->copy()->subMinutes($this->windowShortMinutes))
            ->count();

        $countLong = AdminActionLog::where('admin_user_id', $adminId)
            ->where('action', $action)
            ->where('created_at', '>=', $now->copy()->subHours($this->windowLongHours))
            ->count();

        if ($countShort >= $this->limitShort) {
            $this->hardLock($adminId, "{$action}_{$this->limitShort}_in_{$this->windowShortMinutes}min", [
                'action'        => $action,
                'count_short'   => $countShort,
                'window_min'    => $this->windowShortMinutes,
            ]);
            return;
        }

        if ($countLong >= $this->limitLong) {
            $this->hardLock($adminId, "{$action}_{$this->limitLong}_in_{$this->windowLongHours}h", [
                'action'      => $action,
                'count_long'  => $countLong,
                'window_h'    => $this->windowLongHours,
            ]);
            return;
        }
    }

    protected function reasonLabel(string $reason): string
{
    $map = [
        // users
        'delete_user_3_in_3min'   => 'High risk: 3 User Deletions Within 3 Minutes',
        'delete_user_10_in_24h'   => 'High risk: 10 User Deletions Within 24 Hours',

        // services
        'delete_service_3_in_3min' => 'High risk: 3 Service Deletions Within 3 Minutes',
        'delete_service_10_in_24h' => 'High risk: 10 Service Deletions Within 24 Hours',

        // legacy (if you had older names)
        'mass_deletion_3_in_3min' => 'High risk: 3 User Deletions Within 3 Minutes',
        'mass_deletion_10_in_24h' => 'High risk: 10 User Deletions Within 24 Hours',
    ];

    return $map[$reason] ?? ucwords(str_replace('_', ' ', $reason));
}

    protected function hardLock(int $adminId, string $reason, array $meta = []): void
    {
        $admin = Users::withTrashed()->find($adminId);
        if (! $admin) return;

        if ((bool) ($admin->is_locked ?? false)) return;

        $admin->forceFill([
            'is_locked'     => true,
            'locked_at'     => now(),
            'locked_reason' => $reason,
        ])->save();

        AdminActionLog::create([
            'admin_user_id' => $adminId,
            'action'        => 'hard_locked',
            'target_type'   => Users::class,
            'target_id'     => $adminId,
            'meta'          => [
                'target_name'  => trim(($admin->first_name ?? '').' '.($admin->last_name ?? '')) ?: null,
                'target_email' => $admin->email ?? null,
                'target_role'  => $admin->role ?? null,
                'reason'       => $reason,
            ] + $meta,
            'ip'            => request()->ip(),
            'user_agent'    => substr((string) request()->userAgent(), 0, 500),
        ]);

        AdminSecurityEvent::create([
            'admin_user_id' => $adminId,
            'type'          => 'mass_deletion_lock',
            'status'        => 'open',
            'message'       => $this->reasonLabel($reason),
            'meta'          => $meta + [
        'reason'        => $reason,
        'reason_label'  => $this->reasonLabel($reason),
    ],

        ]);

        auth()->logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();
    }


   public function recordServiceRestore(int $adminId, Service $service): void
{
    AdminActionLog::create([
        'admin_user_id' => $adminId,
        'action'        => 'restore_service',
        'target_type'   => Service::class,
        'target_id'     => $service->id,
        'meta'          => [
            'service_title' => $service->title,
            'coach_email'   => $service->coach?->email,
        ],
        'ip'            => request()->ip(),
        'user_agent'    => substr((string) request()->userAgent(), 0, 500),
    ]);
}



public function recordUserRestore(int $adminId, Users $user): void
{
    $role = $user->role; // client | coach | admin | manager

    // Only log client / coach restores here
    if (! in_array($role, ['client','coach'], true)) {
        return;
    }

    AdminActionLog::create([
        'admin_user_id' => $adminId,
        'action'        => 'restore_user_'.$role, // restore_user_client | restore_user_coach
        'target_type'   => Users::class,
        'target_id'     => $user->id,
        'meta'          => [
            'name'  => trim(($user->first_name ?? '').' '.($user->last_name ?? '')),
            'email' => $user->email,
            'role'  => $role,
        ],
        'ip'            => request()->ip(),
        'user_agent'    => substr((string)request()->userAgent(), 0, 500),
    ]);
}




}

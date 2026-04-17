<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class SupportAssignmentService
{
    /**
     * ✅ Pick next available STAFF (admin OR manager) with < 3 open chats.
     * Returns: ['id'=>int,'role'=>'admin|manager','username'=>'...'] or null
     */
    public function pickStaff(): ?array
    {
       $row = DB::table('users')
  ->leftJoin('support_conversations as sc', function ($join) {
      $join->on('sc.assigned_staff_id', '=', 'users.id')
           ->where('sc.status', '=', 'open');
  })
  ->whereIn('users.role', ['admin', 'manager'])
  ->where('users.support_status', 'available')
  ->where('users.support_presence', 'online')
            ->groupBy('users.id', 'users.role', 'users.username', 'users.support_status_since')
            ->select(
                'users.id',
                'users.role',
                'users.username',
                'users.support_status_since',
                DB::raw('COUNT(sc.id) as open_cnt')
            )
            ->havingRaw('COUNT(sc.id) < 3')
            ->orderBy('open_cnt', 'asc')
            ->orderBy('users.support_status_since', 'asc')
            ->first();

        if (!$row || empty($row->id) || empty($row->role)) return null;

        return [
            'id'       => (int) $row->id,
            'role'     => (string) $row->role,     // admin|manager
            'username' => (string) ($row->username ?? 'support'),
        ];
    }

    /**
     * ✅ Pick a manager for escalation, excluding a given manager (so manager escalates => different manager).
     * Returns: ['id'=>int,'username'=>'...'] or null
     */
    public function pickManagerExcluding(?int $excludeId = null): ?array
    {
        $q = DB::table('users')
            ->leftJoin('support_conversations as sc', function ($join) {
                $join->on('sc.assigned_staff_id', '=', 'users.id')
                    ->where('sc.status', '=', 'open')
                    ->where('sc.assigned_staff_role', '=', 'manager');
            })
            ->where('users.role', 'manager')
            ->where('users.support_status', 'available')
            ->where('users.support_presence', 'online');

        if (!empty($excludeId)) {
            $q->where('users.id', '!=', (int) $excludeId);
        }

        $row = $q->groupBy('users.id', 'users.username', 'users.support_status_since')
            ->select(
                'users.id',
                'users.username',
                'users.support_status_since',
                DB::raw('COUNT(sc.id) as open_cnt')
            )
            ->havingRaw('COUNT(sc.id) < 3')
            ->orderBy('open_cnt', 'asc')
            ->orderBy('users.support_status_since', 'asc')
            ->first();

        if (!$row || empty($row->id)) return null;

        return [
            'id'       => (int) $row->id,
            'username' => (string) ($row->username ?? 'manager'),
        ];
    }

    public function pickAgentId(): ?int
    {
        $pick = $this->pickStaff();
        return $pick ? (int) $pick['id'] : null;
    }
}

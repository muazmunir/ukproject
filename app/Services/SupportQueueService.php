<?php

namespace App\Services;

use App\Models\SupportConversation;
use App\Models\SupportMessage;
use Illuminate\Support\Facades\DB;

class SupportQueueService
{
// ✅ backward compatibility for your controller calls
public function dispatchAdmins(int $limit = 20): int
{
return $this->dispatchAgents($limit);
}

/**
 * Assign normal open conversations (not escalated) to available staff (admins + managers).
 * Max 3 each, FIFO.
 */
public function dispatchAgents(int $limit = 20): int
{
return DB::transaction(function () use ($limit) {

$convs = SupportConversation::query()
->whereNull('assigned_staff_id')
->where('status', 'open')
->whereNull('manager_requested_at') // not escalated
->orderByRaw('COALESCE(last_message_at, created_at) ASC')
->limit($limit)
->lockForUpdate()
->get();

if ($convs->isEmpty()) return 0;

$staff = DB::table('users')
->whereIn('role', ['admin','manager'])
->where('support_status', 'available')
->where('support_presence', 'online')   // ✅ STRICT
->lockForUpdate()
->get(['id','role','username']);



if ($staff->isEmpty()) return 0;

$counts = SupportConversation::query()
->where('status', 'open')
->whereNotNull('assigned_staff_id')
->select('assigned_staff_id', DB::raw('COUNT(*) cnt'))
->groupBy('assigned_staff_id')
->pluck('cnt','assigned_staff_id')
->toArray();

$load = [];
foreach ($staff as $s) $load[$s->id] = (int)($counts[$s->id] ?? 0);

$assigned = 0;

foreach ($convs as $c) {
$pick = collect($load)->filter(fn($v) => $v < 3)->sort()->keys()->first();
if (!$pick) break;

$picked = $staff->firstWhere('id', $pick);
$pickedRole = $picked->role; // admin|manager

$ok = SupportConversation::where('id', $c->id)
    ->whereNull('assigned_staff_id')
    ->update([
        'assigned_staff_id'   => $pick,
        'assigned_staff_role' => $pickedRole,

        // legacy compatibility: fill assigned_admin_id only if admin
        'assigned_admin_id'   => $pickedRole === 'admin' ? $pick : null,
        'sla_started_at' => now(),
    ]);

if ($ok) {
    $assigned++;
    $load[$pick]++;

    $name = trim(($picked->first_name ?? '').' '.($picked->last_name ?? ''))
        ?: ($picked->email ?? 'Support');

SupportMessage::create([
'support_conversation_id' => $c->id,
'sender_id'   => $pick,
'sender_type' => 'system',
'type'        => 'system',
'body'        => '',
'meta'        => [
'event'          => 'agent_assigned',      // ✅ use ONE event everywhere
'agent_id'       => $pick,
'agent_role'     => $pickedRole,
'agent_username' => (string) ($picked->username ?? 'support'),
'escalation'     => 0,
],
]);


}
}

return $assigned;
});
}

/**
 * Assign escalations to available managers automatically (no Join click).
 * Excludes the requester manager (manager_requested_by).
 * Auto-joins by setting manager_joined_at = now().
 */
public function dispatchManagers(int $limit = 20): int
{
return DB::transaction(function () use ($limit) {

$convs = SupportConversation::query()
->where('status', 'waiting_manager')
->whereNotNull('manager_requested_at')
->whereNull('manager_id')
->orderBy('manager_requested_at', 'asc')
->limit($limit)
->lockForUpdate()
->get();

if ($convs->isEmpty()) return 0;

$managers = DB::table('users')
->where('role', 'manager')
->where('support_status', 'available')
->where('support_presence', 'online')   // ✅ STRICT
->lockForUpdate()
->get(['id','username']);



if ($managers->isEmpty()) return 0;

$counts = SupportConversation::query()
->where('status', 'open')
->where('assigned_staff_role', 'manager')
->whereNotNull('assigned_staff_id')
->select('assigned_staff_id', DB::raw('COUNT(*) cnt'))
->groupBy('assigned_staff_id')
->pluck('cnt','assigned_staff_id')
->toArray();

$load = [];
foreach ($managers as $m) $load[$m->id] = (int)($counts[$m->id] ?? 0);

$assigned = 0;

foreach ($convs as $c) {
$excludeId = (int)($c->manager_requested_by ?? 0);

$pick = collect($load)
    ->filter(fn($v, $k) => $v < 3 && (int)$k !== $excludeId)
    ->sort()
    ->keys()
    ->first();

if (!$pick) continue; // stays waiting

$m = $managers->firstWhere('id', $pick);
$managerUsername = (string) ($m->username ?? 'manager');

$ok = SupportConversation::where('id', $c->id)
    ->whereNull('manager_id')
    ->update([
        'manager_id'        => $pick,
        'manager_joined_at' => now(), // ✅ auto join
        'manager_ended_at'  => null,

        // ✅ manager becomes new owner/handler
        'assigned_staff_id'   => $pick,
        'assigned_staff_role' => 'manager',
        'assigned_admin_id'   => null,

        // ✅ back to open since no manual join step
        'status' => 'open',
        'sla_started_at' => now(),
    ]);

if ($ok) {
    $assigned++;
    $load[$pick]++;

    SupportMessage::create([
        'support_conversation_id' => $c->id,
        'sender_id'   => $pick,
        'sender_type' => 'system',
        'type'        => 'system',
        'body'        => '',
        'meta' => [
'event'            => 'manager_joined',
'manager_id'       => $pick,
'manager_username' => $managerUsername,
'escalation'       => 1,
],
    ]);
}
}

return $assigned;
});
}


/**
 * Requeue ALL open conversations currently assigned to a staff member.
 * Used when staff goes offline / tech_issues etc.
 *
 * $role = 'admin'|'manager'
 */
public function requeueStaffOpenConversations(int $staffId, string $role = 'admin'): int
{
$role = strtolower(trim($role));
if (!in_array($role, ['admin','manager'], true)) $role = 'admin';

return DB::transaction(function () use ($staffId, $role) {

// Only open conversations assigned to this staff member
$convs = SupportConversation::query()
->where('status', 'open')
->where('assigned_staff_id', $staffId)
->where('assigned_staff_role', $role)
->lockForUpdate()
->get();

if ($convs->isEmpty()) return 0;

$count = 0;

foreach ($convs as $c) {

// If it is escalated chat, requeue it back to waiting_manager
$isEscalated = !is_null($c->manager_requested_at);

$update = [
    'assigned_staff_id'   => null,
    'assigned_staff_role' => null,
    'assigned_admin_id'   => null, // legacy
    'sla_started_at'      => null,
    'updated_at'          => now(),
];

if ($isEscalated) {
    $update['status'] = 'waiting_manager';
    // NOTE: keep manager_requested_at/by as-is, keep manager_id NULL (or set null just in case)
    $update['manager_id'] = null;
    $update['manager_joined_at'] = null;
    $update['manager_ended_at'] = null;
}

$ok = SupportConversation::where('id', $c->id)
    ->where('assigned_staff_id', $staffId)
    ->update($update);

if ($ok) {
    $count++;

    // system log
    SupportMessage::create([
        'support_conversation_id' => $c->id,
        'sender_id'   => $staffId,
        'sender_type' => 'system',
        'type'        => 'system',
        'body'        => '',
        'meta'        => [
            'event'      => 'conversation_requeued',
            'staff_id'   => $staffId,
            'staff_role' => $role,
            'reason'     => 'staff_unavailable',
            'escalation' => $isEscalated ? 1 : 0,
        ],
    ]);
}
}

return $count;
});
}

}

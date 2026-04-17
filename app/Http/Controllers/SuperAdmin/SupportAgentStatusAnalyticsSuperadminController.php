<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Users;
use App\Services\SupportAgentStatusAnalyticsService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SupportAgentStatusAnalyticsSuperadminController extends Controller
{
    /**
     * Adjust this if you want to hide superadmins from the picker.
     */
    private const VISIBLE_ROLES = ['admin', 'manager', 'superadmin'];

    private function role(Request $request): string
    {
        return strtolower(trim((string) ($request->user()->role ?? '')));
    }

    private function abortUnlessSuperadmin(Request $request): void
    {
        abort_unless($this->role($request) === 'superadmin', 403);
    }

    private function normalizeMode(?string $mode): string
    {
        $mode = strtolower(trim((string) $mode));
        return in_array($mode, ['individual', 'team'], true) ? $mode : 'individual';
    }

    private function normalizeRange(?string $range): string
    {
        $range = strtolower(trim((string) $range));
        return in_array($range, ['daily', 'weekly', 'monthly', 'yearly', 'lifetime', 'custom'], true)
            ? $range
            : 'weekly';
    }

    private function peopleQuery()
    {
        return Users::query()
            ->whereIn('role', self::VISIBLE_ROLES)
            ->select(
                'id',
                'first_name',
                'last_name',
                'email',
                'timezone',
                'role',
                'shift_enabled',
                'shift_start',
                'shift_end',
                'shift_days'
            )
            ->orderByRaw("FIELD(role, 'superadmin', 'manager', 'admin')")
            ->orderBy('first_name')
            ->orderBy('last_name');
    }

    private function teamsQuery()
    {
        return DB::table('staff_teams as st')
            ->leftJoin('users as manager', 'manager.id', '=', 'st.manager_id')
            ->whereNull('st.deleted_at')
            ->where('st.is_active', 1)
            ->select([
                'st.id',
                'st.name',
                'st.manager_id',
                'manager.first_name as manager_first_name',
                'manager.last_name as manager_last_name',
                'manager.email as manager_email',
            ])
            ->orderBy('st.name')
            ->orderBy('st.id');
    }

    private function teamMembersQuery(int $teamId)
    {
        $now = now('UTC')->format('Y-m-d H:i:s');

        return Users::query()
            ->join('staff_team_members as stm', 'stm.agent_id', '=', 'users.id')
            ->join('staff_teams as st', 'st.id', '=', 'stm.team_id')
            ->where('st.id', $teamId)
            ->whereNull('st.deleted_at')
            ->where('st.is_active', 1)
            ->where('stm.start_at', '<=', $now)
            ->where(function ($q) use ($now) {
                $q->whereNull('stm.end_at')
                    ->orWhere('stm.end_at', '>', $now);
            })
            ->whereIn('users.role', self::VISIBLE_ROLES)
            ->select(
                'users.id',
                'users.first_name',
                'users.last_name',
                'users.email',
                'users.timezone',
                'users.role',
                'users.shift_enabled',
                'users.shift_start',
                'users.shift_end',
                'users.shift_days'
            )
            ->distinct()
            ->orderByRaw("FIELD(users.role, 'superadmin', 'manager', 'admin')")
            ->orderBy('users.first_name')
            ->orderBy('users.last_name');
    }

    private function validateCalendarInputs(Request $request, string $range): array
    {
        $anchor = $request->input('anchor');
        $from   = $request->input('from');
        $to     = $request->input('to');

        if ($range !== 'lifetime' && $anchor) {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $anchor)) {
                abort(response()->json(['ok' => false, 'message' => 'Invalid anchor date'], 422));
            }
        }

        if ($range === 'custom') {
            if ($from && !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $from)) {
                abort(response()->json(['ok' => false, 'message' => 'Invalid from date'], 422));
            }

            if ($to && !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $to)) {
                abort(response()->json(['ok' => false, 'message' => 'Invalid to date'], 422));
            }
        }

        return [
            'anchor' => $anchor ? (string) $anchor : null,
            'from'   => $from ? (string) $from : null,
            'to'     => $to ? (string) $to : null,
        ];
    }

    private function displayTimezoneFor(Users $user): string
    {
        $tz = $user->timezone ?: 'UTC';

        try {
            new \DateTimeZone($tz);
        } catch (\Throwable $e) {
            $tz = 'UTC';
        }

        return $tz;
    }

    private function buildShiftMeta(Users $target): array
    {
        $dayMap = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'];

        $shiftEnabled = (bool) ($target->shift_enabled ?? false);
        $shiftStart   = $target->shift_start;
        $shiftEnd     = $target->shift_end;

        $days = $target->shift_days ?: [];
        if (is_string($days)) {
            $decoded = json_decode($days, true);
            $days = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($days)) {
            $days = [];
        }

        $days = array_values(array_unique(array_map('intval', $days)));
        sort($days);

        if ($shiftEnabled) {
            $ss = $shiftStart ? substr((string) $shiftStart, 0, 5) : '—';
            $se = $shiftEnd   ? substr((string) $shiftEnd, 0, 5)   : '—';
            $d  = $days ? implode(', ', array_map(fn ($x) => $dayMap[$x] ?? (string) $x, $days)) : '—';

            $label = "{$d} • {$ss}–{$se}";
        } else {
            $label = 'Shift Disabled';
        }

        return [
            'enabled' => $shiftEnabled,
            'start'   => $shiftStart ? substr((string) $shiftStart, 0, 5) : null,
            'end'     => $shiftEnd   ? substr((string) $shiftEnd, 0, 5)   : null,
            'days'    => $days,
            'label'   => $label,
        ];
    }

    private function personName(Users $user): string
    {
        $name = trim(($user->first_name . ' ' . $user->last_name));
        return $name !== '' ? $name : (string) $user->email;
    }

    private function buildMemberPayload(
        Users $target,
        string $range,
        SupportAgentStatusAnalyticsService $svc,
        ?string $anchor,
        ?string $from,
        ?string $to
    ): array {
        $displayTz = $this->displayTimezoneFor($target);

        $payload = $svc->buildForUser(
            (int) $target->id,
            $range,
            $displayTz,
            $anchor,
            $from,
            $to
        );

        return [
            'user_id'      => (int) $target->id,
            'target_name'  => $this->personName($target),
            'target_role'  => strtolower((string) $target->role),
            'display_tz'   => $displayTz,
            'shift'        => $this->buildShiftMeta($target),
        ] + $payload;
    }

    private function aggregateTeamTotals(Collection $members): array
    {
        $seconds = [];

        foreach ($members as $member) {
            $memberTotals = (array) ($member['totals_seconds'] ?? []);

            foreach ($memberTotals as $status => $value) {
                $seconds[$status] = (int) ($seconds[$status] ?? 0) + (int) $value;
            }
        }

        $minutes = [];
        $hours   = [];

        foreach ($seconds as $status => $sec) {
            $minutes[$status] = (int) floor($sec / 60);
            $hours[$status]   = round($sec / 3600, 2);
        }

        return [
            'totals'         => $hours,
            'totals_hours'   => $hours,
            'totals_minutes' => $minutes,
            'totals_seconds' => $seconds,
        ];
    }

    private function findSelectablePerson(Collection $people, int $requestedId): ?Users
    {
        if ($requestedId > 0) {
            $found = $people->firstWhere('id', $requestedId);
            if ($found instanceof Users) {
                return $found;
            }
        }

        $first = $people->first();
        return $first instanceof Users ? $first : null;
    }

    public function index(Request $request)
    {
        $this->abortUnlessSuperadmin($request);

        $mode = $this->normalizeMode($request->input('mode', 'individual'));

        $people = $this->peopleQuery()->get();
        $teams  = $this->teamsQuery()->get();

        abort_unless($people->count() > 0 || $teams->count() > 0, 404);

        $requestedTargetId = (int) $request->input('user_id', 0);
        $requestedTeamId   = (int) $request->input('team_id', 0);

        $target = $this->findSelectablePerson($people, $requestedTargetId);

        $selectedTeam = null;
        if ($requestedTeamId > 0) {
            $selectedTeam = $teams->firstWhere('id', $requestedTeamId);
        }
        if (!$selectedTeam && $teams->count() > 0) {
            $selectedTeam = $teams->first();
        }

        return view('superadmin.support.status_analytics.index', [
            'mode'         => $mode,
            'people'       => $people,
            'teams'        => $teams,
            'selectedTeam' => $selectedTeam,
            'target'       => $target,
        ]);
    }

    public function data(Request $request, SupportAgentStatusAnalyticsService $svc)
    {
        $this->abortUnlessSuperadmin($request);

        $mode  = $this->normalizeMode($request->input('mode', 'individual'));
        $range = $this->normalizeRange($request->input('range', 'weekly'));

        $calendar = $this->validateCalendarInputs($request, $range);

        if ($mode === 'team') {
            $teamId = (int) $request->input('team_id', 0);
            if (!$teamId) {
                return response()->json(['ok' => false, 'message' => 'Missing team_id'], 422);
            }

            $team = $this->teamsQuery()->where('st.id', $teamId)->first();
            if (!$team) {
                return response()->json(['ok' => false, 'message' => 'Team not found'], 404);
            }

            $members = $this->teamMembersQuery($teamId)->get();

            $memberPayloads = $members->map(function (Users $member) use ($svc, $range, $calendar) {
                return $this->buildMemberPayload(
                    $member,
                    $range,
                    $svc,
                    $calendar['anchor'],
                    $calendar['from'],
                    $calendar['to']
                );
            })->values();

            $managerName = trim(
                (($team->manager_first_name ?? '') . ' ' . ($team->manager_last_name ?? ''))
            );
            if ($managerName === '') {
                $managerName = (string) ($team->manager_email ?? '');
            }

            return response()->json([
                'ok'            => true,
                'mode'          => 'team',
                'range'         => $range,
                'team_id'       => (int) $team->id,
                'team_name'     => (string) $team->name,
                'team_manager'  => $managerName !== '' ? $managerName : null,
                'member_count'  => $memberPayloads->count(),
                'members'       => $memberPayloads,
                'anchor'        => $calendar['anchor'],
                'custom'        => [
                    'from' => $calendar['from'],
                    'to'   => $calendar['to'],
                ],
            ] + $this->aggregateTeamTotals($memberPayloads));
        }

        $targetId = (int) $request->input('user_id', 0);
        if (!$targetId) {
            return response()->json(['ok' => false, 'message' => 'Missing user_id'], 422);
        }

        $target = Users::query()
            ->select(
                'id',
                'first_name',
                'last_name',
                'email',
                'timezone',
                'shift_enabled',
                'shift_start',
                'shift_end',
                'shift_days',
                'role'
            )
            ->findOrFail($targetId);

        abort_unless(
            in_array(strtolower((string) $target->role), self::VISIBLE_ROLES, true),
            403
        );

        $personPayload = $this->buildMemberPayload(
            $target,
            $range,
            $svc,
            $calendar['anchor'],
            $calendar['from'],
            $calendar['to']
        );

        return response()->json([
            'ok'          => true,
            'mode'        => 'individual',
            'range'       => $range,
            'user_id'     => (int) $target->id,
            'target_name' => $this->personName($target),
            'target_role' => strtolower((string) $target->role),
            'anchor'      => $calendar['anchor'],
            'custom'      => [
                'from' => $calendar['from'],
                'to'   => $calendar['to'],
            ],
        ] + $personPayload);
    }
}
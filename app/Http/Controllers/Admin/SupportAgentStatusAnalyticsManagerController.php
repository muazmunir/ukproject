<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Users;
use App\Services\SupportAgentStatusAnalyticsService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SupportAgentStatusAnalyticsManagerController extends Controller
{
    public function index(Request $request)
    {
        $this->abortUnlessManagerOrSuperadmin($request);

        $scope = $this->normalizeScope((string) $request->input('scope', 'team'));
        $mode = $this->normalizeMode((string) $request->input('mode', 'individual'));

        $people = $this->accessiblePeople($request, $scope);
        $teams = $this->accessibleTeams($request);

        $selectedUserId = (int) $request->input('user_id', 0);
        if (!$people->firstWhere('id', $selectedUserId)) {
            $selectedUserId = (int) optional($people->first())->id;
        }

        $selectedTeamId = (int) $request->input('team_id', 0);
        if (!$teams->firstWhere('id', $selectedTeamId)) {
            $selectedTeamId = (int) optional($teams->first())->id;
        }

        return view('admin.support.status_analytics.manager', [
            'scope' => $scope,
            'mode' => $mode,
            'people' => $people,
            'teams' => $teams,
            'selectedUserId' => $selectedUserId,
            'selectedTeamId' => $selectedTeamId,
        ]);
    }

    public function data(Request $request, SupportAgentStatusAnalyticsService $service): JsonResponse
    {
        $this->abortUnlessManagerOrSuperadmin($request);

        $range = strtolower((string) $request->input('range', 'weekly'));
        if (!in_array($range, ['daily', 'weekly', 'monthly', 'yearly', 'lifetime', 'custom'], true)) {
            return response()->json(['ok' => false, 'message' => 'Invalid range'], 422);
        }

        $scope = $this->normalizeScope((string) $request->input('scope', 'team'));
        $mode = $this->normalizeMode((string) $request->input('mode', 'individual'));

        $anchor = $request->input('anchor');
        $from = $request->input('from');
        $to = $request->input('to');

        if ($range !== 'lifetime' && $anchor && !$this->isDateString((string) $anchor)) {
            return response()->json(['ok' => false, 'message' => 'Invalid anchor date'], 422);
        }
        if ($range === 'custom') {
            if ($from && !$this->isDateString((string) $from)) {
                return response()->json(['ok' => false, 'message' => 'Invalid from date'], 422);
            }
            if ($to && !$this->isDateString((string) $to)) {
                return response()->json(['ok' => false, 'message' => 'Invalid to date'], 422);
            }
        }

        $members = collect();
        $selectedTeam = null;

        if ($mode === 'team') {
            $teamId = (int) $request->input('team_id', 0);
            if (!$teamId) {
                return response()->json(['ok' => false, 'message' => 'Missing team_id'], 422);
            }

            $selectedTeam = $this->accessibleTeams($request)->firstWhere('id', $teamId);
            abort_unless($selectedTeam, 403);

            $members = $this->teamMembersForViewer($request, $teamId);
        } else {
            $userId = (int) $request->input('user_id', 0);
            if (!$userId) {
                return response()->json(['ok' => false, 'message' => 'Missing user_id'], 422);
            }

            $target = Users::query()
                ->select('id', 'first_name', 'last_name', 'email', 'timezone', 'role', 'shift_enabled', 'shift_start', 'shift_end', 'shift_days')
                ->findOrFail($userId);

            abort_unless($this->canViewUser($request, $target, $scope), 403);
            $members = collect([$target]);
        }

        $analytics = $service->buildForUsers(
            $members,
            $range,
            $anchor ? (string) $anchor : null,
            $from ? (string) $from : null,
            $to ? (string) $to : null
        );

        $memberPayload = $members
            ->map(fn($member) => $this->formatMemberPayload($member, $analytics[(int) $member->id] ?? [], $selectedTeam))
            ->values();

        return response()->json([
            'ok' => true,
            'scope' => $scope,
            'mode' => $mode,
            'range' => $range,
            'anchor' => $anchor,
            'custom' => ['from' => $from, 'to' => $to],
            'members_count' => $memberPayload->count(),
            'selected_team' => $selectedTeam ? [
                'id' => (int) $selectedTeam->id,
                'name' => (string) $selectedTeam->name,
            ] : null,
            'members' => $memberPayload,
        ]);
    }

    private function role(Request $request): string
    {
        return strtolower(trim((string) ($request->user()->role ?? '')));
    }

    private function abortUnlessManagerOrSuperadmin(Request $request): void
    {
        abort_unless(in_array($this->role($request), ['manager', 'superadmin'], true), 403);
    }

    private function normalizeScope(string $scope): string
    {
        $scope = strtolower(trim($scope));
        return in_array($scope, ['team', 'all'], true) ? $scope : 'team';
    }

    private function normalizeMode(string $mode): string
    {
        $mode = strtolower(trim($mode));
        return in_array($mode, ['individual', 'team'], true) ? $mode : 'individual';
    }

    private function isDateString(string $value): bool
    {
        return (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $value);
    }

    private function accessiblePeople(Request $request, string $scope): Collection
    {
        $viewer = $request->user();
        $role = $this->role($request);

        if ($role === 'superadmin') {
            return Users::query()
                ->whereIn('role', ['admin', 'manager', 'superadmin'])
                ->select('id', 'first_name', 'last_name', 'email', 'timezone', 'role', 'shift_enabled', 'shift_start', 'shift_end', 'shift_days')
                ->orderBy('first_name')
                ->orderBy('last_name')
                ->get();
        }

        $collection = collect();

        $self = Users::query()
            ->select('id', 'first_name', 'last_name', 'email', 'timezone', 'role', 'shift_enabled', 'shift_start', 'shift_end', 'shift_days')
            ->find($viewer->id);
        if ($self) {
            $collection->push($self);
        }

        $admins = $scope === 'all'
            ? $this->allAdminsQuery()->get()
            : $this->teamAdminsQuery((int) $viewer->id)->get();

        foreach ($admins as $admin) {
            if (!$collection->firstWhere('id', $admin->id)) {
                $collection->push($admin);
            }
        }

        return $collection
            ->sortBy([
                ['first_name', 'asc'],
                ['last_name', 'asc'],
            ])
            ->values();
    }

    private function accessibleTeams(Request $request): Collection
    {
        $role = $this->role($request);
        $viewer = $request->user();
        $now = now('UTC')->format('Y-m-d H:i:s');

        $query = DB::table('staff_teams as st')
            ->whereNull('st.deleted_at')
            ->where('st.is_active', 1)
            ->select('st.id', 'st.name', 'st.manager_id')
            ->orderBy('st.name');

        if ($role === 'manager') {
            $query->where('st.manager_id', $viewer->id);
        }

        $teams = collect($query->get())->map(function ($team) use ($now) {
            $count = DB::table('staff_team_members as stm')
                ->join('users', 'users.id', '=', 'stm.agent_id')
                ->where('stm.team_id', $team->id)
                ->where('users.role', 'admin')
                ->where('stm.start_at', '<=', $now)
                ->where(function ($q) use ($now) {
                    $q->whereNull('stm.end_at')
                        ->orWhere('stm.end_at', '>', $now);
                })
                ->count();

            $team->members_count = (int) $count;
            return $team;
        });

        return $teams->values();
    }

    private function canViewUser(Request $request, Users $target, string $scope): bool
    {
        $viewer = $request->user();
        $role = $this->role($request);

        if ($role === 'superadmin') {
            return true;
        }

        if ((int) $target->id === (int) $viewer->id) {
            return true;
        }

        if (strtolower((string) $target->role) !== 'admin') {
            return false;
        }

        if ($scope === 'all') {
            return true;
        }

        return $this->isAdminInManagersTeam((int) $viewer->id, (int) $target->id);
    }

    private function teamMembersForViewer(Request $request, int $teamId): Collection
    {
        $role = $this->role($request);
        $viewer = $request->user();

        $team = DB::table('staff_teams as st')
            ->where('st.id', $teamId)
            ->whereNull('st.deleted_at')
            ->where('st.is_active', 1)
            ->when($role === 'manager', fn($q) => $q->where('st.manager_id', $viewer->id))
            ->first();

        abort_unless($team, 403);

        return $this->teamMembersQuery($teamId)->get();
    }

    private function teamMembersQuery(int $teamId): Builder
    {
        $now = now('UTC')->format('Y-m-d H:i:s');

        return Users::query()
            ->join('staff_team_members as stm', 'stm.agent_id', '=', 'users.id')
            ->where('stm.team_id', $teamId)
            ->where('users.role', 'admin')
            ->where('stm.start_at', '<=', $now)
            ->where(function ($q) use ($now) {
                $q->whereNull('stm.end_at')
                    ->orWhere('stm.end_at', '>', $now);
            })
            ->select('users.id', 'users.first_name', 'users.last_name', 'users.email', 'users.timezone', 'users.role', 'users.shift_enabled', 'users.shift_start', 'users.shift_end', 'users.shift_days')
            ->distinct()
            ->orderBy('users.first_name')
            ->orderBy('users.last_name');
    }

    private function teamAdminsQuery(int $managerId): Builder
    {
        $now = now('UTC')->format('Y-m-d H:i:s');

        return Users::query()
            ->join('staff_team_members as stm', 'stm.agent_id', '=', 'users.id')
            ->join('staff_teams as st', 'st.id', '=', 'stm.team_id')
            ->whereNull('st.deleted_at')
            ->where('st.is_active', 1)
            ->where('st.manager_id', $managerId)
            ->where('users.role', 'admin')
            ->where('stm.start_at', '<=', $now)
            ->where(function ($q) use ($now) {
                $q->whereNull('stm.end_at')
                    ->orWhere('stm.end_at', '>', $now);
            })
            ->select('users.id', 'users.first_name', 'users.last_name', 'users.email', 'users.timezone', 'users.role', 'users.shift_enabled', 'users.shift_start', 'users.shift_end', 'users.shift_days')
            ->distinct()
            ->orderBy('users.first_name')
            ->orderBy('users.last_name');
    }

    private function allAdminsQuery(): Builder
    {
        return Users::query()
            ->where('role', 'admin')
            ->select('id', 'first_name', 'last_name', 'email', 'timezone', 'role', 'shift_enabled', 'shift_start', 'shift_end', 'shift_days')
            ->orderBy('first_name')
            ->orderBy('last_name');
    }

    private function isAdminInManagersTeam(int $managerId, int $adminId): bool
    {
        $now = now('UTC')->format('Y-m-d H:i:s');

        return DB::table('staff_team_members as stm')
            ->join('staff_teams as st', 'st.id', '=', 'stm.team_id')
            ->where('st.manager_id', $managerId)
            ->whereNull('st.deleted_at')
            ->where('st.is_active', 1)
            ->where('stm.agent_id', $adminId)
            ->where('stm.start_at', '<=', $now)
            ->where(function ($q) use ($now) {
                $q->whereNull('stm.end_at')
                    ->orWhere('stm.end_at', '>', $now);
            })
            ->exists();
    }

    private function formatMemberPayload($member, array $analytics, $team = null): array
    {
        $days = $member->shift_days ?: [];
        if (is_string($days)) {
            $decoded = json_decode($days, true);
            $days = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($days)) {
            $days = [];
        }

        $days = array_values(array_unique(array_map('intval', $days)));
        sort($days);

        $dayMap = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'];
        $shiftEnabled = (bool) ($member->shift_enabled ?? false);
        $shiftStart = $member->shift_start ? substr((string) $member->shift_start, 0, 5) : null;
        $shiftEnd = $member->shift_end ? substr((string) $member->shift_end, 0, 5) : null;
        $shiftDays = $days ? implode(', ', array_map(fn($x) => $dayMap[$x] ?? (string) $x, $days)) : '—';

        $name = trim(($member->first_name . ' ' . $member->last_name));
        if ($name === '') {
            $name = (string) $member->email;
        }

        return [
            'user' => [
                'id' => (int) $member->id,
                'name' => $name,
                'email' => (string) $member->email,
                'role' => (string) $member->role,
                'timezone' => (string) ($member->timezone ?: 'UTC'),
            ],
            'team' => $team ? [
                'id' => (int) $team->id,
                'name' => (string) $team->name,
            ] : null,
            'shift' => [
                'enabled' => $shiftEnabled,
                'start' => $shiftStart,
                'end' => $shiftEnd,
                'days' => $days,
                'label' => $shiftEnabled ? ($shiftDays . ' • ' . ($shiftStart ?: '—') . '–' . ($shiftEnd ?: '—')) : 'Shift Disabled',
            ],
        ] + $analytics;
    }
}

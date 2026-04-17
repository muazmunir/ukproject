<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\StaffTeam;
use App\Models\StaffTeamMember;
use App\Models\StaffDmThread;
use App\Models\Users;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class StaffTeamController extends Controller
{
    private function managers()
    {
        return Users::query()
            ->whereRaw('LOWER(role) = ?', ['manager'])
            ->orderBy('username');
    }

    private function agentActiveTeamMap(): array
    {
        $rows = StaffTeamMember::query()
            ->whereNull('end_at')
            ->with(['team.manager'])
            ->get();

        $map = [];

        foreach ($rows as $m) {
            if (!$m->team) {
                continue;
            }

            $map[$m->agent_id] = [
                'team_id'   => $m->team_id,
                'team_name' => $m->team->name,
                'manager'   => $m->team->manager->username ?? $m->team->manager->name ?? 'No Manager',
            ];
        }

        return $map;
    }

    private function agents()
    {
        // your admin role represents agents
        return Users::query()
            ->whereRaw('LOWER(role) IN ("admin","super_admin")')
            ->orderBy('username');
    }

    public function index(Request $r)
    {
        $q = StaffTeam::query()
            ->with(['manager', 'activeMembers.agent'])
            ->latest();

        if ($s = trim((string) $r->get('q'))) {
            $q->where('name', 'like', "%{$s}%");
        }

        if ($mid = $r->get('manager_id')) {
            $q->where('manager_id', (int) $mid);
        }

        if (($status = $r->get('status')) !== null && $status !== '') {
            if ($status === 'active') {
                $q->where('is_active', true);
            }
            if ($status === 'inactive') {
                $q->where('is_active', false);
            }
        }

        if ($r->boolean('trashed')) {
            $q->withTrashed();
        }

        $teams = $q->paginate(20)->appends($r->query());
        $managers = $this->managers()->get();

        return view('superadmin.teams.index', compact('teams', 'managers'));
    }

    private function managerActiveTeamMap(): array
    {
        $teams = StaffTeam::query()
            ->select('id', 'name', 'manager_id')
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->whereNotNull('manager_id')
            ->get();

        $map = [];

        foreach ($teams as $t) {
            $map[$t->manager_id] = [
                'team_id'   => $t->id,
                'team_name' => $t->name,
            ];
        }

        return $map;
    }

    public function create()
    {
        $managers = $this->managers()->get();
        $agents = $this->agents()->get();

        $agentTeamMap = $this->agentActiveTeamMap();
        $managerTeamMap = $this->managerActiveTeamMap();

        return view('superadmin.teams.create', compact(
            'managers',
            'agents',
            'agentTeamMap',
            'managerTeamMap'
        ));
    }

    public function store(Request $r)
    {
        $r->validate([
            'name' => ['required', 'string', 'max:120'],
            'manager_id' => [
                'nullable',
                'exists:users,id',
                Rule::unique('staff_teams', 'manager_id')
                    ->where(fn ($q) => $q->where('is_active', true)->whereNull('deleted_at')),
            ],
            'agent_ids' => ['array'],
            'agent_ids.*' => ['integer', 'exists:users,id'],
        ]);

        DB::transaction(function () use ($r) {
            $managerId = $r->filled('manager_id') ? (int) $r->manager_id : null;

            $team = StaffTeam::create([
                'name'       => $r->name,
                'manager_id' => $managerId,
                'is_active'  => true,
            ]);

            $agentIds = array_values(array_unique($r->input('agent_ids', [])));

            foreach ($agentIds as $aid) {
                // end any previous active assignments for this agent
                StaffTeamMember::where('agent_id', $aid)
                    ->whereNull('end_at')
                    ->update(['end_at' => now()]);

                // assign into this team
                StaffTeamMember::create([
                    'team_id'  => $team->id,
                    'agent_id' => $aid,
                    'start_at' => now(),
                ]);

                // deactivate old active threads for this agent
                StaffDmThread::where('agent_id', $aid)
                    ->where('is_active', true)
                    ->update(['is_active' => false]);

                // create/activate thread only if team has a manager
                if ($team->manager_id) {
                    $thread = StaffDmThread::firstOrCreate(
                        [
                            'manager_id' => $team->manager_id,
                            'agent_id'   => $aid,
                        ],
                        [
                            'is_active' => true,
                        ]
                    );

                    $thread->update(['is_active' => true]);
                }
            }
        });

        return redirect()
            ->route('superadmin.teams.index')
            ->with('success', 'Team created.');
    }

    public function edit(StaffTeam $team)
    {
        $team->load(['manager', 'activeMembers.agent']);

        $managers = $this->managers()->get();
        $agents = $this->agents()->get();
        $selected = $team->activeMembers->pluck('agent_id')->all();

        $agentTeamMap = $this->agentActiveTeamMap();
        $managerTeamMap = $this->managerActiveTeamMap();

        return view('superadmin.teams.edit', compact(
            'team',
            'managers',
            'agents',
            'selected',
            'agentTeamMap',
            'managerTeamMap'
        ));
    }

    public function update(Request $r, StaffTeam $team)
    {
        $r->validate([
            'name' => ['required', 'string', 'max:120'],
            'manager_id' => [
                'nullable',
                'exists:users,id',
                Rule::unique('staff_teams', 'manager_id')
                    ->ignore($team->id)
                    ->where(fn ($q) => $q->where('is_active', true)->whereNull('deleted_at')),
            ],
            'agent_ids' => ['array'],
            'agent_ids.*' => ['integer', 'exists:users,id'],
        ]);

        DB::transaction(function () use ($r, $team) {
            $oldManagerId = $team->manager_id ? (int) $team->manager_id : null;
            $newManagerId = $r->filled('manager_id') ? (int) $r->manager_id : null;

            $team->update([
                'name'       => $r->name,
                'manager_id' => $newManagerId,
            ]);

            $newAgentIds = array_values(array_unique($r->input('agent_ids', [])));
            $currentAgentIds = $team->activeMembers()->pluck('agent_id')->all();

            // remove agents
            $toRemove = array_diff($currentAgentIds, $newAgentIds);
            if (!empty($toRemove)) {
                StaffTeamMember::where('team_id', $team->id)
                    ->whereIn('agent_id', $toRemove)
                    ->whereNull('end_at')
                    ->update(['end_at' => now()]);

                if ($oldManagerId) {
                    StaffDmThread::where('manager_id', $oldManagerId)
                        ->whereIn('agent_id', $toRemove)
                        ->update(['is_active' => false]);
                }
            }

            // add agents
            $toAdd = array_diff($newAgentIds, $currentAgentIds);
            foreach ($toAdd as $aid) {
                StaffTeamMember::where('agent_id', $aid)
                    ->whereNull('end_at')
                    ->update(['end_at' => now()]);

                StaffTeamMember::create([
                    'team_id'  => $team->id,
                    'agent_id' => $aid,
                    'start_at' => now(),
                ]);

                StaffDmThread::where('agent_id', $aid)
                    ->where('is_active', true)
                    ->update(['is_active' => false]);

                if ($newManagerId) {
                    $thread = StaffDmThread::firstOrCreate(
                        [
                            'manager_id' => $newManagerId,
                            'agent_id'   => $aid,
                        ],
                        [
                            'is_active' => true,
                        ]
                    );

                    $thread->update(['is_active' => true]);
                }
            }

            // manager changed (including manager removed)
            if ($oldManagerId !== $newManagerId) {
                $stillAssignedAgentIds = StaffTeamMember::where('team_id', $team->id)
                    ->whereNull('end_at')
                    ->pluck('agent_id')
                    ->all();

                if ($oldManagerId && !empty($stillAssignedAgentIds)) {
                    StaffDmThread::where('manager_id', $oldManagerId)
                        ->whereIn('agent_id', $stillAssignedAgentIds)
                        ->update(['is_active' => false]);
                }

                if ($newManagerId && !empty($stillAssignedAgentIds)) {
                    foreach ($stillAssignedAgentIds as $aid) {
                        $thread = StaffDmThread::firstOrCreate(
                            [
                                'manager_id' => $newManagerId,
                                'agent_id'   => $aid,
                            ],
                            [
                                'is_active' => true,
                            ]
                        );

                        $thread->update(['is_active' => true]);
                    }
                }
            }
        });

        return redirect()
            ->route('superadmin.teams.index')
            ->with('success', 'Team updated.');
    }

    public function destroy(StaffTeam $team)
    {
        DB::transaction(function () use ($team) {
            $agentIds = $team->activeMembers()->pluck('agent_id')->all();

            if (!empty($agentIds)) {
                StaffTeamMember::where('team_id', $team->id)
                    ->whereNull('end_at')
                    ->update(['end_at' => now()]);

                if ($team->manager_id) {
                    StaffDmThread::where('manager_id', $team->manager_id)
                        ->whereIn('agent_id', $agentIds)
                        ->update(['is_active' => false]);
                }
            }

            $team->update(['is_active' => false]);
            $team->delete();
        });

        return redirect()
            ->route('superadmin.teams.index')
            ->with('success', 'Team deleted (soft). Agents unassigned and threads deactivated.');
    }

    public function restore($id)
    {
        $team = StaffTeam::withTrashed()->findOrFail($id);

        $team->restore();
        $team->update(['is_active' => true]);

        return redirect()
            ->route('superadmin.teams.index')
            ->with('success', 'Team restored.');
    }
}
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SupportQuestion;
use App\Models\SupportQuestionMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SupportQuestionController extends Controller
{
    private function staffOrFail()
    {
        $u = auth()->user();
        abort_unless($u && in_array($u->role, ['admin','manager','superadmin'], true), 403);
        return $u;
    }

    private function isAdmin($u): bool
    {
        return in_array($u->role, ['admin','superadmin'], true);
    }

    private function isManager($u): bool
    {
        return in_array($u->role, ['manager','superadmin'], true);
    }

    /* =========================
     * INDEX
     * ======================= */
    public function index(Request $request)
{
    $staff  = $this->staffOrFail();

    $q      = $request->q;
    $mine   = $request->mine ?? '0';
    $status = $request->status ?? 'open';

    $items = SupportQuestion::query()
        ->with(['askedBy','assignedManager','messages'])
        ->when($q, function($qb) use ($q){
            $qb->where(function($w) use ($q){
                $w->where('question','like',"%{$q}%")
                  ->orWhere('id',$q);
            });
        })

        // ✅ role-aware "mine"
        ->when($mine === '1', function ($qb) use ($staff) {
            if ($this->isManager($staff) && !$this->isAdmin($staff)) {
                // manager "mine" = taken by me
                $qb->where('assigned_manager_id', $staff->id);
            } else {
                // admin "mine" = asked by me
                $qb->where('asked_by_admin_id', $staff->id);
            }
        })

        // ✅ UI answered => DB closed
        ->when($status !== 'all', function ($qb) use ($status) {
            if ($status === 'answered') {
                $qb->where('status', 'closed');
            } else {
                $qb->where('status', $status);
            }
        })

        ->orderByDesc('updated_at')
        ->paginate(15)
        ->appends($request->query());

    return view('admin.support-qa.index', compact('items','q','mine','status'));
}

    /* =========================
     * CREATE / STORE (Admins only)
     * ======================= */
    public function create()
    {
        $staff = $this->staffOrFail();
        abort_unless($this->isAdmin($staff), 403);

        return view('admin.support-qa.create');
    }

    public function store(Request $request)
    {
        $staff = $this->staffOrFail();
        abort_unless($this->isAdmin($staff), 403);

        $data = $request->validate([
            'question' => ['required','string','max:20000'],
        ]);

        return DB::transaction(function () use ($data, $staff) {
            $q = SupportQuestion::create([
                'asked_by_admin_id'   => $staff->id,
                'assigned_manager_id' => null,   // not taken yet
                'question'            => $data['question'],
                'status'              => 'open',
                'answered_at'         => null,
                'closed_at'           => null,
            ]);

            // Keep original question as first message (optional but useful for timeline)
            SupportQuestionMessage::create([
                'support_question_id' => $q->id,
                'sender_id'           => $staff->id,
                'sender_role'         => $staff->role,
                'body'                => $data['question'],
                'type'                => 'message',
            ]);

            return redirect()->route('admin.support.questions.show', $q)
                ->with('ok','Question posted.');
        });
    }

    /* =========================
     * SHOW
     * ======================= */
    public function show(SupportQuestion $question)
    {
        $staff = $this->staffOrFail();

        $question->load(['askedBy','assignedManager','messages.sender']);

        $isOwnerAdmin = (int) $question->asked_by_admin_id === (int) auth()->id();
        $isTaken      = !empty($question->assigned_manager_id);
        $isMyTaken    = $isTaken && (int) $question->assigned_manager_id === (int) auth()->id();

        // ONLY the taken manager can post, and ONLY while status = taken
        $canMessage = $this->isManager($staff)
            && $isMyTaken
            && $question->status === 'taken';

        // Any manager can take IF open and not already taken
        $canTake = $this->isManager($staff)
            && !$isTaken
            && $question->status === 'open';

        // No acknowledge step in this flow
        $canAcknowledge = false;

        return view('admin.support-qa.show', compact(
            'question','canTake','canMessage','canAcknowledge','isOwnerAdmin','isTaken','isMyTaken'
        ));
    }

    /* =========================
     * TAKE (Manager locks it)
     * ======================= */
   public function take(Request $request, SupportQuestion $question)
{
    $staff = $this->staffOrFail();
    abort_unless($this->isManager($staff), 403);

    DB::transaction(function () use ($question, $staff) {
        $fresh = SupportQuestion::lockForUpdate()->findOrFail($question->id);

        abort_if(!empty($fresh->assigned_manager_id) || $fresh->status !== 'open', 403);

        $fresh->update([
            'assigned_manager_id' => $staff->id,
            'status'              => 'taken',
            'taken_at'            => now(),
        ]);

        $managerName = $staff->username ?? 'Manager';

        SupportQuestionMessage::create([
            'support_question_id' => $fresh->id,
            'sender_id'           => $staff->id,
            'sender_role'         => $staff->role,
            'type'                => 'system',
            'body'                => "{$managerName} Took This Question.",
            'meta'                => [
                'event' => 'taken',
                'manager_username' => $managerName,
            ],
        ]);

        $fresh->touch();
    });

    $managerName = $staff->username ?? 'Manager';
    return back()->with('ok', "{$managerName} Took This Question.");
}


    /* =========================
     * MESSAGE (Manager answers once -> auto close)
     * ======================= */
    public function storeMessage(Request $request, SupportQuestion $question)
    {
        $staff = $this->staffOrFail();
        abort_unless($this->isManager($staff), 403);

        $data = $request->validate([
            'body' => ['required','string','max:20000'],
        ]);

        DB::transaction(function () use ($question, $staff, $data) {
            // lock row so two submits can't double-answer
            $fresh = SupportQuestion::lockForUpdate()->findOrFail($question->id);

            $isTaken   = !empty($fresh->assigned_manager_id);
            $isMyTaken = $isTaken && (int) $fresh->assigned_manager_id === (int) $staff->id;

            // must be taken by me
            abort_unless($isTaken && $isMyTaken, 403);

            // only allow answering while taken
            abort_unless($fresh->status === 'taken', 403);

            SupportQuestionMessage::create([
                'support_question_id' => $fresh->id,
                'sender_id'           => $staff->id,
                'sender_role'         => $staff->role,
                'body'                => $data['body'],
                'type'                => 'message',
            ]);

            // immediately close after manager answers
            $fresh->update([
                'status'      => 'closed',
                'answered_at' => now(),
                'closed_at'   => now(),
            ]);

           $managerName = $staff->username ?? 'Manager';

SupportQuestionMessage::create([
    'support_question_id' => $fresh->id,
    'sender_id'           => $staff->id,
    'sender_role'         => $staff->role,
    'type'                => 'system',
    'body'                => "{$managerName} Answered And Closed This Question.",
    'meta'                => ['event' => 'closed_after_answer'],
]);


            $fresh->touch();
        });

        return back()->with('ok','Answered And Closed.');
    }

    /* =========================
     * ACKNOWLEDGE (REMOVED in this flow)
     * ======================= */
    // DELETE route + button + this method completely
}

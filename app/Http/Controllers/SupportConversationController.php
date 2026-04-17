<?php

namespace App\Http\Controllers;

use App\Models\SupportConversation;
use App\Models\SupportMessage;
use App\Models\SupportConversationRating;
use App\Services\SupportAssignmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SupportConversationController extends Controller
{
    /**
     * Resolve current active support scope role from session.
     * Must match your role switcher logic.
     */
    protected function activeScopeRole(Request $request): string
    {
        $user = $request->user();

        $scopeRole = session('active_role') ?: ($user->role ?? 'client');
        $scopeRole = in_array($scopeRole, ['client', 'coach'], true) ? $scopeRole : 'client';

        return $scopeRole;
    }

    /**
     * ✅ OPTION A: assign a STAFF owner (admin OR manager) if missing.
     * - only assigns if conversation is open
     * - max 3 active chats per staff enforced inside SupportAssignmentService
     * - writes system message:
     *   - meta.event = agent_assigned  (neutral for customer)
     */
    protected function tryAssignStaffIfNeeded(SupportConversation $conversation, SupportAssignmentService $assigner): void
    {
        if ($conversation->status !== 'open') return;
        if (!empty($conversation->assigned_staff_id)) return;

        DB::transaction(function () use ($conversation, $assigner) {

            $locked = SupportConversation::where('id', $conversation->id)
                ->lockForUpdate()
                ->first();

            if (!$locked) return;
            if ($locked->status !== 'open') return;
            if (!empty($locked->assigned_staff_id)) return;

          $pick = $assigner->pickStaff(); // MUST return username too
if (!$pick || empty($pick['id']) || empty($pick['role'])) return;

$staffId   = (int) $pick['id'];
$staffRole = (string) $pick['role']; // admin|manager

// ✅ username ONLY (no name)
$staffUsername = (string) ($pick['username'] ?? 'support');

$locked->forceFill([
  'assigned_staff_id'   => $staffId,
  'assigned_staff_role' => $staffRole,
  'sla_started_at'      => now(),
])->save();

SupportMessage::create([
  'support_conversation_id' => $locked->id,
  'sender_id'               => $staffId,
  'sender_type'             => 'system',
  'body'                    => '',
  'type'                    => 'system',
  'meta'                    => [
    'event'          => 'agent_assigned',
    'agent_username' => $staffUsername,
    'agent_id'       => $staffId,
    'agent_role'     => $staffRole,
  ],
]);

        });
    }

    /**
     * Get the "current thread" for this user + scope.
     * Prefers open conversation's thread_id; else latest conversation thread_id.
     */
    protected function getActiveThreadIdForScope(int $userId, string $scopeRole): ?string
    {
        $open = SupportConversation::where('user_id', $userId)
            ->where('scope_role', $scopeRole)
            ->where('status', 'open')
            ->orderByDesc('id')
            ->first();

        if ($open?->thread_id) return (string) $open->thread_id;

        $latest = SupportConversation::where('user_id', $userId)
            ->where('scope_role', $scopeRole)
            ->orderByDesc('id')
            ->first();

        return $latest?->thread_id ? (string) $latest->thread_id : null;
    }

    public function index(Request $request, SupportAssignmentService $assigner)
    {
        $user = $request->user();
        $scopeRole = $this->activeScopeRole($request);

        // Rating gate: if there is any resolved conversation in this scope with rating_required=1
        $conversationNeedingRating = SupportConversation::where('user_id', $user->id)
            ->where('scope_role', $scopeRole)
            ->where('status', 'resolved')
            ->where('rating_required', 1)
            ->orderByDesc('id')
            ->first();

        $threadId = null;

        if ($conversationNeedingRating && $conversationNeedingRating->thread_id) {
            $threadId = (string) $conversationNeedingRating->thread_id;
        } else {
            $threadId = $this->getActiveThreadIdForScope($user->id, $scopeRole);
        }

        if (!$threadId) {
            return view('support.index', [
                'conversation'   => null,
                'history'        => collect(),
                'events'         => collect(),
                'hasAdminReply'  => false,
                'canRateNow'     => false,
                'scopeRole'      => $scopeRole,
                'threadId'       => null,
            ]);
        }

        $history = SupportConversation::where('user_id', $user->id)
            ->where('scope_role', $scopeRole)
            ->where('thread_id', $threadId)
            ->orderBy('id', 'asc')
            ->get();

        $conversation = $history->where('status', 'open')->sortByDesc('id')->first()
            ?: $history->sortByDesc('id')->first();

        // ✅ If open, assign staff owner (admin OR manager)
        if ($conversation && $conversation->status === 'open') {
            $this->tryAssignStaffIfNeeded($conversation, $assigner);
            $conversation->refresh();
        }

        // If you have relations, you can keep these
        $conversation?->load(['assignedAdmin', 'manager']);

        $conversationIds = $history->pluck('id')->all();

        $allMessages = SupportMessage::query()
            ->with('sender')
            ->whereIn('support_conversation_id', $conversationIds)
            ->orderBy('id', 'asc')
            ->get();

        $allRatings = SupportConversationRating::query()
            ->with('ratedAdmin')
            ->whereIn('support_conversation_id', $conversationIds)
            ->orderBy('id', 'asc')
            ->get();

        $events = collect();

        foreach ($allMessages as $msg) {
            $type  = $msg->type ?? 'message';
            $event = (string) data_get($msg->meta, 'event');

            if ($type === 'system') {

                // ✅ NEW: unified neutral agent assignment event
                if ($event === 'agent_assigned') {
                    $events->push((object)[
                        'type' => 'agent_assigned',
                        'at'   => $msg->created_at,
                        'model'=> (object)[
                           'username' => data_get($msg->meta, 'agent_username', 'support'),

                        ],
                    ]);
                    continue;
                }

                // ✅ Backward compatibility: old events become agent_assigned display
                if (in_array($event, ['admin_assign','manager_assigned'], true)) {
                  $username = data_get($msg->meta, 'admin_username')
  ?? data_get($msg->meta, 'manager_username')
  ?? 'support';


                    $events->push((object)[
                        'type' => 'agent_assigned',
                        'at'   => $msg->created_at,
                        'model'=> (object)[ 'username' => $username ],
                    ]);
                    continue;
                }

                if ($event === 'manager_requested') {
                    $events->push((object)[
                        'type' => 'manager_requested',
                        'at'   => $msg->created_at,
                        'model'=> (object)[
                            'username' => data_get($msg->meta, 'agent_username', 'support'),

                        ],
                    ]);
                    continue;
                }

                if ($event === 'manager_joined') {
                    $events->push((object)[
                        'type' => 'manager_joined',
                        'at'   => $msg->created_at,
                        'model'=> (object)[
                           'username' => data_get($msg->meta, 'manager_username', 'manager'),

                        ],
                    ]);
                    continue;
                }

                if ($event === 'manager_ended') {
                    $events->push((object)[
                        'type' => 'manager_ended',
                        'at'   => $msg->created_at,
                        'model'=> (object)[
                           'username' => data_get($msg->meta, 'manager_username', 'manager'),

                        ],
                    ]);
                    continue;
                }

                $events->push((object)[
                    'type' => 'system',
                    'at'   => $msg->created_at,
                    'model'=> (object)[ 'text' => __('System update') ],
                ]);
                continue;
            }

            $events->push((object)[
                'type'  => 'message',
                'at'    => $msg->created_at,
                'model' => $msg,
            ]);
        }

        foreach ($allRatings as $rating) {
            $events->push((object)[
                'type'  => 'rating',
                'at'    => $rating->created_at,
                'model' => $rating,
            ]);
        }

        $events = $events->sortBy('at')->values();

        // admin OR manager reply counts as staff reply
        $hasAdminReply = $allMessages->contains(fn ($m) => in_array($m->sender_type, ['admin','manager'], true));

        $canRateNow = false;
        if ($conversationNeedingRating) {
            $alreadyRated = SupportConversationRating::where('support_conversation_id', $conversationNeedingRating->id)
                ->where('user_id', $user->id)
                ->exists();

            $canRateNow = !$alreadyRated;
        }

        return view('support.index', [
            'conversation'   => $conversation,
            'history'        => $history,
            'events'         => $events,
            'hasAdminReply'  => $hasAdminReply,
            'canRateNow'     => $canRateNow,
            'scopeRole'      => $scopeRole,
            'threadId'       => $threadId,
        ]);
    }

    public function storeMessage(Request $request, SupportAssignmentService $assigner)
    {
        $user = $request->user();
        $scopeRole = $this->activeScopeRole($request);

        $baseData = $request->validate([
            'conversation_id' => ['nullable', 'integer'],
            'thread_id'       => ['nullable', 'string'],
            'widget'          => ['nullable'],
            'body'            => ['required', 'string', 'max:5000'],
        ]);

        $needsRating = SupportConversation::where('user_id', $user->id)
            ->where('scope_role', $scopeRole)
            ->where('status', 'resolved')
            ->where('rating_required', 1)
            ->orderByDesc('id')
            ->first();

        if ($needsRating) {
            return redirect()->route('support.conversation.index')
                ->with('error', __('Please rate the resolved conversation before sending another message.'));
        }

        $conversation = null;
        $threadId = !empty($baseData['thread_id']) ? (string) $baseData['thread_id'] : null;

        if (!empty($baseData['conversation_id'])) {
            $existing = SupportConversation::where('id', $baseData['conversation_id'])
                ->where('user_id', $user->id)
                ->where('scope_role', $scopeRole)
                ->first();

            if ($existing) {
                $threadId = $threadId ?: (string) ($existing->thread_id ?: '');
                if ($existing->status === 'open') {
                    $conversation = $existing;
                }
            }
        }

        if (!$conversation) {
            if ($threadId) {
                $latestInThread = SupportConversation::where('user_id', $user->id)
                    ->where('scope_role', $scopeRole)
                    ->where('thread_id', $threadId)
                    ->orderByDesc('id')
                    ->first();

                if ($latestInThread && $latestInThread->status === 'open') {
                    $conversation = $latestInThread;
                } else {
                    $conversation = SupportConversation::create([
                        'user_id' => $user->id,
                        'scope_role' => $scopeRole,
                        'thread_id' => $threadId ?: (string) Str::uuid(),
                        'user_type' => $user->role ?? null,
                        'status' => 'open',
                        'last_message_at' => null,

                        'assigned_staff_id' => null,
                        'assigned_staff_role' => null,

                        'manager_id' => null,
                        'manager_requested_at'=> null,
                        'manager_requested_by'=> null,
                        'manager_joined_at' => null,
                        'manager_ended_at' => null,

                        'closed_at' => null,
                        'closed_by' => null,
                        'closed_by_role' => null,
                        'rating_required' => 0,
                        'auto_closed' => 0,
                    ]);
                }
            } else {
                $conversation = SupportConversation::create([
                    'user_id' => $user->id,
                    'scope_role' => $scopeRole,
                    'thread_id' => (string) Str::uuid(),
                    'user_type' => $user->role ?? null,
                    'status' => 'open',
                    'last_message_at' => null,

                    'assigned_staff_id' => null,
                    'assigned_staff_role' => null,

                    'manager_id' => null,
                    'manager_requested_at'=> null,
                    'manager_requested_by'=> null,
                    'manager_joined_at' => null,
                    'manager_ended_at' => null,

                    'closed_at' => null,
                    'closed_by' => null,
                    'closed_by_role' => null,
                    'rating_required' => 0,
                    'auto_closed' => 0,
                ]);
            }
        }

        SupportMessage::create([
            'support_conversation_id' => $conversation->id,
            'sender_id'               => $user->id,
            'sender_type'             => $scopeRole,
            'body'                    => $baseData['body'],
            'type'                    => 'message',
            'meta'                    => null,
        ]);

        $conversation->update(['last_message_at' => now()]);

        // ✅ assign staff after message exists
        $this->tryAssignStaffIfNeeded($conversation, $assigner);

        return redirect()->route('support.conversation.index')
            ->with('ok', __('Your Message Has Been Sent.'));
    }

    public function latest(Request $request)
    {
        $user = $request->user();
        $scopeRole = $this->activeScopeRole($request);

        $data = $request->validate([
            'thread_id'       => ['nullable', 'string'],
            'conversation_id' => ['nullable', 'integer'],
            'after_id'        => ['nullable', 'integer'],
        ]);

        $threadId = $data['thread_id'] ?? null;

        if (!$threadId && !empty($data['conversation_id'])) {
            $c = SupportConversation::where('id', $data['conversation_id'])
                ->where('user_id', $user->id)
                ->where('scope_role', $scopeRole)
                ->first();

            $threadId = $c?->thread_id ? (string) $c->thread_id : null;
        }

        if (!$threadId) {
            return response()->json(['ok'=>true,'html'=>'','last'=>$data['after_id'] ?? null]);
        }

        $convIds = SupportConversation::where('user_id', $user->id)
            ->where('scope_role', $scopeRole)
            ->where('thread_id', $threadId)
            ->pluck('id')
            ->all();

        if (empty($convIds)) {
            return response()->json(['ok'=>true,'html'=>'','last'=>$data['after_id'] ?? null]);
        }

        $q = SupportMessage::query()
            ->with('sender')
            ->whereIn('support_conversation_id', $convIds)
            ->orderBy('id', 'asc');

        if (!empty($data['after_id'])) {
            $q->where('id', '>', (int)$data['after_id']);
        }

        $messages = $q->get();

        if ($messages->isEmpty()) {
            return response()->json(['ok'=>true,'html'=>'','last'=>$data['after_id'] ?? null]);
        }

        $html = '';

        foreach ($messages as $msg) {
            $type  = $msg->type ?? 'message';
            $event = (string) data_get($msg->meta, 'event');

            if ($type === 'system') {

                // ✅ NEW unified agent assigned
                if ($event === 'agent_assigned') {
                   $username = (string) data_get($msg->meta, 'agent_username', 'support');


                    $html .= '
                      <div class="zv-chat-system-row">
                        <div class="system-pill text-capitalize">
                          <i class="bi bi-person-workspace me-1"></i>
                          '.e(__('This conversation is handled by')).'
                         <strong>Agent: '.$username.'</strong>
                        </div>
                      </div>
                    ';
                    continue;
                }

                // ✅ Backward compatibility: show old events as agent assigned
              if (in_array($event, ['admin_assign','manager_assigned'], true)) {
  $username = (string) (data_get($msg->meta, 'admin_username')
    ?? data_get($msg->meta, 'manager_username')
    ?? 'support');

  $html .= '
    <div class="zv-chat-system-row">
      <div class="system-pill text-capitalize">
        <i class="bi bi-person-workspace me-1"></i>
        '.e(__('This conversation is handled by')).'
        <strong>Agent: '.e($username).'</strong>
      </div>
    </div>
  ';
  continue;
}


                if ($event === 'manager_requested') {
                    $html .= '
                      <div class="zv-chat-system-row">
                        <div class="system-pill text-capitalize">
                          <i class="bi bi-shield-exclamation me-1"></i>
                          '.e(__('We’re connecting you to a senior manager.')).'
                        </div>
                      </div>
                    ';
                    continue;
                }

                if ($event === 'manager_joined') {
                    $username = (string) data_get($msg->meta, 'manager_username', 'manager');
                    $html .= '
                      <div class="zv-chat-system-row">
                        <div class="system-pill text-capitalize">
                          <i class="bi bi-shield-check me-1"></i>
                          '.e(__('A senior manager has joined this conversation.')).'
                        <strong>'.$username.'</strong>
                        </div>
                      </div>
                    ';
                    continue;
                }

                if ($event === 'manager_ended') {
                    $html .= '
                      <div class="zv-chat-system-row">
                        <div class="system-pill text-capitalize">
                          <i class="bi bi-shield-x me-1"></i>
                          '.e(__('The manager session has concluded.')).'
                        </div>
                      </div>
                    ';
                    continue;
                }

                $html .= '
                  <div class="zv-chat-system-row">
                    <div class="system-pill">
                      <i class="bi bi-info-circle me-1"></i>
                      '.e(__('System update')).'
                    </div>
                  </div>
                ';
                continue;
            }

            // message
            $html .= view('support._message', ['msg' => $msg])->render();
        }

        return response()->json([
            'ok'   => true,
            'html' => $html,
            'last' => $messages->last()->id,
        ]);
    }

    public function rate(Request $request, SupportConversation $conversation)
    {
        $user = $request->user();
        $scopeRole = $this->activeScopeRole($request);

        if ((int)$conversation->user_id !== (int)$user->id) abort(403);
        if (($conversation->scope_role ?? null) !== $scopeRole) abort(403);

        if ($conversation->status !== 'resolved' || !$conversation->rating_required) {
            return back()->with('error', __('This Conversation Does Not Require Rating.'));
        }

        $data = $request->validate([
            'stars'    => ['required', 'integer', 'min:1', 'max:5'],
            'feedback' => ['nullable', 'string', 'max:5000'],
        ]);

        $staffId = $conversation->closed_by;
        if (!$staffId) {
            return back()->with('error', __('Unable To Rate: No Support Staff Was Assigned.'));
        }

        $alreadyRated = SupportConversationRating::where('support_conversation_id', $conversation->id)
            ->where('user_id', $user->id)
            ->exists();

        if ($alreadyRated) {
            return back()->with('ok', __('You Already Rated This Conversation.'));
        }

        SupportConversationRating::create([
            'support_conversation_id' => $conversation->id,
            'user_id'                 => $user->id,
            'admin_id'                => $staffId, // legacy column, stores staff id
            'stars'                   => $data['stars'],
            'feedback'                => $data['feedback'] ?? null,
        ]);

        $conversation->update(['rating_required' => 0]);

        return back()->with('ok', __('Thanks For Your Rating!'));
    }
}

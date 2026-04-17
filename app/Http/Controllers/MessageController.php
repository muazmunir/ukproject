<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Service;
use Illuminate\Http\Request;
use App\Support\AnalyticsLogger;

class MessageController extends Controller
{
 public function index(Request $request)
{
    abort_unless($this->activeRole() === 'client', 403);

    $user = $request->user();

    $conversations = Conversation::with(['service', 'coach', 'client'])
        ->where('client_id', $user->id)
        ->whereHas('messages')
        ->orderByDesc('last_message_at')
        ->orderByDesc('updated_at')
        ->get();

    return view('client.messages', compact('conversations'));
}


public function clientShow(Conversation $conversation, Request $request)
{
    abort_unless($this->activeRole() === 'client', 403);

    $user = $request->user();
    abort_unless((int)$conversation->client_id === (int)$user->id, 403);

    $conversation->load(['service','coach','client','messages.sender','messages.service']);

    // mark read
    $conversation->messages()
        ->whereNull('read_at')
        ->where('sender_id', '!=', $user->id)
        ->update(['read_at' => now()]);

    $conversations = Conversation::with(['service','coach','client'])
        ->where('client_id', $user->id)
        ->whereHas('messages')
        ->orderByDesc('last_message_at')
        ->get();

    return view('messages.show', compact('conversation','conversations'));
}


public function coachShow(Conversation $conversation, Request $request)
{
    abort_unless($this->activeRole() === 'coach', 403);

    $user = $request->user();
    abort_unless((int)$conversation->coach_id === (int)$user->id, 403);

    $conversation->load(['service','coach','client','messages.sender','messages.service']);

    $conversation->messages()
        ->whereNull('read_at')
        ->where('sender_id', '!=', $user->id)
        ->update(['read_at' => now()]);

    $conversations = Conversation::with(['service','coach','client'])
        ->where('coach_id', $user->id)
        ->whereHas('messages')
        ->orderByDesc('last_message_at')
        ->get();

    return view('coach.messages.show', compact('conversation','conversations'));
}



private function activeRole(): string
{
    $active = strtolower((string) session('active_role', 'client'));
    return in_array($active, ['client', 'coach'], true) ? $active : 'client';
}



   public function startFromService(Service $service, Request $request)
{
    // ✅ must be in client mode to start a chat from service
    abort_unless($this->activeRole() === 'client', 403);

    $user  = $request->user();
    $coach = $service->coach;

    abort_unless($coach, 404);
    abort_if((int)$coach->id === (int)$user->id, 403); // prevent self chat

    $conversation = Conversation::firstOrCreate(
        [
            'coach_id'  => $coach->id,
            'client_id' => $user->id,
        ],
        [
            'service_id'      => $service->id,
            'last_message_at' => now(),
        ]
    );

    if ($conversation->service_id !== $service->id) {
        $conversation->update(['service_id' => $service->id]);
    }

    // optional context message (fine to keep)
    $conversation->messages()->create([
        'sender_id'    => $user->id,
        'sender_role'  => 'client', // since startFromService is client-only
        'service_id'   => $service->id,
        'body'         => '__SERVICE_CONTEXT__',
    ]);

    $conversation->update(['last_message_at' => now()]);

    // ✅ always go to client chat UI
    return redirect()->route('client.messages.show', ['conversation' => $conversation->id]);
}


 public function show(Conversation $conversation, Request $request)
{
    $user = $request->user();
    $role = $this->activeRole();

    $allowed = $role === 'coach'
        ? ((int)$conversation->coach_id === (int)$user->id)
        : ((int)$conversation->client_id === (int)$user->id);

    abort_unless($allowed, 403);


    if ($role === 'client') {
    AnalyticsLogger::log($request, (int) $conversation->coach_id, 'enquiry_open', 600); // 10 min dedupe
}
    // ✅ Load full conversation (both sides)
    $conversation->load([
        'service',
        'coach',
        'client',
        'messages.sender',
        'messages.service',
    ]);

    // ✅ Mark messages from the OTHER person as read (no sender_role filter)
    $conversation->messages()
        ->whereNull('read_at')
        ->where('sender_id', '!=', $user->id)
        ->update(['read_at' => now()]);

    // Sidebar: conversations for this role (ONLY conversation ownership)
    $conversations = Conversation::with(['service', 'coach', 'client'])
        ->when($role === 'coach',
            fn ($q) => $q->where('coach_id', $user->id),
            fn ($q) => $q->where('client_id', $user->id),
        )
        ->whereHas('messages')
        ->orderByDesc('last_message_at')
        ->orderByDesc('updated_at')
        ->get();

    $view = $role === 'coach' ? 'coach.messages.show' : 'messages.show';

    return view($view, compact('conversation', 'conversations'));
}





public function coachIndex(Request $request)
{
    $user = $request->user();

    // ✅ If user is not in coach mode, don’t show coach inbox
    abort_unless($this->activeRole() === 'coach', 403);

    $conversations = Conversation::with(['service', 'coach', 'client'])
        ->where('coach_id', $user->id)
        ->whereHas('messages')
        ->orderByDesc('last_message_at')
        ->orderByDesc('updated_at')
        ->get();

    // show same view; no forced redirect
    return view('coach.messages.show', [
        'conversations' => $conversations,
        'conversation'  => null, // no selected conversation until user clicks
    ]);
}


public function store(Conversation $conversation, Request $request)
{
    $user = $request->user();
    $role = $this->activeRole();

    $allowed = $role === 'coach'
        ? ((int)$conversation->coach_id === (int)$user->id)
        : ((int)$conversation->client_id === (int)$user->id);

    abort_unless($allowed, 403);

    $data = $request->validate([
        'body' => ['required', 'string', 'max:4000'],
    ]);

    $conversation->messages()->create([
        'sender_id'   => $user->id,
        'sender_role' => $role,     // ✅ THIS is the key
        'body'        => $data['body'],
    ]);

    if ($role === 'client') {
    AnalyticsLogger::log($request, (int) $conversation->coach_id, 'enquiry_message', 60); // 1 min dedupe
}

    $conversation->update(['last_message_at' => now()]);
return $role === 'coach'
    ? redirect()->route('coach.messages.show', $conversation)
    : redirect()->route('client.messages.show', $conversation);

}

}

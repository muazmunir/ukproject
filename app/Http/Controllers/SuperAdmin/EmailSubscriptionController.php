<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Mail\NewsletterBlast;
use App\Models\NewsletterSubscriber;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Jobs\SendNewsletterChunk;


class EmailSubscriptionController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $status = $request->query('status'); // active | inactive | null

        $subscribersQuery = NewsletterSubscriber::query()
            ->when($q, fn ($query) => $query->where('email', 'like', "%{$q}%"))
            ->when($status === 'active', fn ($query) => $query->where('is_active', true))
            ->when($status === 'inactive', fn ($query) => $query->where('is_active', false))
            ->latest();

        $subscribers = $subscribersQuery->paginate(15)->withQueryString();

        $total = NewsletterSubscriber::count();
        $active = NewsletterSubscriber::where('is_active', true)->count();
        $inactive = $total - $active;

        return view('superadmin.email-subscriptions.index', compact(
            'subscribers', 'q', 'status', 'total', 'active', 'inactive'
        ));
    }

    public function toggle(NewsletterSubscriber $subscriber)
    {
        $subscriber->update(['is_active' => !$subscriber->is_active]);

        return back()->with('success', 'Subscriber status updated.');
    }

    public function destroy(NewsletterSubscriber $subscriber)
    {
        $subscriber->delete();

        return back()->with('success', 'Subscriber Deleted.');
    }
    public function compose()
{
    return view('superadmin.email-subscriptions.compose');
}

public function sendToAll(Request $request)
{
    $data = $request->validate([
        'subject' => ['required','string','max:190'],
        'message' => ['required','string'],
        'attachments' => ['nullable','array'],
        'attachments.*' => ['file','max:5120'], // 5MB each
    ]);

    // ✅ Store attachments once
    $attachments = [];
    if ($request->hasFile('attachments')) {
        foreach ($request->file('attachments') as $file) {
            $attachments[] = [
                'path' => $file->store('newsletter_attachments'),
                'name' => $file->getClientOriginalName(),
            ];
        }
    }

    // ✅ only active subscribers
    $emailsQuery = NewsletterSubscriber::where('is_active', true)->select('email');

    // ✅ chunk recipients into jobs (important for huge lists)
    $chunkSize = 1000; // tune: 500, 1000, 2000 depending on server/provider
    $emailsQuery->orderBy('id')->chunk($chunkSize, function ($rows) use ($data, $attachments) {
        $emails = $rows->pluck('email')->all();
        SendNewsletterChunk::dispatch($emails, $data['subject'], $data['message'], $attachments)
            ->onQueue('newsletters');
    });

    return back()->with('success', 'Newsletter Queued. It Will Be Delivered In Background.');
}

}

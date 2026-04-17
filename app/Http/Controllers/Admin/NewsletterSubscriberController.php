<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NewsletterSubscriber;
use Illuminate\Http\Request;

class NewsletterSubscriberController extends Controller
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

        return view('admin.newsletter.subscribers.index', compact(
            'subscribers', 'q', 'status', 'total', 'active', 'inactive'
        ));
    }

    public function toggle(NewsletterSubscriber $subscriber)
    {
        $subscriber->update(['is_active' => !$subscriber->is_active]);

        return back()->with('success', 'Subscriber Status Updated.');
    }

    public function destroy(NewsletterSubscriber $subscriber)
    {
        $subscriber->delete();
        return back()->with('success', 'Subscriber Deleted.');
    }
}


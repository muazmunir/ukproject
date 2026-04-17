<?php
namespace App\Http\Controllers;

use App\Models\NewsletterSubscriber;
use Illuminate\Http\Request;

class NewsletterController extends Controller
{
    public function subscribe(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email:rfc,dns', 'max:255'],
        ]);

        // idempotent subscribe (won't create duplicates)
        $subscriber = NewsletterSubscriber::firstOrCreate(
            ['email' => strtolower(trim($validated['email']))],
            ['is_active' => true, 'subscribed_at' => now()]
        );

        // If already existed but was inactive, re-activate
        if (!$subscriber->is_active) {
            $subscriber->update([
                'is_active' => true,
                'subscribed_at' => now(),
            ]);
        }

        return back()->with('newsletter_success', 'Subscribed Successfully! 🎉');
    }
}

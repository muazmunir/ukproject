<?php

namespace App\Http\Controllers\Coach;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\ServiceFaq;
use App\Models\ServicePackage;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use App\Mail\ServiceSubmittedMail;
use App\Mail\ServiceResubmittedMail;
use App\Mail\ServiceArchivedMail;


class CoachServiceController extends Controller
{
    private array $ENV_OPTS = [
        'Clients Home','Coaches Home','Community Event','Corporate Environment',
        'Group Activity','Gymnasium','Meeting in Person','Outdoor','Rehabilitation Centres',
        'Training Camps','Virtual Platforms','Other',
    ];
    private array $ACC_OPTS = [
        'Accessible Toilet','Disabled Car Parking','Disabled Shower/Wet Room',
        'Sign Language Service','Step Free Access','Wheelchair Access','Other',
    ];
    private array $LEVELS = ['beginner','intermediate','advanced','athlete'];

    private function assertOwner(Service $service, Request $request): void
    {
        abort_unless($service->coach_id === $request->user()->id, 403);
    }

   public function index(Request $request)
{
    $services = Service::with(['category','packages'])
        ->where('coach_id', $request->user()->id)
        ->where('status', '!=', 'archived')   // hide archived services in normal list
        ->latest()
        ->paginate(12);

    return view('coach.services.index', compact('services'));
}


    public function create(Request $request)
    {
        $categories = ServiceCategory::where('is_active', true)
            ->orderBy('sort_order')->orderBy('name')
            ->get(['id','name']);

        return view('coach.services.create', [
            'categories' => $categories,
            'envOpts'    => $this->ENV_OPTS,
            'accOpts'    => $this->ACC_OPTS,
            'levels'     => $this->LEVELS,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title'       => ['required','string','max:140'],
            'description' => ['required','string','max:5000'],
            'category_id' => ['required','exists:service_categories,id'],

            'thumbnail'   => ['nullable','image','max:4096'],
            'images.*'    => ['nullable','image','max:4096'],

            'environments'  => ['required','array','min:1'],
            'environments.*'=> ['string','max:60'],
            'environment_other' => ['nullable','string','max:2000'],

            'accessibility'  => ['required','array','min:1'],
            'accessibility.*'=> ['string','max:60'],
            'accessibility_other' => ['nullable','string','max:2000'],

            'disability_accessible' => ['required','boolean'],
            'service_level' => ['required', Rule::in($this->LEVELS)],

            // packages
            'pkg_name'        => ['nullable','array'],
            'pkg_hours_day'   => ['nullable','array'],
            'pkg_days'        => ['nullable','array'],
            'pkg_hours_total' => ['nullable','array'],
            'pkg_rate_hour'   => ['nullable','array'],
            'pkg_total'       => ['nullable','array'],
            'pkg_equipment'   => ['nullable','array'],
            'pkg_desc'        => ['nullable','array'],

            // faqs
            'faq_q' => ['nullable','array'],
            'faq_a' => ['nullable','array'],
        ]);

        $thumb = $request->hasFile('thumbnail')
            ? $request->file('thumbnail')->store('services/thumbs','public')
            : null;

        $gallery = [];
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $img) {
                $gallery[] = $img->store('services/gallery','public');
            }
        }

        $service = Service::create([
            'coach_id'              => $request->user()->id,
            'category_id'           => $data['category_id'],
            'title'                 => $data['title'],
            'description'           => $data['description'],
            'thumbnail_path'        => $thumb,
            'images'                => $gallery ?: null,
            'environments'          => $data['environments'] ?? [],
            'environment_other'     => $data['environment_other'] ?? null,
            'accessibility'         => $data['accessibility'] ?? [],
            'accessibility_other'   => $data['accessibility_other'] ?? null,
            'disability_accessible' => (bool)$data['disability_accessible'],
            'service_level'         => $data['service_level'],
            'is_active'   => 1,
'is_approved' => 0,
'approved_at' => null,
'status'      => 'under_review',

        ]);

        // Packages (create simple, sorted)
        foreach ((array) $request->input('pkg_name', []) as $i => $name) {
            if (!filled($name)) continue;
            ServicePackage::create([
                'service_id'    => $service->id,
                'name'          => trim($name),
                'hours_per_day' => (float) ($request->input("pkg_hours_day.$i") ?? 0),
                'total_days'    => (int)   ($request->input("pkg_days.$i") ?? 0),
                'total_hours'   => (float) ($request->input("pkg_hours_total.$i") ?? 0),
                'hourly_rate'   => (float) ($request->input("pkg_rate_hour.$i") ?? 0),
                'total_price'   => (float) ($request->input("pkg_total.$i") ?? 0),
                'equipments'    => $request->input("pkg_equipment.$i"),
                'description'   => $request->input("pkg_desc.$i"),
                'sort_order'    => $i,
                'is_active'   => false,
                'archived_at' => null,
            ]);
        }

        // FAQs
        $fq = (array) $request->input('faq_q', []);
        $fa = (array) $request->input('faq_a', []);
        foreach ($fq as $i => $q) {
            if (!filled($q) && !filled($fa[$i] ?? null)) continue;
            ServiceFaq::create([
                'service_id' => $service->id,
                'question'   => trim($q),
                'answer'     => trim($fa[$i] ?? ''),
                'sort_order' => $i,
            ]);
        }
        

        return redirect()->route('coach.services.index')->with('ok', __('Service Created Successfully.'));
    }

    public function edit(Service $service, Request $request)
    {
        $this->assertOwner($service, $request);

        $categories = ServiceCategory::where('is_active', true)
            ->orderBy('sort_order')->orderBy('name')
            ->get(['id','name']);

        $service->load(['packages' => fn($q)=>$q->orderBy('sort_order'), 'faqs' => fn($q)=>$q->orderBy('sort_order')]);

        return view('coach.services.edit', [
            'service'   => $service,
            'categories'=> $categories,
            'envOpts'   => $this->ENV_OPTS,
            'accOpts'   => $this->ACC_OPTS,
            'levels'    => $this->LEVELS,
        ]);
    }

    public function update(Service $service, Request $request)
    {
        $this->assertOwner($service, $request);
        return DB::transaction(function () use ($service, $request) {


        $data = $request->validate([
            'title'       => ['required','string','max:140'],
            'description' => ['required','string','max:5000'],
            'category_id' => ['required','exists:service_categories,id'],

            'thumbnail'   => ['nullable','image','max:4096'],
            'images.*'    => ['nullable','image','max:4096'],

            'environments'  => ['required','array','min:1'],
            'environments.*'=> ['string','max:60'],
            'environment_other' => ['nullable','string','max:2000'],

            'accessibility'  => ['required','array','min:1'],
            'accessibility.*'=> ['string','max:60'],
            'accessibility_other' => ['nullable','string','max:2000'],

            'disability_accessible' => ['required','boolean'],
            'service_level' => ['required', Rule::in($this->LEVELS)],

            // repeater arrays
            'pkg_id'   => ['nullable','array'],
'pkg_id.*' => ['nullable','integer'],

            'pkg_name'        => ['nullable','array'],
            'pkg_hours_day'   => ['nullable','array'],
            'pkg_days'        => ['nullable','array'],
            'pkg_hours_total' => ['nullable','array'],
            'pkg_rate_hour'   => ['nullable','array'],
            'pkg_total'       => ['nullable','array'],
            'pkg_equipment'   => ['nullable','array'],
            'pkg_desc'        => ['nullable','array'],
            'faq_q'           => ['nullable','array'],
            'faq_a'           => ['nullable','array'],
            

            // existing image deletions
            'remove_images'   => ['nullable','array'],
            'remove_images.*' => ['string'],
        ]);

        // thumbnail update (optional)
        if ($request->hasFile('thumbnail')) {
            if ($service->thumbnail_path) Storage::disk('public')->delete($service->thumbnail_path);
            $service->thumbnail_path = $request->file('thumbnail')->store('services/thumbs','public');
        }

        // gallery append & remove
        $images = (array) ($service->images ?? []);
        // remove selected
        foreach ((array) $request->input('remove_images', []) as $relPath) {
            $idx = array_search($relPath, $images, true);
            if ($idx !== false) {
                Storage::disk('public')->delete($relPath);
                unset($images[$idx]);
            }
        }
        // append new
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $img) {
                $images[] = $img->store('services/gallery','public');
            }
        }
        $service->images = array_values($images) ?: null;

        // simple scalar updates
        $service->fill([
            'category_id'           => $data['category_id'],
            'title'                 => $data['title'],
            'description'           => $data['description'],
            'environments'          => $data['environments'] ?? [],
            'environment_other'     => $data['environment_other'] ?? null,
            'accessibility'         => $data['accessibility'] ?? [],
            'accessibility_other'   => $data['accessibility_other'] ?? null,
            'disability_accessible' => (bool) $data['disability_accessible'],
            'service_level'         => $data['service_level'],
        ])->save();

        // re-sync packages (simple approach: delete & recreate)
       $incomingIds = [];

$pkgNames = (array) $request->input('pkg_name', []);

foreach ($pkgNames as $i => $name) {
    if (!filled($name)) continue;

    $pkgId = $request->input("pkg_id.$i"); // existing id or null

    $payload = [
        'name'          => trim($name),
        'hours_per_day' => (float) ($request->input("pkg_hours_day.$i") ?? 0),
        'total_days'    => (int)   ($request->input("pkg_days.$i") ?? 0),
        'total_hours'   => (float) ($request->input("pkg_hours_total.$i") ?? 0),
        'hourly_rate'   => (float) ($request->input("pkg_rate_hour.$i") ?? 0),
        'total_price'   => (float) ($request->input("pkg_total.$i") ?? 0),
        'equipments'    => $request->input("pkg_equipment.$i"),
        'description'   => $request->input("pkg_desc.$i"),
        'sort_order'    => $i,

        // keep it active if it is in the form
       
        'archived_at'   => null,
    ];

    if ($pkgId) {
        $pkg = $service->packages()->whereKey($pkgId)->first();

        if ($pkg) {
            $pkg->update($payload);
            $incomingIds[] = (int) $pkgId;
        }
    } else {
        $new = $service->packages()->create($payload);
        $incomingIds[] = (int) $new->id;
    }
}

// Now handle packages that were removed from the form
$query = $service->packages();

if (!empty($incomingIds)) {
    $query->whereNotIn('id', $incomingIds);
}

$query->get()->each(function ($pkg) {
    $hasBookings = \App\Models\Reservation::where('package_id', $pkg->id)->exists();

    if ($hasBookings) {
        $pkg->update([
            'is_active'   => false,
            'archived_at' => now(),
        ]);
    } else {
        $pkg->delete();
    }
});


       

        // re-sync faqs
        $service->faqs()->delete();
        $fq = (array) $request->input('faq_q', []);
        $fa = (array) $request->input('faq_a', []);
        foreach ($fq as $i => $q) {
            if (!filled($q) && !filled($fa[$i] ?? null)) continue;
            ServiceFaq::create([
                'service_id' => $service->id,
                'question'   => trim($q),
                'answer'     => trim($fa[$i] ?? ''),
                'sort_order' => $i,
            ]);
        }
        // 🔁 After any edit, send service under review again
$service->forceFill([
    'is_approved' => 0,
    'approved_at' => null,
    'status'      => 'under_review',
])->save();


// Disable packages so they can’t be booked until approved again
$service->packages()->update([
    'archived_at' => null,
]);




       return back()->with('ok', __('Changes Saved. Your Service Is Now Under Review.'));

    });
}

   public function destroy(Service $service, Request $request)
{
    $this->assertOwner($service, $request);

    // Check if this service was ever booked/paid
    // Adjust conditions if your statuses differ
    $hasBookings = $service->reservations()
        ->whereIn('status', ['booked','completed','cancelled']) // whatever you use
        ->exists();

    if ($hasBookings) {
        // Airbnb style: DO NOT delete. Archive it.
        $service->update([
            'status'      => 'archived',
            'archived_at' => now(),
            'is_active'   => false,
        ]);
        $service->packages()->update([
    'is_active'   => false,
    'archived_at' => now(),
]);


        return back()->with('ok', __('Service Archived. Existing Bookings Remain Intact.'));
    }

    // If no bookings, safe to delete files and soft-delete
    if ($service->thumbnail_path) Storage::disk('public')->delete($service->thumbnail_path);
    foreach ((array) ($service->images ?? []) as $rel) {
        Storage::disk('public')->delete($rel);
    }

    $service->delete(); // soft delete (because SoftDeletes)
    return redirect()->route('coach.services.index')->with('ok', __('Service Deleted.'));
}

  public function toggle(Service $service, Request $request)
{
    $this->assertOwner($service, $request);

    // Admin disabled => coach cannot change visibility
    if (!is_null($service->admin_disabled_at)) {
        return back()->with('ok', __('This Service Was Disabled By Admin. You Cannot Activate It.'));
    }

    if ($service->status === 'archived') {
        return back()->with('ok', __('Archived Services Cannot Be Activated.'));
    }

    if ((int)$service->is_approved !== 1) {
        return back()->with('ok', __('This service is Under Review / Not Approved Yet.'));
    }

    $service->is_active = ! $service->is_active;
    $service->status    = $service->is_active ? 'active' : 'paused';
    $service->save();

    return back()->with('ok', $service->is_active ? __('Service Activated.') : __('Service Hidden.'));
}



}

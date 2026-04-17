<?php
// app/Http/Controllers/Admin/ServiceController.php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\AdminSecurity\AdminRiskService;
use Illuminate\Support\Facades\Mail;
use App\Mail\ServiceApprovedMail;
use App\Mail\ServiceRejectedMail;
use App\Mail\ServiceDisabledMail;
use App\Mail\ServiceEnabledMail;



class ServiceController extends Controller
{
    public function index(Request $r)
    {
        $q      = trim((string) $r->input('q'));
        $status = $r->input('status', 'all'); // all|live|pending|rejected|inactive|deleted
 
        $per    = (int) $r->input('per', 10);
        $per    = in_array($per, [10,20,50,100], true) ? $per : 10;

       $base = Service::query()->with(['coach','packages']); // non-deleted

$query = ($status === 'deleted')
    ? Service::onlyTrashed()->with(['coach','packages'])
    : clone $base;

$services = $query
    ->when($q, function ($x) use ($q) {
        $x->where(function ($y) use ($q) {
            $y->where('title', 'like', "%$q%")
              ->orWhereHas('coach', function ($c) use ($q) {
                  $c->where('first_name','like',"%$q%")
                    ->orWhere('last_name','like',"%$q%")
                    ->orWhere('email','like',"%$q%");
              });
        });
    })
    ->when($status !== 'all' && $status !== 'deleted', function ($x) use ($status) {
        return match ($status) {
            'live'      => $x->where('is_active',1)->where('is_approved',1),
            'pending'   => $x->where('is_approved',0),
            'rejected'  => $x->where('is_approved',-1),
            'inactive'  => $x->where('is_active',0),
            'disabled'  => $x->where('admin_disabled', 1),

            default     => $x,
        };
    })
    ->orderByDesc('created_at')
    ->paginate($per)
    ->appends(['q'=>$q,'status'=>$status,'per'=>$per]);


        return view('admin.services.index', [
            'services' => $services,
            'q'        => $q,
            'status'   => $status,
            'per'      => $per,
            'pageMode' => 'list', // for headline text / buttons
        ]);
    }

    // For "Service Request" menu: always pending filter
    public function requests(Request $r)
    {
        $r->merge(['status' => 'pending']);
        $resp = $this->index($r);
        $resp->getData()->pageMode = 'requests';
        return $resp;
    }

  public function show($id)
{
    $service = Service::withTrashed()
        ->with(['coach','packages','faqs'])
        ->findOrFail($id);

    return view('admin.services.show', compact('service'));
}


  public function approve(Service $service)
{
    $service->loadMissing('coach');

    $wasApproved = ((int) $service->is_approved === 1);

    $service->forceFill([
        'is_approved' => 1,
        'approved_at' => now(),
        'status'      => 'active',
        'is_active'   => 1,
    ])->save();

    if (! $wasApproved && $service->coach?->email) {
        Mail::to($service->coach->email)->send(new ServiceApprovedMail($service));
    }

    return back()->with('ok', 'Service Approved.');
}



  public function reject(Service $service, Request $r)
{
    $service->loadMissing('coach');

    $wasRejected = ((int) $service->is_approved === -1);

    $reason = trim((string) $r->input('reason')) ?: null;

    $service->forceFill([
        'is_approved' => -1,
        'approved_at' => null,
        'status'      => 'rejected',
        'is_active'   => 0,
    ])->save();

    if (! $wasRejected && $service->coach?->email) {
        Mail::to($service->coach->email)->send(new ServiceRejectedMail($service, $reason));
    }

    return back()->with('ok', 'Service Marked As Rejected.');
}



    public function toggleActive(Service $service)
    {
        $service->is_active = ! $service->is_active;
        $service->save();

        return back()->with('ok','Service Status Updated.');
    }

   public function destroy(Service $service, AdminRiskService $risk)
{
    DB::transaction(function () use ($service, $risk) {

        // ✅ LOG + APPLY RULES (before delete)
        $risk->recordServiceDeletion(auth()->id(), $service->id);

        // ✅ then soft delete
        $service->delete();
    });

    return back()->with('ok', 'Service moved to Deleted.');
}



public function restore($id,AdminRiskService $risk)
{
    $service = Service::onlyTrashed()->findOrFail($id);

    DB::transaction(function () use ($service,$risk) {
        $service->restore();
        $risk->recordServiceRestore(auth()->id(), $service);
    });

    return back()->with('ok', 'Service restored.');
}

public function disable(Service $service, Request $r)
{
    $service->loadMissing('coach');

    $wasDisabled = ((int) $service->admin_disabled === 1);

    $reason = trim((string) $r->input('reason')) ?: null;

    $service->forceFill([
        'admin_disabled'        => 1,
        'admin_disabled_at'     => now(),
        'admin_disabled_reason' => $reason,
        'is_active'             => 0,
        'status'                => 'inactive',
    ])->save();

    if (! $wasDisabled && $service->coach?->email) {
        Mail::to($service->coach->email)->send(new ServiceDisabledMail($service, $reason));
    }

    return back()->with('ok', 'Service disabled by admin.');
}


public function enable(Service $service)
{
    $service->loadMissing('coach');

    $wasEnabled = ((int) $service->admin_disabled === 0);

    $service->forceFill([
        'admin_disabled'        => 0,
        'admin_disabled_at'     => null,
        'admin_disabled_reason' => null,
        'status'                => ((int)$service->is_approved === 1) ? 'active' : 'under_review',
    ])->save();

    if (! $wasEnabled && $service->coach?->email) {
        Mail::to($service->coach->email)->send(new ServiceEnabledMail($service));
    }

    return back()->with('ok', 'Service enabled by admin.');
}



}

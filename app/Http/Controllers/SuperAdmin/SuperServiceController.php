<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Admin\ServiceController as AdminServiceController;
use App\Models\Service;
use Illuminate\Http\Request;

class SuperServiceController extends AdminServiceController
{
    public function index(Request $r)
    {
        $q      = trim((string) $r->input('q'));
        $status = $r->input('status', 'all'); // all|live|pending|rejected|inactive|deleted|disabled
        $per    = (int) $r->input('per', 10);
        $per    = in_array($per, [10,20,50,100], true) ? $per : 10;

        $base = Service::query()->with(['coach','packages']);

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
                    'live'     => $x->where('is_active',1)->where('is_approved',1),
                    'pending'  => $x->where('is_approved',0),
                    'rejected' => $x->where('is_approved',-1),
                    'inactive' => $x->where('is_active',0),
                    'disabled' => $x->where('admin_disabled', 1),
                    default    => $x,
                };
            })
            ->orderByDesc('created_at')
            ->paginate($per)
            ->appends(['q'=>$q,'status'=>$status,'per'=>$per]);

        return view('superadmin.services.index', [
            'services' => $services,
            'q'        => $q,
            'status'   => $status,
            'per'      => $per,
            'pageMode' => 'list',
        ]);
    }

    // For "Service Request" menu: always pending filter
    public function requests(Request $r)
    {
        // simplest (no Response object mutation problems)
        $r->merge(['status' => 'pending']);
        $viewData = $this->index($r)->getData();
        $viewData['pageMode'] = 'requests';

        // Re-render with same data
        return view('superadmin.services.index', (array) $viewData);
    }

    public function show($id)
    {
        $service = Service::withTrashed()
            ->with(['coach','packages','faqs'])
            ->findOrFail($id);

        return view('superadmin.services.show', compact('service'));
    }
}

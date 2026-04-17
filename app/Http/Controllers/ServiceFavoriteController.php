<?php
namespace App\Http\Controllers;

use App\Models\Service;
use App\Models\ServiceFavorite;
use Illuminate\Http\Request;

class ServiceFavoriteController extends Controller
{
    public function toggle(Service $service, Request $request)
    {
        $user = $request->user();

        $existing = ServiceFavorite::where('user_id', $user->id)
            ->where('service_id', $service->id)
            ->first();

        if ($existing) {
            $existing->delete();
            $status = 'removed';
            $msg = __('Service Removed From Favorites.');
        } else {
            ServiceFavorite::create([
                'user_id'    => $user->id,
                'service_id' => $service->id,
            ]);
            $status = 'added';
            $msg = __('Service Added To Favorites.');
        }

        if ($request->wantsJson()) {
            return response()->json([
                'ok'         => true,
                'status'     => $status,
                'service_id' => $service->id,
                'message'    => $msg,
            ]);
        }

        return back()->with('ok', $msg);
    }
}

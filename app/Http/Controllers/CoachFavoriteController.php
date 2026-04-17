<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CoachFavorite;
use App\Models\Users;

class CoachFavoriteController extends Controller
{
    public function toggle(Users $coach, Request $request)
    {
        $user = $request->user();

        if ($user->id === $coach->id) {
            abort(403, 'You cannot favorite yourself');
        }

        $existing = CoachFavorite::where('user_id', $user->id)
            ->where('coach_id', $coach->id)
            ->first();

        if ($existing) {
            $existing->delete();
            $status = 'removed';
            $message = 'Coach Removed From Favorites';
        } else {
            CoachFavorite::create([
                'user_id'  => $user->id,
                'coach_id' => $coach->id,
            ]);
            $status = 'added';
            $message = 'Coach Added To Favorites';
        }

        if ($request->wantsJson()) {
            return response()->json([
                'ok'       => true,
                'status'   => $status,
                'coach_id' => $coach->id,
                'message'  => $message,
            ]);
        }

        return back()->with('ok', __($message));
    }
}


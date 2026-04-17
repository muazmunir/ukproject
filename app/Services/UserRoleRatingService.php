<?php

namespace App\Services;

use App\Models\ReservationReview;
use App\Models\Users;

class UserRoleRatingService
{
    public function refreshUserRoleRating(int $userId, string $role): void
    {
        $role = strtolower(trim($role));

        $reviewQuery = ReservationReview::query()
            ->where('reviewee_id', $userId)
            ->where('reviewee_role', $role);

        $avg = (float) ($reviewQuery->avg('stars') ?? 0);
        $count = (int) (clone $reviewQuery)->count();

        $user = Users::find($userId);

        if (! $user) {
            return;
        }

        if ($role === 'coach') {
            $user->coach_rating_avg = $count > 0 ? round($avg, 2) : null;
            $user->coach_rating_count = $count;
        } elseif ($role === 'client') {
            $user->client_rating_avg = $count > 0 ? round($avg, 2) : null;
            $user->client_rating_count = $count;
        } else {
            return;
        }

        $user->save();
    }
}
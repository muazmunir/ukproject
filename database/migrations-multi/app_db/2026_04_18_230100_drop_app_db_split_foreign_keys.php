<?php

use App\Support\Migrations\DropsMysqlSplitForeignKeys;
use Illuminate\Database\Migrations\Migration;

/**
 * app_db tables reference `users` (auth_db) and payment-side IDs (payments_db).
 * MySQL enforces FKs only inside one schema — drop constraints; app keeps logical integrity.
 */
return new class extends Migration
{
    public function up(): void
    {
        $c = 'app_db';

        DropsMysqlSplitForeignKeys::dropForTables($c, [
            'reservations' => ['client_id', 'coach_id', 'coach_payout_id'],
            'reservation_reviews' => ['reviewer_id', 'reviewee_id'],
            'service_favorites' => ['user_id'],
            'coach_favorites' => ['user_id', 'coach_id'],
            'services' => ['coach_id'],
            'coach_weekly_hours' => ['coach_id'],
            'coach_unavailabilities' => ['coach_id'],
            'coach_availability_overrides' => ['coach_id'],
        ]);
    }

    public function down(): void
    {
        //
    }
};

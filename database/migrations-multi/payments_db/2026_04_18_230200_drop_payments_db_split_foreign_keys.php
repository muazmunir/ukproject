<?php

use App\Support\Migrations\DropsMysqlSplitForeignKeys;
use Illuminate\Database\Migrations\Migration;

/**
 * payments_db references `users` / `coach_profiles` / `reservations` on other connections.
 */
return new class extends Migration
{
    public function up(): void
    {
        $c = 'payments_db';

        DropsMysqlSplitForeignKeys::dropForTables($c, [
            'disputes' => [
                'reservation_id',
                'opened_by_user_id',
                'client_id',
                'coach_id',
                'decided_by_admin_id',
                'latest_summary_by_id',
                'taken_by_admin_id',
                'resolved_by_admin_id',
            ],
            'dispute_summaries' => ['staff_id'],
            'refunds' => ['reservation_id'],
            'coach_payout_accounts' => ['coach_profile_id'],
            'coach_payouts' => ['coach_profile_id'],
            'coach_payout_items' => ['reservation_id'],
            'payouts' => ['user_id'],
            'wallet_transactions' => ['user_id'],
            'coach_withdrawals' => ['coach_id'],
            'coach_payout_methods' => ['coach_id'],
        ]);
    }

    public function down(): void
    {
        //
    }
};

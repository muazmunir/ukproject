<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('coach_payouts', function (Blueprint $table) {
            // Drop existing foreign key
            $table->dropForeign(['payout_batch_id']);

            // Add new foreign key constraint
            $table->foreign('payout_batch_id')
                ->references('id')
                ->on('payout_runs')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('coach_payouts', function (Blueprint $table) {
            // Drop new foreign key
            $table->dropForeign(['payout_batch_id']);

            // Restore old foreign key
            $table->foreign('payout_batch_id')
                ->references('id')
                ->on('payout_batches')
                ->nullOnDelete();
        });
    }
};
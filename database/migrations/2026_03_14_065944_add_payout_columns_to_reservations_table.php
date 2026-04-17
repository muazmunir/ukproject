<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->string('earnings_status')->nullable()->index();
            // pending, available, blocked, refunded

            $table->string('payout_status')->nullable()->index();
            // not_ready, ready, queued, sent, paid, failed, blocked

            $table->foreignId('coach_payout_id')->nullable()->constrained()->nullOnDelete();

            $table->timestamp('earnings_released_at')->nullable();
            $table->timestamp('payout_queued_at')->nullable();
            $table->timestamp('payout_sent_at')->nullable();

            $table->string('payout_provider')->nullable();
            $table->string('provider_transfer_id')->nullable();
            $table->string('provider_payout_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('coach_payout_id');
            $table->dropColumn([
                'earnings_status',
                'payout_status',
                'earnings_released_at',
                'payout_queued_at',
                'payout_sent_at',
                'payout_provider',
                'provider_transfer_id',
                'provider_payout_id',
            ]);
        });
    }
};
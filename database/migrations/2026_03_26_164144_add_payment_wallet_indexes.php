<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->index(['provider', 'status'], 'payments_provider_status_idx');
            $table->index(['payment_channel', 'wallet_type'], 'payments_channel_wallet_idx');
            $table->index(['reservation_id', 'provider'], 'payments_reservation_provider_idx');
        });

        Schema::table('reservations', function (Blueprint $table) {
            $table->index(['checkout_method', 'wallet_type'], 'reservations_checkout_wallet_idx');
            $table->index(['payment_status', 'provider'], 'reservations_payment_provider_idx');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex('payments_provider_status_idx');
            $table->dropIndex('payments_channel_wallet_idx');
            $table->dropIndex('payments_reservation_provider_idx');
        });

        Schema::table('reservations', function (Blueprint $table) {
            $table->dropIndex('reservations_checkout_wallet_idx');
            $table->dropIndex('reservations_payment_provider_idx');
        });
    }
};
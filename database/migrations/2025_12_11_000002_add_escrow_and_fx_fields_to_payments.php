<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddEscrowAndFxFieldsToPayments extends Migration
{
    public function up()
    {
        Schema::table('payments', function (Blueprint $table) {
            // Payment breakdown (all in minor units)
            // amount_total already exists = what client actually paid (subtotal + client fee)
            $table->bigInteger('service_subtotal_minor')
                  ->unsigned()
                  ->default(0)
                  ->after('amount_total'); // base service price (without client fee)

            $table->bigInteger('client_fee_minor')
                  ->unsigned()
                  ->default(0)
                  ->after('service_subtotal_minor'); // client_commission

            $table->bigInteger('coach_fee_minor')
                  ->unsigned()
                  ->default(0)
                  ->after('client_fee_minor'); // coach_commission to be taken on release

            // FX info
            $table->string('payout_currency', 3)
                  ->nullable()
                  ->after('currency'); // currency for coach payouts (may differ)

            $table->string('fx_provider', 32)
                  ->nullable()
                  ->after('payout_currency'); // 'stripe', 'airwallex', 'wise'

            $table->decimal('fx_rate', 18, 8)
                  ->nullable()
                  ->after('fx_provider'); // payment_currency -> payout_currency

            $table->decimal('fx_fee_percent', 5, 2)
                  ->nullable()
                  ->after('fx_rate'); // e.g. 1.50, 0.50 etc.

            // escrow final release time (when money left platform to coach wallet)
            $table->timestamp('escrow_released_at')
                  ->nullable()
                  ->after('succeeded_at');
        });
    }

    public function down()
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn([
                'service_subtotal_minor',
                'client_fee_minor',
                'coach_fee_minor',
                'payout_currency',
                'fx_provider',
                'fx_rate',
                'fx_fee_percent',
                'escrow_released_at',
            ]);
        });
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->bigInteger('wallet_platform_credit_used_minor')
                  ->default(0)
                  ->after('total_minor');

            $table->bigInteger('payable_minor')
                  ->default(0)
                  ->after('wallet_platform_credit_used_minor');

            $table->string('funding_status')
                  ->default('unfunded')
                  ->after('payable_minor');
            // values: unfunded | partially_funded | funded
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn([
                'wallet_platform_credit_used_minor',
                'payable_minor',
                'funding_status',
            ]);
        });
    }
};

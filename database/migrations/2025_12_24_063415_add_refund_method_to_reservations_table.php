<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->string('refund_method', 20)
                ->default('wallet_credit')
                ->after('refund_total_minor')
                ->index();
            // values: wallet_credit | original_payment
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropIndex(['refund_method']);
            $table->dropColumn('refund_method');
        });
    }
};

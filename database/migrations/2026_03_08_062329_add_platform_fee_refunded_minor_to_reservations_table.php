<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->unsignedBigInteger('platform_fee_refund_requested_minor')
                ->default(0)
                ->after('platform_fee_minor');

            $table->unsignedBigInteger('platform_fee_refunded_minor')
                ->default(0)
                ->after('platform_fee_refund_requested_minor');
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn([
                'platform_fee_refund_requested_minor',
                'platform_fee_refunded_minor',
            ]);
        });
    }
};
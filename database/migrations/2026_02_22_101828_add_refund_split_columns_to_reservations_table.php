<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->integer('refund_wallet_minor')->nullable()->after('refund_total_minor');
            $table->integer('refund_external_minor')->nullable()->after('refund_wallet_minor');
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn(['refund_wallet_minor', 'refund_external_minor']);
        });
    }
};
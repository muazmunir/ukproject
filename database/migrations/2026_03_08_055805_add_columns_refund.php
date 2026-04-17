<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('refunds', function (Blueprint $table) {
            $table->unsignedBigInteger('refunded_to_wallet_minor')->default(0)->after('external_amount_minor');
            $table->unsignedBigInteger('refunded_to_original_minor')->default(0)->after('refunded_to_wallet_minor');
        });
    }

    public function down(): void
    {
        Schema::table('refunds', function (Blueprint $table) {
            $table->dropColumn([
                'refunded_to_wallet_minor',
                'refunded_to_original_minor',
            ]);
        });
    }
};
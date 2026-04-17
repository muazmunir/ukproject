<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->string('refund_method', 30)
                  ->nullable()
                  ->default(null)
                  ->change();
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->string('refund_method', 30)
                  ->nullable(false)
                  ->default('wallet_credit')
                  ->change();
        });
    }
};

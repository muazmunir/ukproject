<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            if (!Schema::hasColumn('reservations', 'checkout_method')) {
                $table->string('checkout_method', 40)
                    ->nullable()
                    ->after('funding_status');
            }

            if (!Schema::hasColumn('reservations', 'wallet_type')) {
                $table->string('wallet_type', 40)
                    ->nullable()
                    ->after('checkout_method');
            }
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $drop = [];

            if (Schema::hasColumn('reservations', 'wallet_type')) {
                $drop[] = 'wallet_type';
            }

            if (Schema::hasColumn('reservations', 'checkout_method')) {
                $drop[] = 'checkout_method';
            }

            if (!empty($drop)) {
                $table->dropColumn($drop);
            }
        });
    }
};
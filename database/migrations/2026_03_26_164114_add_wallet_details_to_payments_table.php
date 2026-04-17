<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            if (!Schema::hasColumn('payments', 'payment_channel')) {
                $table->string('payment_channel', 40)
                    ->nullable()
                    ->after('method');
            }

            if (!Schema::hasColumn('payments', 'wallet_type')) {
                $table->string('wallet_type', 40)
                    ->nullable()
                    ->after('payment_channel');
            }

            if (!Schema::hasColumn('payments', 'network_brand')) {
                $table->string('network_brand', 40)
                    ->nullable()
                    ->after('wallet_type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $drop = [];

            if (Schema::hasColumn('payments', 'network_brand')) {
                $drop[] = 'network_brand';
            }

            if (Schema::hasColumn('payments', 'wallet_type')) {
                $drop[] = 'wallet_type';
            }

            if (Schema::hasColumn('payments', 'payment_channel')) {
                $drop[] = 'payment_channel';
            }

            if (!empty($drop)) {
                $table->dropColumn($drop);
            }
        });
    }
};
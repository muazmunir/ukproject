<?php

// database/migrations/xxxx_xx_xx_add_balance_type_to_wallet_transactions_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->string('balance_type', 30)
                ->default('platform_credit')
                ->after('type')
                ->index();
        });
    }

    public function down(): void
    {
        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->dropIndex(['balance_type']);
            $table->dropColumn('balance_type');
        });
    }
};


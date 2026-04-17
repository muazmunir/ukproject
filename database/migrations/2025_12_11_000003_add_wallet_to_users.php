<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddWalletToUsers extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->bigInteger('wallet_balance_minor')
                  ->default(0)
                  ->after('remember_token'); // adjust position as you wish

            $table->string('wallet_currency', 3)
                  ->default('USD')
                  ->after('wallet_balance_minor');
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['wallet_balance_minor', 'wallet_currency']);
        });
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
  public function up()
{
    Schema::table('reservations', function (Blueprint $table) {
        $table->unsignedBigInteger('wallet_hold_tx_id')->nullable()->index()
              ->after('wallet_platform_credit_used_minor');
    });
}

public function down()
{
    Schema::table('reservations', function (Blueprint $table) {
        $table->dropColumn('wallet_hold_tx_id');
    });
}
};

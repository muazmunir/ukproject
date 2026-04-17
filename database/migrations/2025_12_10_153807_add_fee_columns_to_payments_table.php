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
    Schema::table('payments', function (Blueprint $table) {
        $table->unsignedBigInteger('platform_fee')->default(0);   // stored in cents/minor
        $table->unsignedBigInteger('coach_earnings')->default(0); // stored in cents/minor
    });
}

public function down()
{
    Schema::table('payments', function (Blueprint $table) {
        $table->dropColumn(['platform_fee', 'coach_earnings']);
    });
}

};

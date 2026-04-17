<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::table('coach_payout_methods', function (Blueprint $table) {
      $table->string('status')->default('pending')->after('details'); 
      // pending | active | disabled
    });
  }

  public function down(): void {
    Schema::table('coach_payout_methods', function (Blueprint $table) {
      $table->dropColumn('status');
    });
  }
};


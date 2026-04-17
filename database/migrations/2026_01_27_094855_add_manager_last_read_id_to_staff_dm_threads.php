<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    Schema::table('staff_dm_threads', function (Blueprint $table) {
      $table->unsignedBigInteger('manager_last_read_id')->nullable()->after('agent_last_read_id');
    });
  }

  public function down(): void
  {
    Schema::table('staff_dm_threads', function (Blueprint $table) {
      $table->dropColumn('manager_last_read_id');
    });
  }
};
<?php

// php artisan make:migration add_agent_last_read_id_to_staff_dm_threads --table=staff_dm_threads

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    Schema::table('staff_dm_threads', function (Blueprint $table) {
      $table->unsignedBigInteger('agent_last_read_id')->nullable()->after('last_message_id');
    });
  }

  public function down(): void
  {
    Schema::table('staff_dm_threads', function (Blueprint $table) {
      $table->dropColumn('agent_last_read_id');
    });
  }
};

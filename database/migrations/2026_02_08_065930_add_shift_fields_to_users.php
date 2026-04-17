<?php

// database/migrations/2026_02_08_000001_add_shift_fields_to_users.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::table('users', function (Blueprint $t) {
      $t->boolean('shift_enabled')->default(false)->after('timezone');
      $t->time('shift_start')->nullable()->after('shift_enabled'); // local time
      $t->time('shift_end')->nullable()->after('shift_start');     // local time
      $t->json('shift_days')->nullable()->after('shift_end');      // [1..7] ISO weekday
      $t->unsignedSmallInteger('shift_grace_minutes')->default(0)->after('shift_days');
    });
  }

  public function down(): void {
    Schema::table('users', function (Blueprint $t) {
      $t->dropColumn(['shift_enabled','shift_start','shift_end','shift_days','shift_grace_minutes']);
    });
  }
};

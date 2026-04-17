<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    Schema::table('users', function (Blueprint $t) {
      $t->dateTime('absence_start_at')->nullable()->change();
      $t->dateTime('absence_end_at')->nullable()->change();
      $t->dateTime('absence_set_at')->nullable()->change();
      $t->dateTime('support_status_since')->nullable()->change();
    });
  }

  public function down(): void
  {
    Schema::table('users', function (Blueprint $t) {
      $t->timestamp('absence_start_at')->nullable()->change();
      $t->timestamp('absence_end_at')->nullable()->change();
      $t->timestamp('absence_set_at')->nullable()->change();
      $t->timestamp('support_status_since')->nullable()->change();
    });
  }
};

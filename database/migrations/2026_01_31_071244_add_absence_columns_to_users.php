<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::table('users', function (Blueprint $t) {
      $t->string('absence_status', 20)->nullable()->after('support_status'); // authorized|unauthorized
      $t->timestamp('absence_start_at')->nullable()->after('absence_status');
      $t->timestamp('absence_end_at')->nullable()->after('absence_start_at');
      $t->unsignedBigInteger('absence_set_by')->nullable()->after('absence_end_at'); // manager/superadmin id
      $t->timestamp('absence_set_at')->nullable()->after('absence_set_by');
      $t->index(['absence_status']);
      $t->index(['absence_end_at']);
    });
  }

  public function down(): void {
    Schema::table('users', function (Blueprint $t) {
      $t->dropIndex(['absence_status']);
      $t->dropIndex(['absence_end_at']);
      $t->dropColumn(['absence_status','absence_start_at','absence_end_at','absence_set_by','absence_set_at']);
    });
  }
};

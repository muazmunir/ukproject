<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::table('agent_absence_audits', function (Blueprint $t) {
      $t->string('file_disk', 40)->nullable()->after('meta');
      $t->string('file_path', 255)->nullable()->after('file_disk');
      $t->string('file_name', 255)->nullable()->after('file_path');
      $t->string('file_mime', 100)->nullable()->after('file_name');
      $t->unsignedBigInteger('file_size')->nullable()->after('file_mime');
    });
  }

  public function down(): void {
    Schema::table('agent_absence_audits', function (Blueprint $t) {
      $t->dropColumn(['file_disk','file_path','file_name','file_mime','file_size']);
    });
  }
};
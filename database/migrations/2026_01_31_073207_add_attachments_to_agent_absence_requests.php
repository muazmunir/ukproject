<?php

// database/migrations/2026_02_01_000004_add_attachments_to_agent_absence_requests.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::table('agent_absence_requests', function (Blueprint $t) {
      $t->string('proof_disk', 40)->nullable()->after('decision_note');
      $t->string('proof_path', 255)->nullable()->after('proof_disk');
      $t->string('proof_name', 255)->nullable()->after('proof_path');
      $t->string('proof_mime', 100)->nullable()->after('proof_name');
      $t->unsignedBigInteger('proof_size')->nullable()->after('proof_mime');
    });
  }

  public function down(): void {
    Schema::table('agent_absence_requests', function (Blueprint $t) {
      $t->dropColumn(['proof_disk','proof_path','proof_name','proof_mime','proof_size']);
    });
  }
};

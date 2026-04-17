<?php

// database/migrations/2026_02_01_000003_create_agent_absence_audits.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::create('agent_absence_audits', function (Blueprint $t) {
      $t->id();
      $t->unsignedBigInteger('agent_id'); // affected agent
      $t->unsignedBigInteger('actor_id'); // who did it
      $t->unsignedBigInteger('request_id')->nullable(); // can be null for direct set
      $t->string('action', 60); // requested|approved|rejected|cancelled|direct_set|removed
      $t->json('meta')->nullable();

      $t->string('ip', 64)->nullable();
      $t->string('user_agent', 255)->nullable();
      $t->timestamp('created_at')->useCurrent();

      $t->index(['agent_id']);
      $t->index(['actor_id']);
      $t->index(['request_id']);
    });
  }

  public function down(): void {
    Schema::dropIfExists('agent_absence_audits');
  }
};

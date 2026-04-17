<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    Schema::create('staff_team_members', function (Blueprint $table) {
      $table->id();
      $table->foreignId('team_id')->constrained('staff_teams')->cascadeOnDelete();
      $table->foreignId('agent_id')->constrained('users')->cascadeOnDelete(); // admin/agent
      $table->timestamp('start_at')->useCurrent();
      $table->timestamp('end_at')->nullable(); // when reassigned
      $table->timestamps();

      $table->index(['agent_id', 'end_at']); // "active assignment" query
      $table->unique(['team_id', 'agent_id', 'start_at']); // history-safe
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('staff_team_members');
  }
};

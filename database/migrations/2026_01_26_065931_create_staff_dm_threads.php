<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    Schema::create('staff_dm_threads', function (Blueprint $table) {
      $table->id();
      $table->foreignId('manager_id')->constrained('users')->cascadeOnDelete();
      $table->foreignId('agent_id')->constrained('users')->cascadeOnDelete();

      // active thread = current manager assignment
      $table->boolean('is_active')->default(true);

      // for sidebar ordering
      $table->unsignedBigInteger('last_message_id')->nullable();
      $table->timestamp('last_message_at')->nullable();

      $table->timestamps();

      $table->index(['manager_id', 'is_active']);
      $table->index(['agent_id', 'is_active']);
      $table->unique(['manager_id', 'agent_id']); // ensures ONE thread per pair
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('staff_dm_threads');
  }
};

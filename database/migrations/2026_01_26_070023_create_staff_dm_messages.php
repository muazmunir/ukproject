<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    Schema::create('staff_dm_messages', function (Blueprint $table) {
      $table->id();
      $table->foreignId('thread_id')->constrained('staff_dm_threads')->cascadeOnDelete();
      $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
      $table->text('body');
      $table->timestamps();

      $table->index(['thread_id', 'id']);
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('staff_dm_messages');
  }
};

<?php

// database/migrations/2026_01_29_000002_create_staff_chat_room_users.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::create('staff_chat_room_users', function (Blueprint $t) {
      $t->id();
      $t->foreignId('room_id')->constrained('staff_chat_rooms')->cascadeOnDelete();
      $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();

      // unread tracking
      $t->unsignedBigInteger('last_read_message_id')->nullable();
      $t->timestamp('last_read_at')->nullable();

      $t->timestamps();
      $t->unique(['room_id','user_id']);
      $t->index(['user_id']);
    });
  }

  public function down(): void {
    Schema::dropIfExists('staff_chat_room_users');
  }
};

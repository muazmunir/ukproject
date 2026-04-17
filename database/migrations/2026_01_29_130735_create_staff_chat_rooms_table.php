<?php

// database/migrations/2026_01_29_000001_create_staff_chat_rooms.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::create('staff_chat_rooms', function (Blueprint $t) {
      $t->id();
      $t->string('room_type', 30); // all_staff | team_group | dm
      $t->string('name')->nullable(); // for groups
      $t->unsignedBigInteger('team_id')->nullable(); // for team_group
      $t->timestamp('last_message_at')->nullable();
      $t->unsignedBigInteger('last_message_id')->nullable();
      $t->timestamps();

      $t->index(['room_type']);
      $t->index(['team_id']);
    });
  }

  public function down(): void {
    Schema::dropIfExists('staff_chat_rooms');
  }
};

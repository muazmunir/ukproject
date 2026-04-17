<?php

// database/migrations/2026_01_29_000003_create_staff_chat_messages.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::create('staff_chat_messages', function (Blueprint $t) {
      $t->id();
      $t->foreignId('room_id')->constrained('staff_chat_rooms')->cascadeOnDelete();
      $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();

      $t->longText('body')->nullable();
      $t->string('type', 20)->default('message'); // message | attachment
      $t->json('meta')->nullable();

      $t->timestamp('edited_at')->nullable();
      $t->timestamp('deleted_at')->nullable();
      $t->timestamps();

      $t->index(['room_id','id']);
    });
  }

  public function down(): void {
    Schema::dropIfExists('staff_chat_messages');
  }
};

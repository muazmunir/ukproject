<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::create('staff_chat_attachments', function (Blueprint $t) {
      $t->id();
      $t->foreignId('message_id')->constrained('staff_chat_messages')->cascadeOnDelete();

      $t->string('disk')->default('public');
      $t->string('path');
      $t->string('name');
      $t->string('mime')->nullable();
      $t->unsignedBigInteger('size')->default(0);
      $t->timestamps();

      $t->index(['message_id']);
    });
  }

  public function down(): void {
    Schema::dropIfExists('staff_chat_attachments');
  }
};
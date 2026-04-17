<?php

// database/migrations/2026_01_23_000002_create_support_question_messages_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    Schema::create('support_question_messages', function (Blueprint $table) {
      $table->id();

      $table->unsignedBigInteger('support_question_id');
      $table->unsignedBigInteger('sender_id');      // user id
      $table->string('sender_role', 20);            // admin|manager|superadmin
      $table->text('body');

      // message | system
      $table->string('type', 20)->default('message');

      $table->json('meta')->nullable();
      $table->timestamps();

      $table->foreign('support_question_id')->references('id')->on('support_questions')->cascadeOnDelete();
      $table->foreign('sender_id')->references('id')->on('users')->cascadeOnDelete();

      $table->index(['support_question_id', 'created_at']);
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('support_question_messages');
  }
};


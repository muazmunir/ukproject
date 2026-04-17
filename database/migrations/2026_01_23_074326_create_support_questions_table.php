<?php

// database/migrations/2026_01_23_000001_create_support_questions_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    Schema::create('support_questions', function (Blueprint $table) {
      $table->id();

      $table->unsignedBigInteger('asked_by_admin_id'); // who asked (admin/superadmin)
      $table->unsignedBigInteger('assigned_manager_id')->nullable(); // optional direct assign

      $table->string('title', 180);
      $table->text('question');

      // open | answered | acknowledged | needs_more_info | closed
      $table->string('status', 30)->default('open');

      $table->timestamp('answered_at')->nullable();
      $table->timestamp('acknowledged_at')->nullable();
      $table->timestamp('closed_at')->nullable();

      $table->timestamps();

      $table->foreign('asked_by_admin_id')->references('id')->on('users')->cascadeOnDelete();
      $table->foreign('assigned_manager_id')->references('id')->on('users')->nullOnDelete();

      $table->index(['status', 'created_at']);
      $table->index(['asked_by_admin_id', 'created_at']);
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('support_questions');
  }
};



<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    Schema::create('staff_documents', function (Blueprint $t) {
      $t->id();
      $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();

      // government_id | additional
      $t->string('category', 40)->index();

      // e.g. "Passport", "National ID Front", "National Insurance Number"
      $t->string('label', 160)->nullable();

      // for text-only requirements (NI number, tax number, etc.)
      $t->text('value_text')->nullable();

      // file (optional)
      $t->string('file_path')->nullable();
      $t->string('file_original_name')->nullable();
      $t->unsignedBigInteger('file_size')->nullable();
      $t->string('file_mime', 120)->nullable();

      $t->timestamps();
      $t->index(['user_id','category']);
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('staff_documents');
  }
};

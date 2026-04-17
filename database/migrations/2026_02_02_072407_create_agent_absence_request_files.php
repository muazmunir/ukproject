<?php

// database/migrations/2026_02_02_000010_create_agent_absence_request_files.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    Schema::create('agent_absence_request_files', function (Blueprint $t) {
      $t->id();
      $t->unsignedBigInteger('request_id');
      $t->string('disk', 30)->default('public');
      $t->string('path', 255);
      $t->string('original_name', 255)->nullable();
      $t->string('mime', 120)->nullable();
      $t->unsignedBigInteger('size')->default(0);
      $t->timestamps();

      $t->index(['request_id']);
      $t->foreign('request_id')->references('id')->on('agent_absence_requests')->onDelete('cascade');
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('agent_absence_request_files');
  }
};

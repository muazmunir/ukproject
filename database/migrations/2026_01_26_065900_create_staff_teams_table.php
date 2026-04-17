<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    Schema::create('staff_teams', function (Blueprint $table) {
      $table->id();
      $table->string('name');
      $table->foreignId('manager_id')->constrained('users')->cascadeOnDelete(); // manager user
      $table->boolean('is_active')->default(true);
      $table->timestamps();

      $table->index(['manager_id', 'is_active']);
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('staff_teams');
  }
};

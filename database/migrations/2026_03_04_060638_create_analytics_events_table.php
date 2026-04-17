<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::create('analytics_events', function (Blueprint $table) {
      $table->id();
      $table->unsignedBigInteger('coach_id')->index();
      $table->string('type', 40)->index(); // profile_view | booking_page_visit | enquiry_open | enquiry_message
      $table->unsignedBigInteger('user_id')->nullable()->index();
      $table->string('session_id', 100)->nullable()->index();
      $table->string('ip', 45)->nullable();
      $table->string('user_agent', 255)->nullable();
      $table->timestamp('created_at')->useCurrent();
    });
  }

  public function down(): void {
    Schema::dropIfExists('analytics_events');
  }
};
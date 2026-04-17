<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::table('services', function (Blueprint $table) {
      $table->string('status', 20)->default('active')->after('is_active');
      $table->timestamp('archived_at')->nullable()->after('status');
      $table->softDeletes()->after('updated_at'); // adds deleted_at
    });
  }

  public function down(): void {
    Schema::table('services', function (Blueprint $table) {
      $table->dropColumn(['status','archived_at']);
      $table->dropSoftDeletes();
    });
  }
};

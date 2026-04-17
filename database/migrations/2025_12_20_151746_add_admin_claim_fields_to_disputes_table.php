<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    Schema::table('disputes', function (Blueprint $table) {
      $table->foreignId('taken_by_admin_id')->nullable()->after('status')
            ->constrained('users')->nullOnDelete();
      $table->timestamp('taken_at')->nullable()->after('taken_by_admin_id');

      $table->foreignId('resolved_by_admin_id')->nullable()->after('taken_at')
            ->constrained('users')->nullOnDelete();
      $table->timestamp('resolved_at')->nullable()->after('resolved_by_admin_id');

      $table->string('resolution_action', 32)->nullable()->after('resolved_at');
      $table->text('resolution_note')->nullable()->after('resolution_action');

      $table->index(['status', 'taken_by_admin_id']);
    });
  }

  public function down(): void
  {
    Schema::table('disputes', function (Blueprint $table) {
      $table->dropIndex(['status', 'taken_by_admin_id']);
      $table->dropConstrainedForeignId('taken_by_admin_id');
      $table->dropColumn('taken_at');
      $table->dropConstrainedForeignId('resolved_by_admin_id');
      $table->dropColumn('resolved_at');
      $table->dropColumn('resolution_action');
      $table->dropColumn('resolution_note');
    });
  }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    Schema::table('support_conversations', function (Blueprint $t) {
      // manager flow
      $t->foreignId('manager_id')
        ->nullable()
        ->after('assigned_admin_id')
        ->constrained('users')
        ->nullOnDelete();

      $t->timestamp('manager_requested_at')
        ->nullable()
        ->after('manager_id');

      // the agent who requested the manager
      $t->foreignId('manager_requested_by')
        ->nullable()
        ->after('manager_requested_at')
        ->constrained('users')
        ->nullOnDelete();

      $t->timestamp('manager_joined_at')
        ->nullable()
        ->after('manager_requested_by');

      $t->timestamp('manager_ended_at')
        ->nullable()
        ->after('manager_joined_at');

      // indexes
      $t->index(['assigned_admin_id', 'status', 'last_message_at'], 'sc_admin_status_lastmsg_idx');
      $t->index(['manager_id', 'manager_joined_at'], 'sc_manager_joined_idx');
    });
  }

  public function down(): void
  {
    Schema::table('support_conversations', function (Blueprint $t) {
      // drop indexes first
      $t->dropIndex('sc_admin_status_lastmsg_idx');
      $t->dropIndex('sc_manager_joined_idx');

      // drop FKs + columns
      $t->dropConstrainedForeignId('manager_id');
      $t->dropConstrainedForeignId('manager_requested_by');

      $t->dropColumn([
        'manager_requested_at',
        'manager_joined_at',
        'manager_ended_at',
      ]);
    });
  }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    Schema::table('support_conversations', function (Blueprint $table) {
      if (!Schema::hasColumn('support_conversations','assigned_staff_id')) {
        $table->unsignedBigInteger('assigned_staff_id')->nullable()->after('assigned_admin_id');
        $table->string('assigned_staff_role', 20)->nullable()->after('assigned_staff_id'); // admin|manager

        $table->index(['assigned_staff_id','status'], 'sc_assigned_staff_status_idx');
        $table->index(['assigned_staff_role','status'], 'sc_assigned_staff_role_status_idx');
      }
    });
  }

  public function down(): void
  {
    Schema::table('support_conversations', function (Blueprint $table) {
      if (Schema::hasColumn('support_conversations','assigned_staff_id')) {
        $table->dropIndex('sc_assigned_staff_status_idx');
        $table->dropIndex('sc_assigned_staff_role_status_idx');
        $table->dropColumn(['assigned_staff_id','assigned_staff_role']);
      }
    });
  }
};

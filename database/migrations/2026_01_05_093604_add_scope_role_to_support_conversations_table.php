<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('support_conversations', function (Blueprint $table) {
            $table->string('scope_role', 20)->nullable()->after('user_type')->index();
        });

        // ✅ Backfill existing rows (optional but recommended)
        // If you already have user_type = coach/client then copy it, else default 'client'
        DB::table('support_conversations')
            ->whereNull('scope_role')
            ->update([
                'scope_role' => DB::raw("CASE
                    WHEN user_type IN ('coach','client') THEN user_type
                    ELSE 'client'
                END")
            ]);

        // ✅ If you want to enforce NOT NULL after backfill:
        Schema::table('support_conversations', function (Blueprint $table) {
            $table->string('scope_role', 20)->nullable(false)->change();
        });

        // ✅ Helpful composite index
        Schema::table('support_conversations', function (Blueprint $table) {
            $table->index(['user_id', 'scope_role', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('support_conversations', function (Blueprint $table) {
            $table->dropIndex(['support_conversations_scope_role_index']);
            $table->dropIndex(['support_conversations_user_id_scope_role_status_index']);
            $table->dropColumn('scope_role');
        });
    }
};

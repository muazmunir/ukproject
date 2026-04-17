<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_conversations', function (Blueprint $table) {
            $table->uuid('thread_id')
                ->nullable()
                ->index()
                ->after('scope_role');
        });
    }

    public function down(): void
    {
        Schema::table('support_conversations', function (Blueprint $table) {
            $table->dropIndex(['thread_id']);
            $table->dropColumn('thread_id');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
       Schema::table('support_conversations', function (Blueprint $table) {
    $table->timestamp('closed_at')->nullable()->after('last_message_at');
    $table->unsignedBigInteger('closed_by')->nullable()->after('closed_at');
    $table->string('closed_by_role', 20)->nullable()->after('closed_by');
    $table->boolean('rating_required')->default(false)->after('closed_by_role');
    $table->boolean('auto_closed')->default(false)->after('rating_required');

    $table->index(['status', 'last_message_at']);
    $table->index(['assigned_admin_id', 'status']);
    $table->index(['user_id', 'status']);
});

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('support_conversations', function (Blueprint $table) {
    $table->dropIndex(['status', 'last_message_at']);
    $table->dropIndex(['assigned_admin_id', 'status']);
    $table->dropIndex(['user_id', 'status']);

    $table->dropColumn([
        'closed_at',
        'closed_by',
        'closed_by_role',
        'rating_required',
        'auto_closed'
    ]);
});

    }
};

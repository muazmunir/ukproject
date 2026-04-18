<?php

use App\Support\Migrations\DropsMysqlSplitForeignKeys;
use Illuminate\Database\Migrations\Migration;

/**
 * audit_db holds dispute threads, staff chat messages, staff DMs, admin logs, etc.
 * Parents (`disputes`, `staff_chat_rooms`, `users`) live on other connections.
 */
return new class extends Migration
{
    public function up(): void
    {
        $c = 'audit_db';

        DropsMysqlSplitForeignKeys::dropForTables($c, [
            'dispute_messages' => ['dispute_id', 'sender_user_id'],
            'dispute_attachments' => ['dispute_id'],
            'staff_chat_messages' => ['room_id', 'user_id'],
            'staff_dm_threads' => ['manager_id', 'agent_id'],
            'staff_dm_messages' => ['sender_id'],
            'admin_action_logs' => ['admin_user_id'],
            'admin_security_events' => ['admin_user_id', 'reviewed_by'],
            'staff_deletion_audits' => ['user_id', 'performed_by'],
        ]);
    }

    public function down(): void
    {
        //
    }
};

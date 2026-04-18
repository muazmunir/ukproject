<?php

use App\Support\Migrations\DropsMysqlSplitForeignKeys;
use Illuminate\Database\Migrations\Migration;

/**
 * comms_db references `users` (auth_db) and `services` (app_db).
 */
return new class extends Migration
{
    public function up(): void
    {
        $c = 'comms_db';

        DropsMysqlSplitForeignKeys::dropForTables($c, [
            'conversations' => ['coach_id', 'client_id', 'service_id'],
            'messages' => ['sender_id', 'service_id'],
            'support_conversation_ratings' => ['user_id'],
            'support_conversations' => ['manager_id', 'manager_requested_by'],
            'support_questions' => ['asked_by_admin_id', 'assigned_manager_id'],
            'support_question_messages' => ['sender_id'],
            'support_question_acknowledgements' => ['admin_id'],
        ]);
    }

    public function down(): void
    {
        //
    }
};

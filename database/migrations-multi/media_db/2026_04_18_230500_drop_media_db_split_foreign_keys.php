<?php

use App\Support\Migrations\DropsMysqlSplitForeignKeys;
use Illuminate\Database\Migrations\Migration;

/**
 * `staff_chat_attachments` lives on media_db while `staff_chat_messages` is on audit_db.
 */
return new class extends Migration
{
    public function up(): void
    {
        $c = 'media_db';

        DropsMysqlSplitForeignKeys::dropForTables($c, [
            'staff_chat_attachments' => ['message_id'],
        ]);
    }

    public function down(): void
    {
        //
    }
};

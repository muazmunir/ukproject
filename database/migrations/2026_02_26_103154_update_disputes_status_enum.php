<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // First convert any existing rejected rows to resolved (or open if you prefer)
        DB::table('disputes')
            ->where('status', 'rejected')
            ->update(['status' => 'resolved']);

        // Then modify ENUM
        DB::statement("
            ALTER TABLE disputes 
            MODIFY status ENUM('open','opened','in_review','resolved') 
            NOT NULL DEFAULT 'open'
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE disputes 
            MODIFY status ENUM('open','in_review','resolved','rejected') 
            NOT NULL DEFAULT 'open'
        ");
    }
};
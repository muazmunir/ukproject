<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Convert ENUM to VARCHAR(20)
        DB::statement("
            ALTER TABLE dispute_messages
            MODIFY sender_role VARCHAR(20) NOT NULL
        ");
    }

    public function down(): void
    {
        // Revert back to ENUM (only if needed)
        DB::statement("
            ALTER TABLE dispute_messages
            MODIFY sender_role ENUM('client','coach','admin') NOT NULL
        ");
    }
};
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->string('sender_role', 10)->default('client')->index()->after('sender_id');
        });

        // optional: backfill existing rows as client (or whatever you prefer)
        DB::table('messages')->whereNull('sender_role')->update(['sender_role' => 'client']);
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex(['sender_role']);
            $table->dropColumn('sender_role');
        });
    }
};

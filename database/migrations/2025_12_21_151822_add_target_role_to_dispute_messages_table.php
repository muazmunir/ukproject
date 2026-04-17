<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dispute_messages', function (Blueprint $table) {
            $table
                ->string('target_role', 20)
                ->nullable()
                ->index(); // client | coach (admin-only messages)
        });
    }

    public function down(): void
    {
        Schema::table('dispute_messages', function (Blueprint $table) {
            // drop index first (Laravel auto-names it)
            $table->dropIndex(['target_role']);

            // then drop column
            $table->dropColumn('target_role');
        });
    }
};

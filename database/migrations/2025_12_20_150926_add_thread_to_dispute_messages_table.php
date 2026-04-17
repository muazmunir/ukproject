<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('dispute_messages', function (Blueprint $table) {
            $table->enum('thread', ['client','coach'])
                  ->after('dispute_id')
                  ->index();
        });
    }

    public function down(): void
    {
        Schema::table('dispute_messages', function (Blueprint $table) {
            $table->dropIndex(['thread']);
            $table->dropColumn('thread');
        });
    }
};

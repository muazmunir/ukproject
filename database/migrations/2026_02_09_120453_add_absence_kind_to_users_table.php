<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // kind: absence | holiday
            $table->string('absence_kind', 20)->nullable()->after('absence_status');

            // (optional but recommended) who approved/changed the lock window
            // you already use absence_set_by and absence_set_at, so skip if present
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('absence_kind');
        });
    }
};

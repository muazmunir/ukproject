<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('users', function (Blueprint $t) {
            // ✅ Add new column(s)
            $t->string('avatar_path')->nullable()->after('username');
        });
    }

    public function down(): void {
        Schema::table('users', function (Blueprint $t) {
            // Rollback column if migration is reverted
            $t->dropColumn('avatar_path');
        });
    }
};

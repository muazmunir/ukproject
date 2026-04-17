<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('admin_last_seen_at')->nullable()->after('locked_reason');
            $table->timestamp('admin_soft_locked_at')->nullable()->after('admin_last_seen_at');
            $table->unsignedInteger('admin_soft_lock_count')->default(0)->after('admin_soft_locked_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['admin_last_seen_at','admin_soft_locked_at','admin_soft_lock_count']);
        });
    }
};

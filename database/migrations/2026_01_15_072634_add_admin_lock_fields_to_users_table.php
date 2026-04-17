<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Hard lock
            $table->boolean('is_locked')->default(false)->after('is_active');
            $table->timestamp('locked_at')->nullable()->after('is_locked');
            $table->string('locked_reason', 120)->nullable()->after('locked_at');
            $table->foreignId('locked_by')->nullable()->after('locked_reason')->constrained('users')->nullOnDelete();

            // Activity / soft lock support (we’ll use these in next step)
            $table->timestamp('last_activity_at')->nullable()->after('locked_by');
            $table->timestamp('soft_locked_at')->nullable()->after('last_activity_at');

            $table->index(['is_locked', 'role']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['is_locked', 'role']);

            $table->dropConstrainedForeignId('locked_by');
            $table->dropColumn([
                'is_locked',
                'locked_at',
                'locked_reason',
                'locked_by',
                'last_activity_at',
                'soft_locked_at',
            ]);
        });
    }
};

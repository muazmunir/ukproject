<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('users', function (Blueprint $t) {
            $t->boolean('is_approved')->default(false)->after('role'); // 0 = pending
            $t->timestamp('approved_at')->nullable()->after('is_approved');
        });
    }
    public function down(): void {
        Schema::table('users', function (Blueprint $t) {
            $t->dropColumn(['is_approved','approved_at']);
        });
    }
};

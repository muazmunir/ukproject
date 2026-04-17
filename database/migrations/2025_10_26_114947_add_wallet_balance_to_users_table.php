<?php
// database/migrations/xxxx_xx_xx_xxxxxx_add_wallet_balance_to_users_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('users', function (Blueprint $t) {
            $t->decimal('wallet_balance', 12, 2)->default(0)->after('role');
        });
    }
    public function down(): void {
        Schema::table('users', function (Blueprint $t) {
            $t->dropColumn('wallet_balance');
        });
    }
};

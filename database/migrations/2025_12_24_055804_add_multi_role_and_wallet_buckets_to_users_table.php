<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {

            if (!Schema::hasColumn('users', 'is_client')) {
                $table->boolean('is_client')->default(true)->after('id');
            }

            if (!Schema::hasColumn('users', 'is_coach')) {
                $table->boolean('is_coach')->default(false)->after('is_client');
            }

            if (!Schema::hasColumn('users', 'active_role')) {
                $table->string('active_role', 20)->default('client')->index()->after('is_coach');
            }

            if (!Schema::hasColumn('users', 'wallet_balance_minor')) {
                $table->unsignedBigInteger('wallet_balance_minor')->default(0)->after('active_role');
            }

            if (!Schema::hasColumn('users', 'platform_credit_minor')) {
                $table->unsignedBigInteger('platform_credit_minor')->default(0)->after('wallet_balance_minor');
            }

            if (!Schema::hasColumn('users', 'withdrawable_minor')) {
                $table->unsignedBigInteger('withdrawable_minor')->default(0)->after('platform_credit_minor');
            }

            if (!Schema::hasColumn('users', 'pending_escrow_minor')) {
                $table->unsignedBigInteger('pending_escrow_minor')->default(0)->after('withdrawable_minor');
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'active_role')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropIndex(['active_role']);
            });
        }

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'pending_escrow_minor')) $table->dropColumn('pending_escrow_minor');
            if (Schema::hasColumn('users', 'withdrawable_minor')) $table->dropColumn('withdrawable_minor');
            if (Schema::hasColumn('users', 'platform_credit_minor')) $table->dropColumn('platform_credit_minor');
            if (Schema::hasColumn('users', 'wallet_balance_minor')) $table->dropColumn('wallet_balance_minor');

            if (Schema::hasColumn('users', 'active_role')) $table->dropColumn('active_role');
            if (Schema::hasColumn('users', 'is_coach')) $table->dropColumn('is_coach');
            if (Schema::hasColumn('users', 'is_client')) $table->dropColumn('is_client');
        });
    }
};

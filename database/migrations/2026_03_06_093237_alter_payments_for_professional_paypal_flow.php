<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            if (!Schema::hasColumn('payments', 'provider_status')) {
                $table->string('provider_status', 64)->nullable()->after('status');
            }

            if (!Schema::hasColumn('payments', 'provider_order_id')) {
                $table->string('provider_order_id', 255)->nullable()->after('provider_payment_id');
            }

            if (!Schema::hasColumn('payments', 'last_webhook_event')) {
                $table->string('last_webhook_event', 120)->nullable()->after('provider_refund_id');
            }

            if (!Schema::hasColumn('payments', 'last_webhook_at')) {
                $table->timestamp('last_webhook_at')->nullable()->after('last_webhook_event');
            }

            if (!Schema::hasColumn('payments', 'refund_failure_reason')) {
                $table->text('refund_failure_reason')->nullable()->after('refund_status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $drop = [];

            foreach ([
                'provider_status',
                'provider_order_id',
                'last_webhook_event',
                'last_webhook_at',
                'refund_failure_reason',
            ] as $col) {
                if (Schema::hasColumn('payments', $col)) {
                    $drop[] = $col;
                }
            }

            if ($drop) {
                $table->dropColumn($drop);
            }
        });
    }
};
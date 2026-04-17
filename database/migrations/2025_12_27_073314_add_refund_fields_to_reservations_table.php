<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->string('refund_status')->default('none')->after('refund_total_minor');
            $table->timestamp('refund_requested_at')->nullable()->after('refund_method');
            $table->timestamp('refund_processed_at')->nullable()->after('refund_requested_at');
            $table->text('refund_error')->nullable()->after('refund_processed_at');
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn([
                'refund_status',
                'refund_requested_at',
                'refund_processed_at',
                'refund_error',
            ]);
        });
    }
};

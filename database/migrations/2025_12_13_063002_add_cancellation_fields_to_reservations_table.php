<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            // When booking is considered "booked" (after payment success)
            $table->timestamp('booked_at')->nullable()->after('created_at');

            // Cancellation metadata
            $table->string('cancelled_by')->nullable()->after('status'); // client|coach|admin|system
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancel_reason')->nullable();

            // Audit amounts
            $table->integer('refund_minor')->nullable();   // refunded to client
            $table->integer('penalty_minor')->nullable();  // charged to coach
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn([
                'booked_at',
                'cancelled_by',
                'cancelled_at',
                'cancel_reason',
                'refund_minor',
                'penalty_minor',
            ]);
        });
    }
};



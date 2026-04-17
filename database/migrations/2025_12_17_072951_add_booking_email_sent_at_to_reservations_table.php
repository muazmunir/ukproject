<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
   public function up(): void
{
    Schema::table('reservations', function (Blueprint $table) {
        $table->timestamp('client_booking_emailed_at')->nullable()->after('booked_at');
        $table->timestamp('coach_booking_emailed_at')->nullable()->after('client_booking_emailed_at');
    });
}

public function down(): void
{
    Schema::table('reservations', function (Blueprint $table) {
        $table->dropColumn(['client_booking_emailed_at','coach_booking_emailed_at']);
    });
}

};

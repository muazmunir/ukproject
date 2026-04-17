<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
{
    Schema::table('reservation_slots', function (Blueprint $table) {
        // codes
        $table->string('client_code', 10)->nullable();
        $table->string('coach_code', 10)->nullable();

        // check-in timestamps
        $table->timestamp('client_checked_in_at')->nullable();
        $table->timestamp('coach_checked_in_at')->nullable();

        // geolocation
        $table->decimal('client_lat', 10, 7)->nullable();
        $table->decimal('client_lng', 10, 7)->nullable();
        $table->decimal('coach_lat', 10, 7)->nullable();
        $table->decimal('coach_lng', 10, 7)->nullable();

        // session status for that slot
        $table->enum('session_status', [
            'pending',            // booked but not started
            'waiting_for_coach',  // client confirmed
            'started',            // both confirmed
            'no_show_coach',
            'no_show_client'
        ])->default('pending');
    });
}

public function down()
{
    Schema::table('reservation_slots', function (Blueprint $table) {
        $table->dropColumn([
            'client_code',
            'coach_code',
            'client_checked_in_at',
            'coach_checked_in_at',
            'client_lat', 'client_lng',
            'coach_lat', 'coach_lng',
            'session_status',
        ]);
    });
}

};

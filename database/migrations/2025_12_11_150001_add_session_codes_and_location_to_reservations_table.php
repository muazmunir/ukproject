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
    Schema::table('reservations', function (Blueprint $table) {
        // Codes
        $table->string('client_session_code', 10)->nullable();
        $table->timestamp('client_code_generated_at')->nullable();
        $table->timestamp('client_code_confirmed_at')->nullable();

        $table->string('coach_session_code', 10)->nullable();
        $table->timestamp('coach_code_generated_at')->nullable();
        $table->timestamp('coach_code_confirmed_at')->nullable();

        // Session “start” reference (when client confirms)
        $table->timestamp('session_start_requested_at')->nullable();

        // Location capture (lat/lng)
        $table->decimal('client_lat', 10, 7)->nullable();
        $table->decimal('client_lng', 10, 7)->nullable();

        $table->decimal('coach_lat', 10, 7)->nullable();
        $table->decimal('coach_lng', 10, 7)->nullable();

        // Session status for this check-in logic
        $table->enum('session_status', [
            'pending',            // not started yet
            'waiting_for_coach',  // client confirmed, waiting 5 min
            'started',            // both confirmed
            'no_show_coach',      // client confirmed, coach never did
            'no_show_client',     // (optional for later)
        ])->default('pending');
    });
}

public function down()
{
    Schema::table('reservations', function (Blueprint $table) {
        $table->dropColumn([
            'client_session_code',
            'client_code_generated_at',
            'client_code_confirmed_at',
            'coach_session_code',
            'coach_code_generated_at',
            'coach_code_confirmed_at',
            'session_start_requested_at',
            'client_lat', 'client_lng',
            'coach_lat', 'coach_lng',
            'session_status',
        ]);
    });
}

};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddEscrowFieldsToReservations extends Migration
{
    public function up()
    {
        Schema::table('reservations', function (Blueprint $table) {
            // when the booked slot actually begins / ends
            $table->timestamp('starts_at')->nullable()->after('environment');
            $table->timestamp('ends_at')->nullable()->after('starts_at');

            // automatic escrow release moment (ends_at + 40h)
            $table->timestamp('escrow_release_at')->nullable()->after('ends_at');

            // manual completion
            $table->timestamp('completed_by_client_at')->nullable()->after('escrow_release_at');
            $table->timestamp('completed_by_coach_at')->nullable()->after('completed_by_client_at');

            // disputes within 40h window
            $table->timestamp('disputed_by_client_at')->nullable()->after('completed_by_coach_at');
            $table->timestamp('disputed_by_coach_at')->nullable()->after('disputed_by_client_at');
        });
    }

    public function down()
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn([
                'starts_at',
                'ends_at',
                'escrow_release_at',
                'completed_by_client_at',
                'completed_by_coach_at',
                'disputed_by_client_at',
                'disputed_by_coach_at',
            ]);
        });
    }
}

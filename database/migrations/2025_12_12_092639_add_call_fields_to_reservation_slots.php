<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('reservation_slots', function (Blueprint $table) {
            $table->string('call_provider', 20)->nullable()->after('session_status'); // jaas
            $table->string('call_room', 100)->nullable()->after('call_provider');     // random room part
        });
    }

    public function down(): void
    {
        Schema::table('reservation_slots', function (Blueprint $table) {
            $table->dropColumn(['call_provider', 'call_room']);
        });
    }
};

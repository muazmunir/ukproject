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
        Schema::table('reservation_slots', function (Blueprint $table) {
            // Make sure you have doctrine/dbal installed for change():
            // composer require doctrine/dbal
            $table->string('session_status', 50)
                  ->nullable()
                  ->default(null)
                  ->change();
        });
    }

    public function down(): void
    {
        Schema::table('reservation_slots', function (Blueprint $table) {
            // Put your old definition back here if needed
            // e.g. $table->string('session_status', 20)->nullable()->change();
        });
    }


};

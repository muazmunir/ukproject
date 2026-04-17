<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('dispute_messages', function (Blueprint $table) {
            $table->string('channel', 20)->default('client')->index();
            // Allowed values: 'client' or 'coach'
        });
    }

    public function down()
    {
        Schema::table('dispute_messages', function (Blueprint $table) {
            $table->dropIndex(['channel']);
            $table->dropColumn('channel');
        });
    }
};

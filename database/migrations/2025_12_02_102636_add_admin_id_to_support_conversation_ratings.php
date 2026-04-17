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
    Schema::table('support_conversation_ratings', function (Blueprint $table) {
        $table->unsignedBigInteger('admin_id')->nullable()->after('user_id');
    });
}

public function down()
{
    Schema::table('support_conversation_ratings', function (Blueprint $table) {
        $table->dropColumn('admin_id');
    });
}

};

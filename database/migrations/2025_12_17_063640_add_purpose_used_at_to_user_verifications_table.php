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
    Schema::table('user_verifications', function (Blueprint $table) {
        $table->string('purpose')->default('register')->after('code'); // register|login
        $table->timestamp('used_at')->nullable()->after('expires_at');
    });
}

public function down(): void
{
    Schema::table('user_verifications', function (Blueprint $table) {
        $table->dropColumn(['purpose','used_at']);
    });
}

};

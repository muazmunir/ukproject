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
    Schema::table('users', function (Blueprint $table) {
        if (!Schema::hasColumn('users','support_presence')) {
            $table->string('support_presence', 20)->default('online')->after('support_status'); // online|offline
        }
        if (!Schema::hasColumn('users','support_presence_since')) {
            $table->timestamp('support_presence_since')->nullable()->after('support_presence');
        }
    });
}

public function down(): void
{
    Schema::table('users', function (Blueprint $table) {
        $table->dropColumn(['support_presence','support_presence_since']);
    });
}

};

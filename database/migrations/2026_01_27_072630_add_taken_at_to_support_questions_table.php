<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_questions', function (Blueprint $table) {
            $table->dateTime('taken_at')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('support_questions', function (Blueprint $table) {
            $table->dropColumn('taken_at');
        });
    }
};

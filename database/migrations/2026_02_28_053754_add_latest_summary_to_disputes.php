<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('disputes', function (Blueprint $table) {
            $table->text('latest_summary')->nullable()->after('resolution_note');
            $table->unsignedBigInteger('latest_summary_by_id')->nullable()->after('latest_summary');
            $table->timestamp('latest_summary_at')->nullable()->after('latest_summary_by_id');

            $table->foreign('latest_summary_by_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('disputes', function (Blueprint $table) {
            $table->dropForeign(['latest_summary_by_id']);
            $table->dropColumn(['latest_summary', 'latest_summary_by_id', 'latest_summary_at']);
        });
    }
};
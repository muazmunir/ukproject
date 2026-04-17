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
    $table->string('support_status', 30)->nullable()->after('role');
    $table->timestamp('support_status_since')->nullable()->after('support_status');

    $table->index(['role', 'support_status']);
});

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
    $table->dropIndex(['role', 'support_status']);
    $table->dropColumn(['support_status', 'support_status_since']);
});

    }
};

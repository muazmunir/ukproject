<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('absence_return_required')
                ->default(false)
                ->after('absence_set_at');

            $table->dateTime('absence_return_since')
                ->nullable()
                ->after('absence_return_required');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['absence_return_required','absence_return_since']);
        });
    }
};

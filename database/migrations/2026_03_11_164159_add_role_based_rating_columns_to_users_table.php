<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
       Schema::table('users', function (Blueprint $table) {
    $table->decimal('coach_rating_avg', 3, 2)->nullable();
    $table->unsignedInteger('coach_rating_count')->default(0);

    $table->decimal('client_rating_avg', 3, 2)->nullable();
    $table->unsignedInteger('client_rating_count')->default(0);
});
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'coach_rating_avg',
                'coach_rating_count',
                'client_rating_avg',
                'client_rating_count',
            ]);
        });
    }
};
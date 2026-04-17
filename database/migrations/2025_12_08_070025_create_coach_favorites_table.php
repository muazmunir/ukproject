<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('coach_favorites', function (Blueprint $table) {
            $table->id();

            // user who favorites
            $table->foreignId('user_id')
                  ->constrained('users')
                  ->cascadeOnDelete();

            // coach being favorited (also in users table)
            $table->foreignId('coach_id')
                  ->constrained('users')
                  ->cascadeOnDelete();

            $table->timestamps();

            $table->unique(['user_id', 'coach_id']); // prevent duplicates
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coach_favorites');
    }
};

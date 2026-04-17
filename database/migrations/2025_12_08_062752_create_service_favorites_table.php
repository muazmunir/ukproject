<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('service_favorites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                  ->constrained('users')
                  ->cascadeOnDelete();

            $table->foreignId('service_id')
                  ->constrained('services')
                  ->cascadeOnDelete();

            $table->timestamps();

            $table->unique(['user_id', 'service_id']); // no duplicates
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_favorites');
    }
};

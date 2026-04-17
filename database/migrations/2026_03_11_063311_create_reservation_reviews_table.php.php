<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('reservation_reviews', function (Blueprint $table) {
            $table->id();

            $table->foreignId('reservation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reviewer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('reviewee_id')->constrained('users')->cascadeOnDelete();

            $table->string('reviewer_role', 20); // client / coach
            $table->string('reviewee_role', 20); // coach / client

            $table->unsignedTinyInteger('stars');
            $table->text('description')->nullable();

            $table->timestamps();

            $table->unique(['reservation_id', 'reviewer_role'], 'reservation_role_unique_review');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservation_reviews');
    }
};
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->engine = 'InnoDB';

            $table->id();

            // Foreign key to users (coach)
            $table->unsignedBigInteger('coach_id');
            $table->foreign('coach_id')
                ->references('id')->on('users')
                ->onDelete('cascade')
                ->onUpdate('cascade');

            // Foreign key to service_categories
            $table->unsignedBigInteger('category_id')->nullable();
            $table->foreign('category_id')
                ->references('id')->on('service_categories')
                ->onDelete('cascade')
                ->onUpdate('cascade');

            // Main fields
            $table->string('title');
            $table->text('description')->nullable();

            $table->string('thumbnail_path')->nullable();
            $table->json('images')->nullable();

            $table->json('environments')->nullable();
            $table->string('environment_other')->nullable();

            $table->json('accessibility')->nullable();
            $table->string('accessibility_other')->nullable();

            $table->boolean('disability_accessible')->nullable();
            $table->enum('service_level', ['beginner', 'intermediate', 'advanced'])->default('beginner');

            $table->boolean('is_active')->default(true);
            $table->json('meta')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};

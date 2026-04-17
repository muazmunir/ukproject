<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('dispute_summaries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('dispute_id');
            $table->unsignedBigInteger('staff_id');
            $table->string('staff_role', 30)->nullable(); // admin|manager
            $table->text('summary');
            $table->timestamps();

            $table->foreign('dispute_id')->references('id')->on('disputes')->cascadeOnDelete();
            $table->foreign('staff_id')->references('id')->on('users')->cascadeOnDelete();

            $table->index(['dispute_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dispute_summaries');
    }
};
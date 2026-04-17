<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('support_conversation_ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('support_conversation_id')
                ->constrained()
                ->onDelete('cascade');

            $table->foreignId('user_id') // who rated (coach/client)
                ->constrained('users')
                ->onDelete('cascade');

            $table->tinyInteger('stars'); // 1–5
            $table->text('feedback')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_conversation_ratings');
    }
};


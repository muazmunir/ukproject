<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('dispute_messages', function (Blueprint $table) {
            $table->id();

            $table->foreignId('dispute_id')->constrained('disputes')->cascadeOnDelete();

            $table->foreignId('sender_user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('sender_role', ['client','coach','admin']);

            $table->text('message')->nullable();

            $table->timestamps();

            $table->index(['dispute_id','created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dispute_messages');
    }
};

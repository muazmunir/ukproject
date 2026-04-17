<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('support_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('support_conversation_id');
            $table->unsignedBigInteger('sender_id');
            $table->string('sender_type'); // 'coach','client','admin'
            $table->text('body');
            $table->timestamps();

            $table->index('support_conversation_id');
            $table->index(['sender_id', 'sender_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_messages');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('dispute_attachments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('dispute_id')->constrained('disputes')->cascadeOnDelete();
            $table->foreignId('message_id')->nullable()->constrained('dispute_messages')->cascadeOnDelete();

            $table->string('disk')->default('public');
            $table->string('path');      // storage path
            $table->string('filename');
            $table->string('mime')->nullable();
            $table->unsignedBigInteger('size')->nullable();

            $table->timestamps();

            $table->index(['dispute_id']);
            $table->index(['message_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dispute_attachments');
    }
};

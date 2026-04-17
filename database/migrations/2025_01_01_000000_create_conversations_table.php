<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();

            // Participant roles
            $table->unsignedBigInteger('coach_id');
            $table->unsignedBigInteger('client_id');

            // Link to a specific service (for “came from this service”)
            $table->unsignedBigInteger('service_id')->nullable();

            // For quick list ordering
            $table->timestamp('last_message_at')->nullable();

            $table->timestamps();

            $table->foreign('coach_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('client_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('service_id')->references('id')->on('services')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};

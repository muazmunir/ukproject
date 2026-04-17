<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('agent_status_logs', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('user_id');
    $table->string('status', 30);
    $table->string('reason', 255)->nullable();
    $table->timestamp('started_at');
    $table->timestamp('ended_at')->nullable();
    $table->timestamps();

    $table->index(['user_id','status']);
    $table->index(['started_at']);
});

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agent_status_logs');
    }
};

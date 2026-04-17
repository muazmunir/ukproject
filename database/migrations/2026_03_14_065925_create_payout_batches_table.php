<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('payout_batches', function (Blueprint $table) {
            $table->id();
            $table->string('batch_key')->unique(); // e.g. 2026-03-14-0200-utc
            $table->string('status')->default('pending');

            $table->timestamp('scheduled_for')->index();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->unsignedInteger('coach_count')->default(0);
            $table->unsignedInteger('reservation_count')->default(0);

            $table->bigInteger('gross_minor')->default(0);
            $table->bigInteger('net_minor')->default(0);
            $table->string('currency', 3)->default('USD');

            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payout_batches');
    }
};
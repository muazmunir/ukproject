<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('coach_payout_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coach_payout_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reservation_id')->constrained()->cascadeOnDelete();

            $table->bigInteger('gross_minor')->default(0);
            $table->bigInteger('platform_fee_minor')->default(0);
            $table->bigInteger('net_minor')->default(0);

            $table->timestamp('released_at')->nullable();
            $table->json('meta')->nullable();

            $table->timestamps();

            $table->unique('reservation_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coach_payout_items');
    }
};
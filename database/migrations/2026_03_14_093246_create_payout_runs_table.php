<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up(): void
    {
        Schema::create('payout_runs', function (Blueprint $table) {
            $table->id();

            $table->string('provider'); // stripe
            $table->string('run_key')->unique();

            $table->timestamp('scheduled_for')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();

            $table->string('status')->default('running');
            // running, completed, partial, failed

            $table->unsignedInteger('total_coaches')->default(0);
            $table->bigInteger('total_amount_minor')->default(0);

            $table->unsignedInteger('success_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);

            $table->text('notes')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payout_runs');
    }
};
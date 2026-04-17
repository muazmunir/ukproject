<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('coach_payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payout_batch_id')->nullable()->constrained('payout_batches')->nullOnDelete();
            $table->foreignId('coach_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('coach_payout_account_id')->nullable()->constrained()->nullOnDelete();

            $table->string('provider'); // stripe
            $table->string('currency', 3)->default('USD');

            $table->bigInteger('amount_minor');
            $table->unsignedInteger('reservation_count')->default(0);

            $table->string('status')->default('pending');
            // pending, transfer_created, payout_pending, paid, failed, reversed

            $table->string('provider_transfer_id')->nullable()->index();
            $table->string('provider_payout_id')->nullable()->index();
            $table->string('provider_balance_txn_id')->nullable();

            $table->timestamp('paid_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('failure_reason')->nullable();

            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coach_payouts');
    }
};

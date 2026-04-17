<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('refunds', function (Blueprint $table) {
            $table->id();

            $table->foreignId('reservation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();

            $table->unsignedBigInteger('requested_by_user_id')->nullable();

            $table->string('provider', 16)->nullable(); // stripe, paypal, wallet
            $table->string('method', 32)->nullable();   // wallet_credit, original_payment

            $table->unsignedBigInteger('requested_amount_minor')->default(0);
            $table->unsignedBigInteger('actual_amount_minor')->default(0);

            $table->unsignedBigInteger('wallet_amount_minor')->default(0);
            $table->unsignedBigInteger('external_amount_minor')->default(0);

            $table->string('currency', 3)->default('USD');

            $table->string('status', 32)->default('pending'); 
            // pending | processing | partial | succeeded | failed | cancelled

            $table->string('wallet_status', 32)->nullable();   
            // pending | succeeded | failed | not_applicable

            $table->string('external_status', 32)->nullable(); 
            // pending | succeeded | failed | not_applicable

            $table->string('provider_order_id', 255)->nullable();
            $table->string('provider_capture_id', 255)->nullable();
            $table->string('provider_refund_id', 255)->nullable();

            $table->string('idempotency_key', 120)->nullable()->unique();

            $table->text('failure_reason')->nullable();
            $table->json('meta')->nullable();

            $table->timestamp('requested_at')->nullable();
            $table->timestamp('processed_at')->nullable();

            $table->timestamps();

            $table->index(['reservation_id', 'status']);
            $table->index(['payment_id', 'status']);
            $table->index(['provider', 'provider_refund_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refunds');
    }
};
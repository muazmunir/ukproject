<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('coach_payout_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coach_payout_account_id')->constrained()->cascadeOnDelete();

            $table->string('provider'); // stripe
            $table->string('provider_external_account_id')->nullable()->index();

            $table->string('type')->nullable(); // bank_account, card
            $table->string('brand')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('last4', 10)->nullable();
            $table->string('country', 2)->nullable();
            $table->string('currency', 3)->nullable();

            $table->boolean('is_default')->default(true);
            $table->string('status')->default('active');

            $table->json('raw_provider_payload')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coach_payout_methods');
    }
};

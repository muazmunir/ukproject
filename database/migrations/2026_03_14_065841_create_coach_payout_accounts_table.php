<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('coach_payout_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coach_profile_id')->constrained()->cascadeOnDelete();

            $table->string('provider'); // stripe, payoneer
            $table->string('provider_account_id')->nullable()->index();

            $table->string('status')->default('draft');
            // draft, onboarding_required, pending_verification, verified, restricted, disabled

            $table->string('country', 2)->nullable();
            $table->string('default_currency', 3)->nullable();

            $table->boolean('charges_enabled')->default(false);
            $table->boolean('payouts_enabled')->default(false);

            $table->timestamp('onboarding_started_at')->nullable();
            $table->timestamp('onboarding_completed_at')->nullable();
            $table->timestamp('verified_at')->nullable();

            $table->json('requirements_currently_due')->nullable();
            $table->json('requirements_eventually_due')->nullable();
            $table->json('requirements_past_due')->nullable();
            $table->json('capabilities')->nullable();
            $table->json('raw_provider_payload')->nullable();

            $table->boolean('is_default')->default(true);
            $table->timestamps();

            $table->unique(['provider', 'provider_account_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coach_payout_accounts');
    }
};
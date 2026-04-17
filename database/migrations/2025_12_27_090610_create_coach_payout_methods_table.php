<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    Schema::create('coach_payout_methods', function (Blueprint $table) {
      $table->id();
      $table->foreignId('coach_id')->constrained('users')->cascadeOnDelete();

      $table->string('type'); // stripe | paypal
      $table->string('label')->nullable(); // "My PayPal", "Stripe Business"
      $table->json('details'); // paypal_email OR stripe_email/stripe_account_id later

      $table->boolean('is_default')->default(false);
      $table->timestamps();

      $table->index(['coach_id','type']);
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('coach_payout_methods');
  }
};

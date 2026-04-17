<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    Schema::create('coach_withdrawals', function (Blueprint $table) {
      $table->id();

      $table->foreignId('coach_id')->constrained('users')->cascadeOnDelete();
      $table->foreignId('payout_method_id')->nullable()->constrained('coach_payout_methods')->nullOnDelete();

      $table->bigInteger('amount_minor'); // cents
      $table->string('currency', 3)->default('USD');

      $table->string('method');        // stripe | paypal (snapshot)
      $table->json('payout_details');  // snapshot at time of withdrawal

      $table->string('status')->default('processing'); // processing|released|failed
      $table->timestamp('requested_at')->nullable();
      $table->timestamp('release_at')->nullable();
      $table->timestamp('released_at')->nullable();

      $table->string('provider_ref')->nullable();
      $table->text('error')->nullable();

      $table->timestamps();
      $table->index(['status','release_at']);
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('coach_withdrawals');
  }
};

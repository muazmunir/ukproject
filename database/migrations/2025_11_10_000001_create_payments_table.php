<?php
// database/migrations/2025_11_10_000001_create_payments_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::create('payments', function (Blueprint $t) {
      $t->id();
      $t->unsignedBigInteger('reservation_id')->nullable(); // we'll attach once reservation is persisted
      $t->string('provider', 16)->default('stripe');
      $t->string('method', 32)->nullable();                 // card|gpay|applepay|bank|klarna
      $t->string('status', 32)->default('requires_payment'); // requires_payment|processing|succeeded|failed|refunded
      $t->string('currency', 3)->default('USD');
      $t->integer('amount_total');                           // minor units
      $t->string('provider_payment_id', 128)->nullable();    // pi_xxx
      $t->string('provider_charge_id', 128)->nullable();     // ch_xxx (optional)
      $t->string('receipt_url', 255)->nullable();
      $t->json('meta')->nullable();
      $t->timestamp('succeeded_at')->nullable();
      $t->timestamp('refunded_at')->nullable();
      $t->timestamps();

      $t->index('reservation_id');
      $t->index('provider_payment_id');
    });
  }
  public function down(): void {
    Schema::dropIfExists('payments');
  }
};

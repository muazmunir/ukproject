<?php
// database/migrations/2025_11_10_100000_create_reservations_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::create('reservations', function (Blueprint $t) {
      $t->id();

      // relations
      $t->foreignId('service_id')->constrained('services');
      $t->foreignId('package_id')->constrained('service_packages');
      $t->foreignId('client_id')->constrained('users');           // the booking user
      $t->foreignId('coach_id')->nullable()->constrained('users'); // denormalized for quick filters (optional)

      // booking info
      $t->string('client_tz', 64)->nullable();
      $t->string('environment', 120)->nullable(); // e.g., "Gymnasium"
      $t->text('note')->nullable();

      // money (minor units, e.g., cents)
      $t->char('currency', 3)->default('USD');
      $t->unsignedInteger('subtotal_minor')->default(0);
      $t->unsignedInteger('fees_minor')->default(0);
      $t->unsignedInteger('total_minor')->default(0);
      $t->decimal('total_hours', 6, 2)->default(0);

      // statuses
      $t->string('status', 32)->default('booked');          // draft|pending_payment|booked|cancelled|completed
      $t->string('payment_status', 32)->default('paid');    // unpaid|paid|refunded|failed

      // payments linkage
      $t->string('payment_intent_id', 128)->nullable();     // Stripe PI id (pi_*)
      $t->string('provider', 16)->default('stripe');

      $t->timestamps();

      $t->index(['client_id','coach_id']);
      $t->index('payment_intent_id');
    });
  }
  public function down(): void {
    Schema::dropIfExists('reservations');
  }
};

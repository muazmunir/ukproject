<?php

// database/migrations/xxxx_xx_xx_add_settlement_fields_to_reservations_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::table('reservations', function (Blueprint $table) {
      $table->string('settlement_status', 32)->default('none')->after('payment_status');
      // none|pending|settled|refunded|cancelled

      $table->bigInteger('platform_earned_minor')->unsigned()->default(0)->after('settlement_status');
      $table->bigInteger('coach_earned_minor')->unsigned()->default(0)->after('platform_earned_minor');

      $table->bigInteger('refund_total_minor')->unsigned()->default(0)->after('coach_earned_minor');
      $table->bigInteger('client_penalty_minor')->unsigned()->default(0)->after('refund_total_minor');
      $table->bigInteger('coach_penalty_minor')->unsigned()->default(0)->after('client_penalty_minor');

      $table->timestamp('first_slot_start_utc')->nullable()->after('coach_penalty_minor');
      $table->timestamp('last_slot_end_utc')->nullable()->after('first_slot_start_utc');

      $table->json('cancel_policy_snapshot')->nullable()->after('last_slot_end_utc');
    });
  }

  public function down(): void {
    Schema::table('reservations', function (Blueprint $table) {
      $table->dropColumn([
        'settlement_status',
        'platform_earned_minor','coach_earned_minor',
        'refund_total_minor','client_penalty_minor','coach_penalty_minor',
        'first_slot_start_utc','last_slot_end_utc',
        'cancel_policy_snapshot',
      ]);
    });
  }
};


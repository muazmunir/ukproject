<?php

// database/migrations/xxxx_xx_xx_add_timer_fields_to_reservation_slots_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::table('reservation_slots', function (Blueprint $table) {
      $table->timestamp('reminder_15_sent_at')->nullable()->after('call_room');

      $table->timestamp('nudge1_sent_at')->nullable()->after('reminder_15_sent_at');
      $table->timestamp('nudge2_sent_at')->nullable()->after('nudge1_sent_at');

      $table->timestamp('wait_deadline_utc')->nullable()->after('nudge2_sent_at');      // default: start_utc + 5 min
      $table->timestamp('extended_until_utc')->nullable()->after('wait_deadline_utc');  // start_utc + 10 if extended

      $table->timestamp('auto_cancelled_at')->nullable()->after('extended_until_utc');
      $table->timestamp('finalized_at')->nullable()->after('auto_cancelled_at');        // when slot becomes final
      $table->json('info_json')->nullable()->after('finalized_at');
    });
  }

  public function down(): void {
    Schema::table('reservation_slots', function (Blueprint $table) {
      $table->dropColumn([
        'reminder_15_sent_at',
        'nudge1_sent_at','nudge2_sent_at',
        'wait_deadline_utc','extended_until_utc',
        'auto_cancelled_at','finalized_at',
        'info_json',
      ]);
    });
  }
};


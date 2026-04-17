<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
  public function up(): void
  {
    // pending -> scheduled
    DB::statement("UPDATE reservation_slots SET session_status='scheduled' WHERE session_status='pending'");

    // waiting_for_* -> waiting_*
    DB::statement("UPDATE reservation_slots SET session_status='waiting_coach' WHERE session_status='waiting_for_coach'");
    DB::statement("UPDATE reservation_slots SET session_status='waiting_client' WHERE session_status='waiting_for_client'");

    // in_progress -> live
    DB::statement("UPDATE reservation_slots SET session_status='live' WHERE session_status='in_progress'");

    // completed_no_show_both -> no_show_both
    DB::statement("UPDATE reservation_slots SET session_status='no_show_both' WHERE session_status='completed_no_show_both'");
  }

  public function down(): void
  {
    DB::statement("UPDATE reservation_slots SET session_status='pending' WHERE session_status='scheduled'");
    DB::statement("UPDATE reservation_slots SET session_status='waiting_for_coach' WHERE session_status='waiting_coach'");
    DB::statement("UPDATE reservation_slots SET session_status='waiting_for_client' WHERE session_status='waiting_client'");
    DB::statement("UPDATE reservation_slots SET session_status='in_progress' WHERE session_status='live'");
    DB::statement("UPDATE reservation_slots SET session_status='completed_no_show_both' WHERE session_status='no_show_both'");
  }
};



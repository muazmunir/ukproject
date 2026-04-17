<?php

// database/migrations/2026_02_01_000002_create_agent_absence_requests.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::create('agent_absence_requests', function (Blueprint $t) {
      $t->id();

      // relations
      $t->unsignedBigInteger('agent_id');           // admin agent
      $t->unsignedBigInteger('decided_by')->nullable(); // manager / superadmin

      // absence info
      $t->string('type', 20);                       // authorized | unauthorized
      $t->string('state', 20)->default('pending');  // pending | approved | rejected | cancelled

      $t->string('reason', 190)->nullable();
      $t->text('comments')->nullable();

      // ✅ use DATETIME (fixes MySQL default issue)
      $t->dateTime('start_at');
      $t->dateTime('end_at');

      // decision
      $t->dateTime('decided_at')->nullable();
      $t->string('decision_note', 255)->nullable();

      $t->timestamps();

      // indexes
      $t->index(['agent_id', 'state']);
      $t->index(['start_at', 'end_at']);
    });
  }

  public function down(): void {
    Schema::dropIfExists('agent_absence_requests');
  }
};

<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('reservation_slots', function (Blueprint $t) {
            $t->id();

            $t->foreignId('reservation_id')
                ->constrained('reservations')
                ->cascadeOnDelete();

            // --- Time slots (stored in UTC but using DATETIME type)
            $t->date('slot_date');                      // derived from start_utc
            $t->dateTime('start_utc');                  // no default — UTC time
            $t->dateTime('end_utc');                    // no default — UTC time

            $t->timestamps();

            $t->index(['reservation_id', 'slot_date']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('reservation_slots');
    }
};

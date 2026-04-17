<?php
// database/migrations/2025_10_31_000001_create_coach_weekly_hours_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('coach_weekly_hours', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('coach_id')->index();
            $t->unsignedTinyInteger('weekday'); // 0=Sun .. 6=Sat
            $t->time('start_time');
            $t->time('end_time');
            $t->timestamps();

            $t->unique(['coach_id','weekday','start_time','end_time']);
            $t->foreign('coach_id')->references('id')->on('users')->onDelete('cascade');
        });
    }
    public function down(): void { Schema::dropIfExists('coach_weekly_hours'); }
};

<?php
// database/migrations/2025_10_31_000003_create_coach_availability_overrides_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('coach_availability_overrides', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('coach_id')->index();
            // Overrides are *extra availability* windows (in UTC)
           // database/migrations/2025_10_31_000002_create_coach_unavailabilities_table.php
$t->timestamp('start_utc')->nullable();
$t->timestamp('end_utc')->nullable();

            $t->string('reason')->nullable();
            $t->timestamps();

            $t->index(['coach_id','start_utc']);
            $t->foreign('coach_id')->references('id')->on('users')->onDelete('cascade');
        });
    }
    public function down(): void { Schema::dropIfExists('coach_availability_overrides'); }
};

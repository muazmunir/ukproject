<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
  public function up(): void
{
    Schema::table('reservations', function (Blueprint $table) {
        $table->unsignedBigInteger('coach_comp_minor')->default(0)->after('coach_penalty_minor');
        $table->timestamp('coach_comp_created_at')->nullable()->after('coach_comp_minor');
    });
}

public function down(): void
{
    Schema::table('reservations', function (Blueprint $table) {
        $table->dropColumn(['coach_comp_minor','coach_comp_created_at']);
    });
}

};

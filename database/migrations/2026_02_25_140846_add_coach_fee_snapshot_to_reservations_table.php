<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->string('coach_fee_type', 20)->nullable()->after('fees_minor'); // percent|fixed
            $table->decimal('coach_fee_amount', 8, 2)->nullable()->after('coach_fee_type');
            $table->unsignedInteger('coach_fee_minor')->default(0)->after('coach_fee_amount');
            $table->unsignedInteger('coach_net_minor')->default(0)->after('coach_fee_minor');
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn([
                'coach_fee_type',
                'coach_fee_amount',
                'coach_fee_minor',
                'coach_net_minor',
            ]);
        });
    }
};
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

            // Coach earnings
            $table->bigInteger('coach_gross_minor')->default(0)
                  ->comment('Total amount before commission & penalties');

            $table->bigInteger('coach_commission_minor')->default(0)
                  ->comment('Commission taken from coach gross');

            // Platform amounts
            $table->bigInteger('platform_fee_minor')->default(0)
                  ->comment('Platform earnings from this reservation');

            $table->bigInteger('platform_penalty_minor')->default(0)
                  ->comment('Penalty amount charged (coach/client)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn([
                'coach_gross_minor',
                'coach_commission_minor',
                'platform_fee_minor',
                'platform_penalty_minor',
            ]);
        });
    }
};

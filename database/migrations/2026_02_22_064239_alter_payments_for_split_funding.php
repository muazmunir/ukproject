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
    Schema::table('payments', function (Blueprint $table) {
        // wallet rows won't have these
        $table->string('provider_payment_id')->nullable()->change();
        $table->string('provider_charge_id')->nullable()->change();
        $table->string('receipt_url')->nullable()->change();
        $table->string('method')->nullable()->change();

        $table->index(['reservation_id', 'provider']);
        $table->index(['provider', 'provider_payment_id']);
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};

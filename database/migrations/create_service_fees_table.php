<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('service_fees', function (Blueprint $table) {
            $table->bigIncrements('id');

            // Unique machine name, e.g. "coach_commission", "client_commission"
            $table->string('slug')->unique();

            // Label shown in admin UI, e.g. "Coach commission rate"
            $table->string('label');

            // Who pays this fee: coach or client (you can extend later if needed)
            $table->enum('party', ['coach', 'client']);

            // percent = percentage; fixed = fixed amount in currency
            $table->enum('type', ['percent', 'fixed'])->default('percent');

            // 10.00 => "10%" or "10.00 currency", depending on type
            $table->decimal('amount', 10, 2)->default(0.00);

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index('party');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_fees');
    }
};

<?php

// database/migrations/2025_11_06_000000_create_booking_fees_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('booking_fees', function (Blueprint $t) {
            $t->id();
            $t->string('code')->unique();              // e.g. service_fee, processing_fee
            $t->string('label');                       // shown to user
            $t->enum('kind', ['percent','flat']);      // percent of base, or flat currency
            $t->decimal('value', 8, 2);                // 12.50 (%) OR 1.99 ($)
            $t->enum('applies_to',['subtotal','per_day','per_booking'])->default('per_booking');
            $t->boolean('is_active')->default(true);
            $t->timestamp('starts_at')->nullable();
            $t->timestamp('ends_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('booking_fees');
    }
};

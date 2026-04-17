<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {

            // ---- Service snapshot ----
            $table->string('service_title_snapshot', 140)->after('service_id');

            // ---- Package snapshot ----
            $table->string('package_name_snapshot', 140)->after('package_id')->nullable();
            $table->decimal('package_hourly_rate', 10, 2)->nullable();
            $table->decimal('package_total_price', 10, 2)->nullable();
            $table->decimal('package_hours_per_day', 6, 2)->nullable();
            $table->integer('package_total_days')->nullable();
            $table->decimal('package_total_hours', 8, 2)->nullable();

            // ---- Pricing snapshot ----
            $table->string('currency', 3)->default('USD')->change();
            $table->integer('subtotal_minor')->change();
            $table->integer('fees_minor')->change();
            $table->integer('total_minor')->change();

            // ---- Audit helpers ----
            $table->timestamp('priced_at')->nullable()->after('total_minor');
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn([
                'service_title_snapshot',
                'package_name_snapshot',
                'package_hourly_rate',
                'package_total_price',
                'package_hours_per_day',
                'package_total_days',
                'package_total_hours',
                'priced_at',
            ]);
        });
    }
};


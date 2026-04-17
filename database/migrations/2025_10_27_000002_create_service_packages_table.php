<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('service_packages', function (Blueprint $table) {
            $table->engine = 'InnoDB';

            $table->id();

            // Foreign key to services
            $table->unsignedBigInteger('service_id');
            $table->foreign('service_id')
                ->references('id')->on('services')
                ->onDelete('cascade')
                ->onUpdate('cascade');

            $table->string('name');
            $table->decimal('hours_per_day', 5, 2)->nullable();
            $table->unsignedInteger('total_days')->nullable();
            $table->decimal('total_hours', 6, 2)->nullable();
            $table->decimal('hourly_rate', 10, 2)->nullable();
            $table->decimal('total_price', 10, 2)->nullable();

            $table->text('equipments')->nullable();
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_packages');
    }
};

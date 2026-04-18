<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('cities')) {
            return;
        }

        Schema::create('cities', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('state_id')->nullable()->index();
            $table->string('state_code', 32)->nullable();
            $table->unsignedBigInteger('country_id')->index();
            $table->char('country_code', 2)->index();
            $table->string('type', 64)->nullable();
            $table->unsignedSmallInteger('level')->nullable();
            $table->unsignedBigInteger('parent_id')->nullable()->index();
            $table->decimal('latitude', 11, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->string('native', 255)->nullable();
            $table->unsignedBigInteger('population')->nullable();
            $table->string('timezone', 64)->nullable();
            $table->longText('translations')->nullable();
            $table->timestamps();
            $table->unsignedTinyInteger('flag')->nullable();
            $table->string('wikiDataId', 32)->nullable()->unique();

            $table->index(['country_code', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cities');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // SIGNED bigint so negatives are allowed
            $table->bigInteger('withdrawable_minor')->default(0)->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // revert back to unsigned if you want
            $table->unsignedBigInteger('withdrawable_minor')->default(0)->change();
        });
    }
};


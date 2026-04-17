<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('users', function (Blueprint $table) {
            // Change boolean to signed tiny integer
            $table->tinyInteger('is_approved')
                  ->default(0)
                  ->change();
        });
    }

    public function down(): void {
        Schema::table('users', function (Blueprint $table) {
            // revert back to boolean if needed
            $table->boolean('is_approved')
                  ->default(false)
                  ->change();
        });
    }
};


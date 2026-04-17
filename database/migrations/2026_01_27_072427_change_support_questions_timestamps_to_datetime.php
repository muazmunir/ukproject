<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_questions', function (Blueprint $table) {
            $table->dateTime('answered_at')->nullable()->change();
            $table->dateTime('acknowledged_at')->nullable()->change();
            $table->dateTime('closed_at')->nullable()->change();
            $table->dateTime('created_at')->nullable()->change();
            $table->dateTime('updated_at')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('support_questions', function (Blueprint $table) {
            $table->timestamp('answered_at')->nullable()->change();
            $table->timestamp('acknowledged_at')->nullable()->change();
            $table->timestamp('closed_at')->nullable()->change();
            $table->timestamp('created_at')->nullable()->change();
            $table->timestamp('updated_at')->nullable()->change();
        });
    }
};

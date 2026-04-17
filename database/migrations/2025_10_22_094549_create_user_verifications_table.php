<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('user_verifications', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('code', 6);
            $t->dateTime('expires_at');
            $t->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('user_verifications');
    }
};

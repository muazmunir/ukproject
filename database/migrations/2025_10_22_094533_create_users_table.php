<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('users', function (Blueprint $t) {
            $t->id();

            // Auth essentials
            $t->string('email')->unique();
            $t->timestamp('email_verified_at')->nullable();
            $t->string('password');
            $t->rememberToken();

            // Role
            $t->string('role', 20)->default('client'); // client|coach|admin

            // Profile fields (put them here so you don't need a follow-up "alter" migration)
            $t->string('first_name')->nullable();
            $t->string('last_name')->nullable();
            $t->string('username')->unique()->nullable();
            
            $t->date('dob')->nullable();

            $t->string('country')->nullable();
            $t->string('city')->nullable();

            $t->string('phone_code', 10)->nullable();
            $t->string('phone', 30)->nullable();

            $t->string('timezone')->nullable();

            $t->string('short_bio', 160)->nullable();
            $t->text('description')->nullable();

            $t->json('languages')->nullable();

            $t->string('facebook_url')->nullable();
            $t->string('instagram_url')->nullable();
            $t->string('linkedin_url')->nullable();
            $t->string('twitter_url')->nullable();
            $t->string('youtube_url')->nullable();

            $t->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('users');
    }
};

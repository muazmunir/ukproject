<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('coach_verification_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coach_profile_id')->constrained()->cascadeOnDelete();

            $table->string('document_type');
            // profile_photo, passport, driving_license_front, driving_license_back

            $table->string('storage_disk')->default('public');
            $table->string('storage_path');

            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();

            $table->string('status')->default('active');
            // active, replaced, removed

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coach_verification_documents');
    }
};
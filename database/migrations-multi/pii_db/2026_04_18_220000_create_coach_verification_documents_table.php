<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Coach KYC files belong next to `coach_profiles` (pii_db). When this table lived on kyc_db,
 * inserts used `coach_profile_id` from pii_db while the FK only saw `coach_profiles` on kyc_db — 1452 errors.
 */
return new class extends Migration
{
    private function conn(): string
    {
        return 'pii_db';
    }

    public function up(): void
    {
        $c = $this->conn();

        if (Schema::connection($c)->hasTable('coach_verification_documents')) {
            return;
        }

        if (! Schema::connection($c)->hasTable('coach_profiles')) {
            return;
        }

        Schema::connection($c)->create('coach_verification_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coach_profile_id')
                ->references('id')
                ->on('coach_profiles')
                ->cascadeOnDelete();

            $table->string('document_type');

            $table->string('storage_disk')->default('public');
            $table->string('storage_path');

            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();

            $table->string('status')->default('active');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection($this->conn())->dropIfExists('coach_verification_documents');
    }
};

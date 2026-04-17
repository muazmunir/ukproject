<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('staff_deletion_audits', function (Blueprint $t) {
            $t->id();

            // which staff got deleted
            $t->foreignId('user_id')
              ->constrained('users')
              ->cascadeOnDelete();

            // who performed deletion
            $t->foreignId('performed_by')
              ->nullable()
              ->constrained('users')
              ->nullOnDelete();

            // unlimited length reason
            $t->longText('reason')->nullable();

            // attachment (single row = single attachment)
            $t->string('image_path')->nullable();
            $t->string('image_original_name')->nullable();
            $t->unsignedBigInteger('image_size')->nullable();
            $t->string('image_mime', 120)->nullable();

            $t->string('ip', 64)->nullable();
            $t->text('user_agent')->nullable();

            $t->timestamps();

            $t->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_deletion_audits');
    }
};

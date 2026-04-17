<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('admin_action_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('admin_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('action', 60)->index(); // soft_locked, soft_unlocked, hard_locked, hard_unlocked, delete_user, payment_toggle, etc

            $table->string('target_type', 120)->nullable()->index(); // e.g. App\Models\User, PaymentSetting
            $table->unsignedBigInteger('target_id')->nullable()->index();

            $table->json('meta')->nullable();

            $table->string('ip', 64)->nullable();
            $table->text('user_agent')->nullable();

            $table->timestamps();

            $table->index(['admin_user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_action_logs');
    }
};

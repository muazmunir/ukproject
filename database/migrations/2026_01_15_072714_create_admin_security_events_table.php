<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('admin_security_events', function (Blueprint $table) {
            $table->id();

            $table->foreignId('admin_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 60)->index();   // mass_deletion_lock, suspicious_activity, etc
            $table->string('status', 20)->default('open')->index(); // open/reviewed/closed

            $table->string('message', 255)->nullable();
            $table->json('meta')->nullable();

            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_security_events');
    }
};

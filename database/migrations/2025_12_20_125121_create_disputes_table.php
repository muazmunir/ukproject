<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('disputes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('reservation_id')->constrained()->cascadeOnDelete();

            // Who opened this dispute (IMPORTANT for visibility rules)
            $table->enum('opened_by_role', ['client','coach']); // client dispute / coach dispute
            $table->foreignId('opened_by_user_id')->constrained('users')->cascadeOnDelete();

            // For quick filtering (optional but useful)
            $table->foreignId('client_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('coach_id')->nullable()->constrained('users')->nullOnDelete();

            // Title selected from dropdown
            $table->string('title_key');      // e.g. "coach_late", "quality_issue", ...
            $table->string('title_label');    // store human readable label at time of creation

            $table->text('description')->nullable();

            $table->enum('status', ['open','resolved','rejected'])->default('open');

            // Admin decision
            $table->enum('decision_action', ['reject','refund_full','refund_service','pay_coach'])->nullable();
            $table->text('decision_note')->nullable();
            $table->foreignId('decided_by_admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();

            // convenience timestamps
            $table->timestamp('last_message_at')->nullable();

            // prevent duplicates: max 1 dispute per reservation per side
            $table->unique(['reservation_id','opened_by_role']);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('disputes');
    }
};

<?php

// database/migrations/2026_01_23_000003_create_support_question_acknowledgements_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_question_acknowledgements', function (Blueprint $table) {
            $table->id();

            $table->foreignId('support_question_id')
                  ->constrained('support_questions')
                  ->cascadeOnDelete();

            $table->foreignId('admin_id')
                  ->constrained('users')
                  ->cascadeOnDelete();

            // acknowledged | needs_more_info
            $table->string('status', 30);

            $table->text('note')->nullable();
            $table->timestamps();

            // prevent duplicate acknowledgement per admin per question
            $table->unique(
                ['support_question_id', 'admin_id'],
                'sq_ack_question_admin_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_question_acknowledgements');
    }
};

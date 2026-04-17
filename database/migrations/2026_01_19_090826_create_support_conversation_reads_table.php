<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    Schema::create('support_conversation_reads', function (Blueprint $t) {
      $t->id();

      $t->foreignId('support_conversation_id')
        ->constrained('support_conversations')
        ->cascadeOnDelete();

      $t->foreignId('admin_id')
        ->constrained('users')
        ->cascadeOnDelete();

      // WhatsApp-style: "read up to this message"
      $t->unsignedBigInteger('last_read_message_id')->default(0);

      $t->timestamp('last_read_at')->nullable();
      $t->timestamps();

      $t->unique(
        ['support_conversation_id', 'admin_id'],
        'scr_unique'
      );

      $t->index(
        ['admin_id', 'support_conversation_id'],
        'scr_admin_conv'
      );
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('support_conversation_reads');
  }
};

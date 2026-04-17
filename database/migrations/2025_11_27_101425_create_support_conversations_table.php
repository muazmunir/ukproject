<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('support_conversations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id'); // coach/client
            $table->string('user_type')->nullable(); // 'coach' / 'client' (optional)
            $table->unsignedBigInteger('assigned_admin_id')->nullable(); // which admin is serving
            $table->string('status')->default('open'); // open / pending / closed
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('assigned_admin_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_conversations');
    }
};


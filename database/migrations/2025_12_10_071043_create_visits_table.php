<?php
// database/migrations/xxxx_xx_xx_xxxxxx_create_visits_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('visits', function (Blueprint $table) {
            $table->id();
            $table->string('visitor_id', 64)->index(); // from cookie
            $table->unsignedBigInteger('user_id')->nullable()->index(); // logged in user
            $table->string('ip', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->string('path')->nullable();
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable()->index();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visits');
    }
};

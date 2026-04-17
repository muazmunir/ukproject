<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('service_faqs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('service_id')->constrained('services')->cascadeOnDelete();
            $t->string('question');
            $t->text('answer');
            $t->unsignedInteger('sort_order')->default(0);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_faqs');
    }
};

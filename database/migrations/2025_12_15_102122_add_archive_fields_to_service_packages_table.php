<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
   public function up(): void
{
    Schema::table('service_packages', function (Blueprint $table) {
        $table->boolean('is_active')->default(true)->after('total_price');
        $table->timestamp('archived_at')->nullable()->after('is_active');
        $table->softDeletes(); // recommended
    });
}

public function down(): void
{
    Schema::table('service_packages', function (Blueprint $table) {
        $table->dropColumn(['is_active', 'archived_at']);
        $table->dropSoftDeletes();
    });
}

};

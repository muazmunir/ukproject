<?php
// database/migrations/2025_11_16_000001_add_media_to_service_categories.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('service_categories', function (Blueprint $t) {
            if (!Schema::hasColumn('service_categories', 'description')) {
                $t->text('description')->nullable()->after('slug');
            }
            if (!Schema::hasColumn('service_categories', 'cover_image')) {
                $t->string('cover_image')->nullable()->after('description');
            }
            if (!Schema::hasColumn('service_categories', 'icon_path')) {
                $t->string('icon_path')->nullable()->after('cover_image');
            }
        });
    }

    public function down(): void {
        Schema::table('service_categories', function (Blueprint $t) {
            if (Schema::hasColumn('service_categories', 'icon_path')) $t->dropColumn('icon_path');
            if (Schema::hasColumn('service_categories', 'cover_image')) $t->dropColumn('cover_image');
            if (Schema::hasColumn('service_categories', 'description')) $t->dropColumn('description');
        });
    }
};

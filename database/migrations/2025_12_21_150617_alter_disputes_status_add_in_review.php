// php artisan make:migration alter_disputes_status_add_in_review
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement("ALTER TABLE disputes MODIFY status ENUM('open','in_review','resolved','rejected') NOT NULL DEFAULT 'open'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE disputes MODIFY status ENUM('open','resolved','rejected') NOT NULL DEFAULT 'open'");
    }
};

<?php
// database/migrations/2025_11_02_000001_fix_unavailability_to_datetime.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // 1) Work in UTC so TIMESTAMP→DATETIME keeps UTC wall times
        DB::statement("SET time_zone = '+00:00'");

        // 2) Add temp DATETIME columns
        Schema::table('coach_unavailabilities', function (Blueprint $t) {
            $t->dateTime('start_utc_dt')->nullable()->after('coach_id');
            $t->dateTime('end_utc_dt')->nullable()->after('start_utc_dt');
        });

        // 3) Copy data from TIMESTAMP to DATETIME in UTC
        DB::statement("
            UPDATE coach_unavailabilities
            SET
              start_utc_dt = FROM_UNIXTIME(UNIX_TIMESTAMP(start_utc)),
              end_utc_dt   = FROM_UNIXTIME(UNIX_TIMESTAMP(end_utc))
        ");

        // 4) Drop old TIMESTAMP columns and rename the new ones
        Schema::table('coach_unavailabilities', function (Blueprint $t) {
            $t->dropColumn(['start_utc', 'end_utc']);
        });

        // doctrine/dbal is needed for renameColumn; if not installed, use a second ALTER
        Schema::table('coach_unavailabilities', function (Blueprint $t) {
            $t->renameColumn('start_utc_dt', 'start_utc');
            $t->renameColumn('end_utc_dt', 'end_utc');
        });
    }

    public function down(): void
    {
        // reverse (DATETIME → TIMESTAMP, still in UTC)
        DB::statement("SET time_zone = '+00:00'");

        Schema::table('coach_unavailabilities', function (Blueprint $t) {
            $t->timestamp('start_utc_ts')->nullable()->after('coach_id');
            $t->timestamp('end_utc_ts')->nullable()->after('start_utc_ts');
        });

        DB::statement("
            UPDATE coach_unavailabilities
            SET
              start_utc_ts = start_utc,
              end_utc_ts   = end_utc
        ");

        Schema::table('coach_unavailabilities', function (Blueprint $t) {
            $t->dropColumn(['start_utc', 'end_utc']);
        });

        Schema::table('coach_unavailabilities', function (Blueprint $t) {
            $t->renameColumn('start_utc_ts', 'start_utc');
            $t->renameColumn('end_utc_ts', 'end_utc');
        });
    }
};

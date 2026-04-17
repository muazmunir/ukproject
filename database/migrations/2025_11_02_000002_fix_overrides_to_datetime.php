<?php
// database/migrations/2025_11_02_000002_fix_overrides_to_datetime.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Work in UTC so TIMESTAMP → DATETIME keeps the correct wall time
        DB::statement("SET time_zone = '+00:00'");

        // 1) Add temp DATETIME columns
        Schema::table('coach_availability_overrides', function (Blueprint $t) {
            $t->dateTime('start_utc_dt')->nullable()->after('coach_id');
            $t->dateTime('end_utc_dt')->nullable()->after('start_utc_dt');
        });

        // 2) Copy TIMESTAMP → DATETIME using UNIX timestamp
        DB::statement("
            UPDATE coach_availability_overrides
            SET
              start_utc_dt = FROM_UNIXTIME(UNIX_TIMESTAMP(start_utc)),
              end_utc_dt   = FROM_UNIXTIME(UNIX_TIMESTAMP(end_utc))
        ");

        // 3) Drop old columns
        Schema::table('coach_availability_overrides', function (Blueprint $t) {
            $t->dropColumn(['start_utc', 'end_utc']);
        });

        // 4) Rename temp columns
        Schema::table('coach_availability_overrides', function (Blueprint $t) {
            $t->renameColumn('start_utc_dt', 'start_utc');
            $t->renameColumn('end_utc_dt', 'end_utc');
        });
    }

    public function down(): void
    {
        DB::statement("SET time_zone = '+00:00'");

        Schema::table('coach_availability_overrides', function (Blueprint $t) {
            $t->timestamp('start_utc_ts')->nullable()->after('coach_id');
            $t->timestamp('end_utc_ts')->nullable()->after('start_utc_ts');
        });

        DB::statement("
            UPDATE coach_availability_overrides
            SET
              start_utc_ts = start_utc,
              end_utc_ts   = end_utc
        ");

        Schema::table('coach_availability_overrides', function (Blueprint $t) {
            $t->dropColumn(['start_utc', 'end_utc']);
        });

        Schema::table('coach_availability_overrides', function (Blueprint $t) {
            $t->renameColumn('start_utc_ts', 'start_utc');
            $t->renameColumn('end_utc_ts', 'end_utc');
        });
    }
};

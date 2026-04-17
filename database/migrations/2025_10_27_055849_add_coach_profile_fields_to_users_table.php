<?php
// database/migrations/xxxx_add_coach_profile_fields_to_users_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::table('users', function (Blueprint $t) {
      $t->json('coach_gallery')->nullable()->after('avatar_path');     // ["gallery/..jpg", ...]
      $t->json('coach_service_areas')->nullable()->after('coach_gallery'); // ["Karachi","Islamabad"]
      $t->json('coach_qualifications')->nullable()->after('coach_service_areas');
      // each qualification item: {title, achieved_at, description}
    });
  }
  public function down(): void {
    Schema::table('users', function (Blueprint $t) {
      $t->dropColumn(['coach_gallery','coach_service_areas','coach_qualifications']);
    });
  }
};

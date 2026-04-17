<?php

// database/migrations/xxxx_xx_xx_add_coach_kyc_fields_to_users_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    Schema::table('users', function (Blueprint $table) {
      // KYC flags
      $table->boolean('coach_kyc_submitted')->default(false)->after('onboarding_completed');
      $table->enum('coach_verification_status', ['pending','approved','rejected'])
            ->default('pending')
            ->after('coach_kyc_submitted');

      // Profile + ID type
      $table->string('coach_profile_photo')->nullable()->after('coach_verification_status');
      $table->enum('coach_id_type', ['passport','driving_license'])->nullable()->after('coach_profile_photo');

      // Passport (1 image)
      $table->string('coach_passport_image')->nullable()->after('coach_id_type');

      // Driving license (2 images)
      $table->string('coach_dl_front')->nullable()->after('coach_passport_image');
      $table->string('coach_dl_back')->nullable()->after('coach_dl_front');
    });
  }

  public function down(): void
  {
    Schema::table('users', function (Blueprint $table) {
      $table->dropColumn([
        'coach_kyc_submitted',
        'coach_verification_status',
        'coach_profile_photo',
        'coach_id_type',
        'coach_passport_image',
        'coach_dl_front',
        'coach_dl_back',
      ]);
    });
  }
};

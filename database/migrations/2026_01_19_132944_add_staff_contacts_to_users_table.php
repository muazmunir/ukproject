<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    Schema::table('users', function (Blueprint $t) {
      $t->string('emergency_contact_name', 120)->nullable()->after('phone');
      $t->string('emergency_contact_phone', 40)->nullable()->after('emergency_contact_name');

      $t->string('next_of_kin_name', 120)->nullable()->after('emergency_contact_phone');
      $t->string('next_of_kin_phone', 40)->nullable()->after('next_of_kin_name');
    });
  }

  public function down(): void
  {
    Schema::table('users', function (Blueprint $t) {
      $t->dropColumn([
        'emergency_contact_name',
        'emergency_contact_phone',
        'next_of_kin_name',
        'next_of_kin_phone',
      ]);
    });
  }
};

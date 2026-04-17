<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservation_reviews', function (Blueprint $table) {
            $table->index(['reviewee_id', 'reviewee_role'], 'rr_reviewee_role_idx');
            $table->index(['reviewer_id', 'reviewer_role'], 'rr_reviewer_role_idx');

            $table->unique([
                'reservation_id',
                'reviewer_id',
                'reviewer_role',
                'reviewee_id',
                'reviewee_role',
            ], 'rr_unique_reservation_role_review');
        });
    }

    public function down(): void
    {
        Schema::table('reservation_reviews', function (Blueprint $table) {
            $table->dropUnique('rr_unique_reservation_role_review');
            $table->dropIndex('rr_reviewee_role_idx');
            $table->dropIndex('rr_reviewer_role_idx');
        });
    }
};
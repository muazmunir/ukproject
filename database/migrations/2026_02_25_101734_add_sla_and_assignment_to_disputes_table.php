<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('disputes', function (Blueprint $table) {

            // =====================
            // Assignment Tracking
            // =====================
            $table->unsignedBigInteger('assigned_admin_id')
                  ->nullable()
                  ->index();

          

            $table->timestamp('assigned_at')->nullable();
           

            // =====================
            // SLA Deadlines
            // =====================
            $table->timestamp('sla_first_response_due_at')->nullable();
            $table->timestamp('sla_resolution_due_at')->nullable();

            // =====================
            // SLA Actual Times
            // =====================
            $table->timestamp('first_admin_response_at')->nullable();
          

           

            // =====================
            // SLA Flags
            // =====================
            $table->boolean('sla_first_response_breached')->default(false);
            $table->boolean('sla_resolution_breached')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('disputes', function (Blueprint $table) {

            $table->dropIndex(['assigned_admin_id']);
            $table->dropIndex(['taken_by_admin_id']);

            $table->dropColumn([
                'assigned_admin_id',
                
                'assigned_at',
               
                'sla_first_response_due_at',
                'sla_resolution_due_at',
                'first_admin_response_at',
                
                
                'sla_first_response_breached',
                'sla_resolution_breached',
            ]);
        });
    }
};
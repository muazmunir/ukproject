<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('analytics_events', function (Blueprint $table) {
            if (! Schema::hasColumn('analytics_events', 'client_id')) {
                $table->unsignedBigInteger('client_id')->nullable()->after('user_id')->index();
            }

            if (! Schema::hasColumn('analytics_events', 'service_id')) {
                $table->unsignedBigInteger('service_id')->nullable()->after('client_id')->index();
            }

            if (! Schema::hasColumn('analytics_events', 'reservation_id')) {
                $table->unsignedBigInteger('reservation_id')->nullable()->after('service_id')->index();
            }

            if (! Schema::hasColumn('analytics_events', 'payment_id')) {
                $table->unsignedBigInteger('payment_id')->nullable()->after('reservation_id')->index();
            }

            if (! Schema::hasColumn('analytics_events', 'visitor_token')) {
                $table->string('visitor_token', 100)->nullable()->after('session_id')->index();
            }

            if (! Schema::hasColumn('analytics_events', 'event_group')) {
                $table->string('event_group', 60)->nullable()->after('type')->index();
            }

            if (! Schema::hasColumn('analytics_events', 'page')) {
                $table->string('page', 255)->nullable()->after('event_group');
            }

            if (! Schema::hasColumn('analytics_events', 'url')) {
                $table->text('url')->nullable()->after('page');
            }

            if (! Schema::hasColumn('analytics_events', 'method')) {
                $table->string('method', 20)->nullable()->after('url');
            }

            if (! Schema::hasColumn('analytics_events', 'device_type')) {
                $table->string('device_type', 50)->nullable()->after('user_agent');
            }

            if (! Schema::hasColumn('analytics_events', 'platform')) {
                $table->string('platform', 50)->nullable()->after('device_type');
            }

            if (! Schema::hasColumn('analytics_events', 'country')) {
                $table->string('country', 100)->nullable()->after('platform');
            }

            if (! Schema::hasColumn('analytics_events', 'city')) {
                $table->string('city', 100)->nullable()->after('country');
            }

            if (! Schema::hasColumn('analytics_events', 'meta')) {
                $table->json('meta')->nullable()->after('city');
            }

            if (! Schema::hasColumn('analytics_events', 'updated_at')) {
                $table->timestamp('updated_at')->nullable()->after('created_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('analytics_events', function (Blueprint $table) {
            $drop = [];

            foreach ([
                'client_id',
                'service_id',
                'reservation_id',
                'payment_id',
                'visitor_token',
                'event_group',
                'page',
                'url',
                'method',
                'device_type',
                'platform',
                'country',
                'city',
                'meta',
                'updated_at',
            ] as $col) {
                if (Schema::hasColumn('analytics_events', $col)) {
                    $drop[] = $col;
                }
            }

            if (! empty($drop)) {
                $table->dropColumn($drop);
            }
        });
    }
};
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('audit_logs')) {
            return;
        }

        Schema::table('audit_logs', function (Blueprint $table): void {
            if (! Schema::hasColumn('audit_logs', 'location_source')) {
                $table->string('location_source', 20)->nullable()->after('ip_address');
            }
            if (! Schema::hasColumn('audit_logs', 'location_label')) {
                $table->string('location_label', 255)->nullable()->after('location_source');
            }
            if (! Schema::hasColumn('audit_logs', 'location_latitude')) {
                $table->decimal('location_latitude', 10, 7)->nullable()->after('location_label');
            }
            if (! Schema::hasColumn('audit_logs', 'location_longitude')) {
                $table->decimal('location_longitude', 10, 7)->nullable()->after('location_latitude');
            }
            if (! Schema::hasColumn('audit_logs', 'location_accuracy_meters')) {
                $table->decimal('location_accuracy_meters', 10, 2)->nullable()->after('location_longitude');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('audit_logs')) {
            return;
        }

        Schema::table('audit_logs', function (Blueprint $table): void {
            foreach ([
                'location_source',
                'location_label',
                'location_latitude',
                'location_longitude',
                'location_accuracy_meters',
            ] as $column) {
                if (Schema::hasColumn('audit_logs', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};

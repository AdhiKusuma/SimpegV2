<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('audit_logs') && ! Schema::hasIndex('audit_logs', 'audit_logs_created_id_index')) {
            Schema::table('audit_logs', function (Blueprint $table): void {
                $table->index(['created_at', 'id'], 'audit_logs_created_id_index');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('audit_logs') && Schema::hasIndex('audit_logs', 'audit_logs_created_id_index')) {
            Schema::table('audit_logs', function (Blueprint $table): void {
                $table->dropIndex('audit_logs_created_id_index');
            });
        }
    }
};

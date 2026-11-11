<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('payroll_publications')) {
            return;
        }

        Schema::table('payroll_publications', function (Blueprint $table): void {
            if (! Schema::hasColumn('payroll_publications', 'deleted_at')) {
                $table->timestamp('deleted_at')->nullable()->after('sent_at');
            }
            if (! Schema::hasColumn('payroll_publications', 'deleted_by')) {
                $table->string('deleted_by', 64)->nullable()->after('updated_by');
            }
            if (! Schema::hasColumn('payroll_publications', 'deletion_reason')) {
                $table->text('deletion_reason')->nullable()->after('deleted_by');
            }
            $table->index(['status', 'deleted_at'], 'payroll_publication_deleted_index');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('payroll_publications')) {
            return;
        }

        Schema::table('payroll_publications', function (Blueprint $table): void {
            if (Schema::hasIndex('payroll_publications', 'payroll_publication_deleted_index')) {
                $table->dropIndex('payroll_publication_deleted_index');
            }
            foreach (['deletion_reason', 'deleted_by', 'deleted_at'] as $column) {
                if (Schema::hasColumn('payroll_publications', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};

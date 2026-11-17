<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('payroll_history_cleanup_logs')) {
            return;
        }

        Schema::create('payroll_history_cleanup_logs', function (Blueprint $table): void {
            $table->id();
            // No foreign key: the source payroll_import row is intentionally deleted.
            $table->unsignedBigInteger('payroll_import_id')->nullable();
            $table->unsignedSmallInteger('tahun');
            $table->unsignedTinyInteger('bulan');
            $table->string('source_filename', 255)->nullable();
            $table->char('source_sha256', 64)->nullable();
            $table->string('deleted_by', 64)->nullable();
            $table->string('reason', 255);
            $table->timestamp('deleted_at');
            $table->timestamps();

            $table->index(['tahun', 'bulan'], 'payroll_history_cleanup_period_index');
            $table->index('payroll_import_id', 'payroll_history_cleanup_import_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_history_cleanup_logs');
    }
};

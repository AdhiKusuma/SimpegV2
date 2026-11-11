<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('payroll_publications')) {
            return;
        }

        Schema::create('payroll_publications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payroll_import_id')->unique()->constrained('payroll_imports')->cascadeOnDelete();
            $table->string('mode', 20)->default('immediate');
            $table->string('status', 20)->default('sent');
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->unsignedInteger('total_recipients')->default(0);
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->string('created_by', 64)->nullable();
            $table->string('updated_by', 64)->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
            $table->index(['status', 'scheduled_at'], 'payroll_publication_due_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_publications');
    }
};

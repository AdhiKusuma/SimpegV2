<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('rbac_permissions')) {
            Schema::create('rbac_permissions', function (Blueprint $table): void {
                $table->id();
                $table->string('code', 100)->unique();
                $table->string('label', 160);
                $table->string('group_name', 80);
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('rbac_access_rules')) {
            Schema::create('rbac_access_rules', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('permission_id')->constrained('rbac_permissions')->cascadeOnDelete();
                $table->string('subject_type', 24);
                $table->string('subject_value', 160);
                $table->boolean('allowed')->default(true);
                $table->timestamps();
                $table->unique(['permission_id', 'subject_type', 'subject_value'], 'rbac_rule_subject_permission_unique');
                $table->index(['subject_type', 'subject_value'], 'rbac_rule_subject_index');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('rbac_access_rules');
        Schema::dropIfExists('rbac_permissions');
    }
};

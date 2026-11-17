<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('audit_logs')) {
            return;
        }

        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('actor_id', 64)->nullable();
            $table->string('actor_name', 120)->nullable();
            $table->string('actor_role', 32)->nullable();
            $table->string('module', 80);
            $table->string('action', 80);
            $table->text('description');
            $table->string('entity_type', 80)->nullable();
            $table->string('entity_id', 160)->nullable();
            $table->string('route_name', 160)->nullable();
            $table->string('method', 10);
            $table->text('url')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['created_at', 'module'], 'audit_logs_created_module_index');
            $table->index(['actor_id', 'created_at'], 'audit_logs_actor_created_index');
            $table->index(['action', 'created_at'], 'audit_logs_action_created_index');
            $table->index(['entity_type', 'entity_id'], 'audit_logs_entity_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};

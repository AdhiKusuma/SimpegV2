<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tb_config') || Schema::hasColumn('tb_config', 'density')) {
            return;
        }

        Schema::table('tb_config', function (Blueprint $table): void {
            $table->string('density', 16)->default('comfortable')->after('font_size');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('tb_config') && Schema::hasColumn('tb_config', 'density')) {
            Schema::table('tb_config', function (Blueprint $table): void {
                $table->dropColumn('density');
            });
        }
    }
};

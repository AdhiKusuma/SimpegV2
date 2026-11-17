<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tb_config')) {
            return;
        }

        if (! Schema::hasColumn('tb_config', 'sidebar_color')) {
            Schema::table('tb_config', function (Blueprint $table): void {
                $table->string('sidebar_color', 16)->nullable()->after('logo_dark');
            });
        }

        if (! Schema::hasColumn('tb_config', 'font_size')) {
            Schema::table('tb_config', function (Blueprint $table): void {
                $table->string('font_size', 16)->nullable()->after('sidebar_color');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('tb_config')) {
            return;
        }

        if (Schema::hasColumn('tb_config', 'font_size')) {
            Schema::table('tb_config', function (Blueprint $table): void {
                $table->dropColumn('font_size');
            });
        }

        if (Schema::hasColumn('tb_config', 'sidebar_color')) {
            Schema::table('tb_config', function (Blueprint $table): void {
                $table->dropColumn('sidebar_color');
            });
        }
    }
};

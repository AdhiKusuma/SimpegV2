<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tb_config') && ! Schema::hasColumn('tb_config', 'logo_dark')) {
            Schema::table('tb_config', function (Blueprint $table): void {
                $table->string('logo_dark')->nullable()->after('logo');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('tb_config') && Schema::hasColumn('tb_config', 'logo_dark')) {
            Schema::table('tb_config', function (Blueprint $table): void {
                $table->dropColumn('logo_dark');
            });
        }
    }
};

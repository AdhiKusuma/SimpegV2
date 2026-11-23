<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tb_angkat') || Schema::hasColumn('tb_angkat', 'tmt_selesai')) {
            return;
        }

        Schema::table('tb_angkat', function (Blueprint $table): void {
            $table->date('tmt_selesai')->nullable()->after('tmt');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('tb_angkat') || ! Schema::hasColumn('tb_angkat', 'tmt_selesai')) {
            return;
        }

        Schema::table('tb_angkat', function (Blueprint $table): void {
            $table->dropColumn('tmt_selesai');
        });
    }
};

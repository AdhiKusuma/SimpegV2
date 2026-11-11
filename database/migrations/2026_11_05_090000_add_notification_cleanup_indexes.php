<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tb_notifikasi')) {
            return;
        }

        if (! Schema::hasIndex('tb_notifikasi', 'tb_notifikasi_user_read_time_index')) {
            Schema::table('tb_notifikasi', function (Blueprint $table): void {
                $table->index(
                    ['id_user', 'status_baca', 'waktu_notif'],
                    'tb_notifikasi_user_read_time_index',
                );
            });
        }

        if (! Schema::hasIndex('tb_notifikasi', 'tb_notifikasi_cleanup_index')) {
            Schema::table('tb_notifikasi', function (Blueprint $table): void {
                $table->index(
                    ['status_baca', 'waktu_notif', 'id_notif'],
                    'tb_notifikasi_cleanup_index',
                );
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('tb_notifikasi')) {
            return;
        }

        foreach (['tb_notifikasi_user_read_time_index', 'tb_notifikasi_cleanup_index'] as $index) {
            if (Schema::hasIndex('tb_notifikasi', $index)) {
                Schema::table('tb_notifikasi', function (Blueprint $table) use ($index): void {
                    $table->dropIndex($index);
                });
            }
        }
    }
};

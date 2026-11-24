<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->addIndexIfMissing('tb_pegawai', 'tb_pegawai_status_nama_index', ['status_aktif', 'nama']);
        $this->addIndexIfMissing('tb_pegawai', 'tb_pegawai_nama_id_index', ['nama', 'id_peg']);

        $this->addIndexIfMissing('tb_jabatan', 'tb_jabatan_pegawai_status_index', ['id_peg', 'status_jab']);
        $this->addIndexIfMissing('tb_jabatan', 'tb_jabatan_status_unit_pegawai_index', ['status_jab', 'unit_kerja', 'id_peg']);

        $this->addIndexIfMissing('tb_mutasi', 'tb_mutasi_pegawai_tmt_index', ['id_peg', 'tmt']);
        $this->addIndexIfMissing('tb_mutasi', 'tb_mutasi_jenis_tanggal_index', ['jns_mutasi', 'tgl_mutasi']);

        $this->addIndexIfMissing('tb_pendidikan', 'tb_pendidikan_pegawai_jenjang_index', ['id_peg', 'jenjang']);
        $this->addIndexIfMissing('tb_hukuman', 'tb_hukuman_tanggal_pegawai_index', ['tgl_sk', 'id_peg']);
    }

    public function down(): void
    {
        foreach ([
            'tb_pegawai' => ['tb_pegawai_status_nama_index', 'tb_pegawai_nama_id_index'],
            'tb_jabatan' => ['tb_jabatan_pegawai_status_index', 'tb_jabatan_status_unit_pegawai_index'],
            'tb_mutasi' => ['tb_mutasi_pegawai_tmt_index', 'tb_mutasi_jenis_tanggal_index'],
            'tb_pendidikan' => ['tb_pendidikan_pegawai_jenjang_index'],
            'tb_hukuman' => ['tb_hukuman_tanggal_pegawai_index'],
        ] as $table => $indexes) {
            foreach ($indexes as $index) {
                $this->dropIndexIfExists($table, $index);
            }
        }
    }

    private function addIndexIfMissing(string $table, string $index, array $columns): void
    {
        if (! Schema::hasTable($table) || ! collect($columns)->every(fn (string $column): bool => Schema::hasColumn($table, $column))) {
            return;
        }

        if ($this->indexExists($table, $index)) {
            return;
        }

        Schema::table($table, fn (Blueprint $blueprint) => $blueprint->index($columns, $index));
    }

    private function dropIndexIfExists(string $table, string $index): void
    {
        if (! Schema::hasTable($table) || ! $this->indexExists($table, $index)) {
            return;
        }

        Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropIndex($index));
    }

    private function indexExists(string $table, string $index): bool
    {
        $safeTable = str_replace('`', '``', $table);

        return collect(DB::select('SHOW INDEX FROM `'.$safeTable.'`'))
            ->contains(fn (object $row): bool => (string) ($row->Key_name ?? '') === $index);
    }
};

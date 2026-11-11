<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tb_biaya_pendidikan')) {
            Schema::table('tb_biaya_pendidikan', function (Blueprint $table): void {
                if (! Schema::hasColumn('tb_biaya_pendidikan', 'tipe_pelaksana')) {
                    $table->string('tipe_pelaksana', 100)->nullable()->after('pihak_pelaksana');
                }

                if (! Schema::hasColumn('tb_biaya_pendidikan', 'durasi_hari')) {
                    $table->unsignedInteger('durasi_hari')->nullable()->after('waktu_pelaksanaan');
                }
            });
        }

        if (! Schema::hasTable('tb_diklat')) {
            return;
        }

        Schema::table('tb_diklat', function (Blueprint $table): void {
            if (! Schema::hasColumn('tb_diklat', 'diklat_id')) {
                $table->integer('diklat_id')->nullable()->after('id_peg');
            }

            if (! Schema::hasColumn('tb_diklat', 'biaya_per_peserta')) {
                $table->integer('biaya_per_peserta')->nullable()->after('biaya');
            }
        });

        if (! $this->indexExists('tb_diklat', 'tb_diklat_diklat_id_index')) {
            Schema::table('tb_diklat', fn (Blueprint $table) => $table->index('diklat_id', 'tb_diklat_diklat_id_index'));
        }

        if (! $this->indexExists('tb_diklat', 'tb_diklat_id_peg_diklat_id_unique')) {
            Schema::table('tb_diklat', fn (Blueprint $table) => $table->unique(['id_peg', 'diklat_id'], 'tb_diklat_id_peg_diklat_id_unique'));
        }

        if (Schema::hasTable('tb_biaya_pendidikan') && ! $this->foreignKeyExists('tb_diklat', 'tb_diklat_diklat_id_foreign')) {
            Schema::table('tb_diklat', function (Blueprint $table): void {
                $table->foreign('diklat_id', 'tb_diklat_diklat_id_foreign')
                    ->references('biaya_id')
                    ->on('tb_biaya_pendidikan')
                    ->restrictOnDelete()
                    ->cascadeOnUpdate();
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('tb_diklat')) {
            return;
        }

        if ($this->foreignKeyExists('tb_diklat', 'tb_diklat_diklat_id_foreign')) {
            Schema::table('tb_diklat', fn (Blueprint $table) => $table->dropForeign('tb_diklat_diklat_id_foreign'));
        }

        if ($this->indexExists('tb_diklat', 'tb_diklat_id_peg_diklat_id_unique')) {
            Schema::table('tb_diklat', fn (Blueprint $table) => $table->dropUnique('tb_diklat_id_peg_diklat_id_unique'));
        }

        if ($this->indexExists('tb_diklat', 'tb_diklat_diklat_id_index')) {
            Schema::table('tb_diklat', fn (Blueprint $table) => $table->dropIndex('tb_diklat_diklat_id_index'));
        }

        Schema::table('tb_diklat', function (Blueprint $table): void {
            if (Schema::hasColumn('tb_diklat', 'biaya_per_peserta')) {
                $table->dropColumn('biaya_per_peserta');
            }

            if (Schema::hasColumn('tb_diklat', 'diklat_id')) {
                $table->dropColumn('diklat_id');
            }
        });

        if (Schema::hasTable('tb_biaya_pendidikan')) {
            Schema::table('tb_biaya_pendidikan', function (Blueprint $table): void {
                if (Schema::hasColumn('tb_biaya_pendidikan', 'durasi_hari')) {
                    $table->dropColumn('durasi_hari');
                }

                if (Schema::hasColumn('tb_biaya_pendidikan', 'tipe_pelaksana')) {
                    $table->dropColumn('tipe_pelaksana');
                }
            });
        }
    }

    private function indexExists(string $table, string $index): bool
    {
        return DB::table('information_schema.statistics')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', $table)
            ->where('index_name', $index)
            ->exists();
    }

    private function foreignKeyExists(string $table, string $constraint): bool
    {
        return DB::table('information_schema.table_constraints')
            ->where('constraint_schema', DB::getDatabaseName())
            ->where('table_name', $table)
            ->where('constraint_name', $constraint)
            ->where('constraint_type', 'FOREIGN KEY')
            ->exists();
    }
};

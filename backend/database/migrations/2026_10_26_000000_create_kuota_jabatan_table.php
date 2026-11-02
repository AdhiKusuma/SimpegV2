<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('kuota_jabatan')) {
            return;
        }

        Schema::create('kuota_jabatan', function (Blueprint $table): void {
            $table->id();
            $table->string('kode_cabang', 20);
            $table->unsignedInteger('jabatan_id');
            $table->unsignedInteger('kuota_pegawai')->default(0);
            $table->timestamps();

            $table->unique(['kode_cabang', 'jabatan_id'], 'kuota_jabatan_cabang_jabatan_unique');
            $table->index('jabatan_id', 'kuota_jabatan_jabatan_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kuota_jabatan');
    }
};

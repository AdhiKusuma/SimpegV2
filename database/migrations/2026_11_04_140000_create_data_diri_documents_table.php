<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_diri_documents', function (Blueprint $table): void {
            $table->id();
            $table->string('pegawai_id', 32);
            $table->string('jenis', 32);
            $table->string('nama_asli', 255);
            $table->string('path', 512);
            $table->unsignedBigInteger('ukuran');
            $table->char('hash', 64);
            $table->string('diunggah_oleh', 64)->nullable();
            $table->timestamps();

            $table->unique(['pegawai_id', 'jenis'], 'data_diri_documents_pegawai_jenis_unique');
            $table->index('hash', 'data_diri_documents_hash_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_diri_documents');
    }
};

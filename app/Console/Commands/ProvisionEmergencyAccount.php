<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class ProvisionEmergencyAccount extends Command
{
    protected $signature = 'simpeg:provision-emergency-account';

    protected $description = 'Membuat atau memperbarui akun lokal darurat dari konfigurasi environment.';

    public function handle(): int
    {
        if (! (bool) config('emergency.enabled')) {
            $this->error('Provisioning dinonaktifkan. Set EMERGENCY_ACCOUNT_ENABLED=true di .env.');

            return self::FAILURE;
        }

        if (! Schema::hasTable('tb_user') || ! Schema::hasColumn('tb_user', 'local_login_enabled')) {
            $this->error('Struktur login lokal belum tersedia. Jalankan migration terlebih dahulu.');

            return self::FAILURE;
        }

        $id = trim((string) config('emergency.id'));
        $password = (string) config('emergency.password');
        if ($id === '' || strlen($id) > 10 || strlen($password) < 10) {
            $this->error('ID maksimal 10 karakter dan password minimal 10 karakter wajib diisi di .env.');

            return self::FAILURE;
        }

        $now = now();
        DB::table('tb_user')->updateOrInsert(
            ['id_user' => $id],
            [
                'nama_user' => trim((string) config('emergency.name', 'IT Administrator')),
                'password' => Hash::make($password),
                'hak_akses' => 'it_admin',
                'jabatan' => 'IT Administrator',
                'status_aktif' => 'Y',
                'local_login_enabled' => true,
                'updated_at' => $now,
                'updated_by' => 'SYSTEM',
                'created_at' => $now,
                'created_by' => 'SYSTEM',
            ],
        );

        $this->info(sprintf('Akun lokal darurat %s siap digunakan dengan role IT Administrator.', $id));

        return self::SUCCESS;
    }
}

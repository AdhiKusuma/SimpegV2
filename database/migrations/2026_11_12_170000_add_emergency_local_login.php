<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tb_user') && ! Schema::hasColumn('tb_user', 'local_login_enabled')) {
            Schema::table('tb_user', function (Blueprint $table): void {
                $table->boolean('local_login_enabled')->default(false)->after('status_aktif');
            });
        }

        if (Schema::hasTable('rbac_roles')) {
            DB::table('rbac_roles')->updateOrInsert(
                ['code' => 'it_admin'],
                [
                    'name' => 'IT Administrator',
                    'description' => 'Akun darurat dengan akses administrasi inti tanpa SSO.',
                    'is_system' => true,
                    'active' => true,
                    'updated_at' => now(),
                    'created_at' => now(),
                ],
            );
        }

        if (Schema::hasTable('rbac_permissions') && Schema::hasTable('rbac_access_rules')) {
            $permissionIds = DB::table('rbac_permissions')
                ->whereIn('code', config('rbac.default_role_permissions.it_admin', []))
                ->where('active', true)
                ->pluck('id');
            $now = now();

            foreach ($permissionIds as $permissionId) {
                DB::table('rbac_access_rules')->updateOrInsert(
                    [
                        'permission_id' => $permissionId,
                        'subject_type' => 'role',
                        'subject_value' => 'it_admin',
                    ],
                    [
                        'allowed' => true,
                        'updated_at' => $now,
                        'created_at' => $now,
                    ],
                );
            }
        }

        $this->provisionConfiguredAccount();
    }

    private function provisionConfiguredAccount(): void
    {
        if (! Schema::hasTable('tb_user') || ! (bool) config('emergency.enabled')) {
            return;
        }

        $id = trim((string) config('emergency.id'));
        $password = (string) config('emergency.password');
        if ($id === '' || $password === '' || strlen($id) > 10 || strlen($password) < 10) {
            return;
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
    }

    public function down(): void
    {
        if (Schema::hasTable('rbac_access_rules')) {
            DB::table('rbac_access_rules')
                ->where('subject_type', 'role')
                ->where('subject_value', 'it_admin')
                ->delete();
        }

        if (Schema::hasTable('rbac_roles')) {
            DB::table('rbac_roles')->where('code', 'it_admin')->delete();
        }

        // Account data is intentionally retained on rollback; deleting users
        // from a migration would be unsafe. Disable it explicitly instead.
        if (Schema::hasTable('tb_user') && Schema::hasColumn('tb_user', 'local_login_enabled')) {
            DB::table('tb_user')->where('hak_akses', 'it_admin')->update(['local_login_enabled' => false]);
            Schema::table('tb_user', function (Blueprint $table): void {
                $table->dropColumn('local_login_enabled');
            });
        }
    }
};

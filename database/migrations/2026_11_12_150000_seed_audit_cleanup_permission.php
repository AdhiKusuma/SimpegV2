<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('rbac_permissions') || ! Schema::hasTable('rbac_access_rules')) {
            return;
        }

        $now = now();
        DB::table('rbac_permissions')->updateOrInsert(
            ['code' => 'audit.cleanup'],
            [
                'label' => 'Bersihkan audit log',
                'group_name' => 'Administrasi',
                'sort_order' => 455,
                'active' => true,
                'updated_at' => $now,
                'created_at' => $now,
            ],
        );

        $permissionId = DB::table('rbac_permissions')
            ->where('code', 'audit.cleanup')
            ->value('id');

        if ($permissionId === null) {
            return;
        }

        foreach (['admin', 'superadmin'] as $role) {
            DB::table('rbac_access_rules')->updateOrInsert(
                [
                    'permission_id' => $permissionId,
                    'subject_type' => 'role',
                    'subject_value' => $role,
                ],
                [
                    'allowed' => true,
                    'updated_at' => $now,
                    'created_at' => $now,
                ],
            );
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('rbac_permissions')) {
            return;
        }

        $permissionId = DB::table('rbac_permissions')
            ->where('code', 'audit.cleanup')
            ->value('id');

        if ($permissionId === null) {
            return;
        }

        DB::table('rbac_access_rules')->where('permission_id', $permissionId)->delete();
        DB::table('rbac_permissions')->where('id', $permissionId)->delete();
    }
};

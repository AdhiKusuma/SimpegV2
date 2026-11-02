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

        $permissionId = DB::table('rbac_permissions')
            ->where('code', 'dashboard.branch')
            ->value('id');

        if ($permissionId === null) {
            return;
        }

        DB::table('rbac_access_rules')
            ->where('permission_id', $permissionId)
            ->where('subject_type', 'role')
            ->where('subject_value', 'admin')
            ->update([
                'allowed' => false,
                'updated_at' => now(),
            ]);
    }

    public function down(): void {}
};

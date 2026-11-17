<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('rbac_roles')) {
            Schema::create('rbac_roles', function (Blueprint $table): void {
                $table->id();
                $table->string('code', 80)->unique();
                $table->string('name', 120);
                $table->text('description')->nullable();
                $table->boolean('is_system')->default(false);
                $table->boolean('active')->default(true);
                $table->timestamps();
            });
        }

        $now = now();
        $descriptions = [
            'admin' => 'Administrator dengan akses pengelolaan sistem.',
            'superadmin' => 'Pengelola akses dan pemantauan sistem.',
            'kepala' => 'Supervisor dengan akses operasional sesuai kewenangan.',
            'user' => 'Pengguna dengan akses layanan pribadi.',
        ];

        foreach (config('rbac.roles', []) as $code => $name) {
            DB::table('rbac_roles')->updateOrInsert(
                ['code' => $code],
                [
                    'name' => $name,
                    'description' => $descriptions[$code] ?? null,
                    'is_system' => true,
                    'active' => true,
                    'updated_at' => $now,
                    'created_at' => $now,
                ],
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('rbac_roles');
    }
};

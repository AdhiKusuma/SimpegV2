<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tb_config')) {
            return;
        }

        $columns = [];

        if (! Schema::hasColumn('tb_config', 'default_theme')) {
            $columns['default_theme'] = ['type' => 'string', 'default' => 'light'];
        }

        if (! Schema::hasColumn('tb_config', 'allow_user_theme')) {
            $columns['allow_user_theme'] = ['type' => 'boolean', 'default' => true];
        }

        if (! Schema::hasColumn('tb_config', 'reduce_motion')) {
            $columns['reduce_motion'] = ['type' => 'boolean', 'default' => false];
        }

        if (! Schema::hasColumn('tb_config', 'apply_login_theme')) {
            $columns['apply_login_theme'] = ['type' => 'boolean', 'default' => true];
        }

        if ($columns === []) {
            return;
        }

        Schema::table('tb_config', function (Blueprint $table) use ($columns): void {
            foreach ($columns as $name => $definition) {
                $column = $table->{$definition['type']}($name)->default($definition['default']);
                if ($name !== 'default_theme') {
                    $column->after('font_size');
                }
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('tb_config')) {
            return;
        }

        $columns = array_filter([
            Schema::hasColumn('tb_config', 'apply_login_theme') ? 'apply_login_theme' : null,
            Schema::hasColumn('tb_config', 'reduce_motion') ? 'reduce_motion' : null,
            Schema::hasColumn('tb_config', 'allow_user_theme') ? 'allow_user_theme' : null,
            Schema::hasColumn('tb_config', 'default_theme') ? 'default_theme' : null,
        ]);

        if ($columns !== []) {
            Schema::table('tb_config', function (Blueprint $table) use ($columns): void {
                $table->dropColumn($columns);
            });
        }
    }
};

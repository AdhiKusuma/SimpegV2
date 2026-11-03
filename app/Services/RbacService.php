<?php

namespace App\Services;

use App\Models\Legacy\TbJabatan;
use App\Models\Legacy\TbUser;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RbacService
{
    private const PERMISSIONS_TABLE = 'rbac_permissions';

    private const RULES_TABLE = 'rbac_access_rules';

    private static bool $defaultsChecked = false;

    public function available(): bool
    {
        return Schema::hasTable(self::PERMISSIONS_TABLE) && Schema::hasTable(self::RULES_TABLE);
    }

    public function ensureDefaults(): void
    {
        if (! $this->available() || self::$defaultsChecked) {
            return;
        }

        $now = now();
        foreach (config('rbac.permissions', []) as $code => $definition) {
            DB::table(self::PERMISSIONS_TABLE)->insertOrIgnore([
                'code' => $code,
                'label' => $definition['label'],
                'group_name' => $definition['group'],
                'sort_order' => $definition['sort'],
                'active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        DB::table(self::PERMISSIONS_TABLE)
            ->where('code', 'payroll.approval.manage')
            ->where('active', false)
            ->update(['active' => true, 'updated_at' => $now]);

        $permissionIds = DB::table(self::PERMISSIONS_TABLE)
            ->whereIn('code', array_keys(config('rbac.permissions', [])))
            ->pluck('id', 'code');

        foreach (config('rbac.default_role_permissions', []) as $role => $permissions) {
            $permissions = $permissions === '*' ? array_keys(config('rbac.permissions', [])) : $permissions;
            foreach ($permissions as $code) {
                $permissionId = $permissionIds[$code] ?? null;
                if ($permissionId === null) {
                    continue;
                }

                DB::table(self::RULES_TABLE)->insertOrIgnore([
                    'permission_id' => $permissionId,
                    'subject_type' => 'role',
                    'subject_value' => strtolower((string) $role),
                    'allowed' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        self::$defaultsChecked = true;
    }

    public function permissionsFor(Request $request): array
    {
        $cached = $request->attributes->get('rbac.permissions');
        if (is_array($cached)) {
            return $cached;
        }

        if (! $request->session()->has('id_user')) {
            $request->attributes->set('rbac.permissions', []);

            return [];
        }

        $this->ensureDefaults();
        $catalog = config('rbac.permissions', []);
        $fallback = $this->fallbackPermissions($request);

        if (! $this->available()) {
            $request->attributes->set('rbac.permissions', $fallback);

            return $fallback;
        }

        $subjects = $this->subjectsFor($request);
        $rules = $this->rulesFor($subjects);
        $permissions = [];

        foreach (array_keys($catalog) as $code) {
            $matching = $rules[$code] ?? [];
            if ($matching === []) {
                if (in_array($code, $fallback, true)) {
                    $permissions[] = $code;
                }

                continue;
            }

            $highestPriority = max(array_keys($matching));
            $permissionsAtPriority = $matching[$highestPriority];
            $allowed = ! in_array(false, $permissionsAtPriority, true);
            if ($allowed) {
                $permissions[] = $code;
            }
        }

        $request->attributes->set('rbac.permissions', $permissions);

        return $permissions;
    }

    public function can(Request $request, string $permission): bool
    {
        return in_array($permission, $this->permissionsFor($request), true);
    }

    public function permissionForRoute(?string $routeName): string|array|null
    {
        if ($routeName === null || $routeName === '') {
            return null;
        }

        return config('rbac.route_permissions')[$routeName] ?? null;
    }

    public function metrics(): array
    {
        $users = TbUser::query();

        $metrics = [
            'users' => (clone $users)->count(),
            'roles' => (clone $users)->select('hak_akses')->selectRaw('COUNT(*) as total')->groupBy('hak_akses')->orderBy('hak_akses')->get()->map(fn ($row): array => [
                'key' => strtolower((string) $row->hak_akses),
                'label' => config('rbac.roles.'.strtolower((string) $row->hak_akses), $row->hak_akses),
                'total' => (int) $row->total,
            ])->values()->all(),
            'positions' => (clone $users)->whereNotNull('jabatan')->where('jabatan', '<>', '')->distinct('jabatan')->count('jabatan'),
            'employees' => (clone $users)->whereNotNull('id_pegawai')->where('id_pegawai', '<>', '')->distinct('id_pegawai')->count('id_pegawai'),
        ];

        if ($this->available()) {
            $metrics['directRules'] = DB::table(self::RULES_TABLE)->whereIn('subject_type', ['employee', 'position'])->count();
        } else {
            $metrics['directRules'] = 0;
        }

        return $metrics;
    }

    public function subjects(): array
    {
        $roles = TbUser::query()
            ->whereNotNull('hak_akses')
            ->select('hak_akses')
            ->distinct()
            ->orderBy('hak_akses')
            ->pluck('hak_akses')
            ->map(fn ($role): array => ['value' => strtolower((string) $role), 'label' => config('rbac.roles.'.strtolower((string) $role), $role)])
            ->values()
            ->all();

        $roles = collect($roles)
            ->concat(collect(config('rbac.roles', []))->map(fn (string $label, string $value): array => ['value' => $value, 'label' => $label]))
            ->unique('value')
            ->sortBy('label')
            ->values()
            ->all();

        $positions = TbUser::query()
            ->whereNotNull('jabatan')
            ->where('jabatan', '<>', '')
            ->select('jabatan')
            ->distinct()
            ->orderBy('jabatan')
            ->pluck('jabatan')
            ->map(fn ($position): array => ['value' => strtolower(trim((string) $position)), 'label' => $position])
            ->values()
            ->all();

        if (Schema::hasTable('tb_jabatan')) {
            $positions = collect($positions)
                ->concat(TbJabatan::query()->where('status_jab', 'Aktif')->whereNotNull('jabatan')->where('jabatan', '<>', '')->select('jabatan')->distinct()->orderBy('jabatan')->pluck('jabatan')->map(fn ($position): array => ['value' => strtolower(trim((string) $position)), 'label' => $position]))
                ->unique('value')
                ->sortBy('label')
                ->values()
                ->all();
        }

        $employees = TbUser::query()
            ->whereNotNull('id_pegawai')
            ->where('id_pegawai', '<>', '')
            ->select(['id_pegawai', 'nama_user'])
            ->orderBy('nama_user')
            ->get()
            ->map(fn (TbUser $user): array => ['value' => (string) $user->id_pegawai, 'label' => trim($user->nama_user.' · '.$user->id_pegawai)])
            ->unique('value')
            ->values()
            ->all();

        return ['role' => $roles, 'position' => $positions, 'employee' => $employees];
    }

    public function matrix(Request $request, string $subjectType, string $subjectValue): array
    {
        $this->ensureDefaults();
        $catalog = config('rbac.permissions', []);
        $permissions = DB::table(self::PERMISSIONS_TABLE)
            ->where('active', true)
            ->whereIn('code', array_keys($catalog))
            ->get()
            ->sortBy(fn ($permission) => $catalog[$permission->code]['sort'] ?? $permission->sort_order)
            ->values();
        $rules = $this->available()
            ? DB::table(self::RULES_TABLE)->where('subject_type', $subjectType)->where('subject_value', $this->normaliseSubjectValue($subjectType, $subjectValue))->pluck('allowed', 'permission_id')
            : collect();

        $effective = $this->permissionsForSubject($request, $subjectType, $subjectValue);

        return [
            'permissions' => $permissions->map(fn ($permission): array => [
                'code' => $permission->code,
                'label' => $catalog[$permission->code]['label'] ?? $permission->label,
                'group' => $catalog[$permission->code]['group'] ?? $permission->group_name,
                'enabled' => (bool) ($rules[$permission->id] ?? in_array($permission->code, $effective, true)),
                'explicit' => $rules->has($permission->id),
            ])->values()->all(),
            'effective' => $effective,
        ];
    }

    public function saveMatrix(string $subjectType, string $subjectValue, array $permissionCodes): void
    {
        $this->ensureDefaults();
        abort_unless($this->available(), 503, 'Tabel RBAC belum tersedia. Jalankan migrasi database terlebih dahulu.');

        $subjectValue = $this->normaliseSubjectValue($subjectType, $subjectValue);
        $permissionIds = DB::table(self::PERMISSIONS_TABLE)
            ->where('active', true)
            ->whereIn('code', array_keys(config('rbac.permissions', [])))
            ->pluck('id', 'code');
        $selected = array_fill_keys($permissionCodes, true);
        $now = now();

        DB::transaction(function () use ($now, $permissionIds, $selected, $subjectType, $subjectValue): void {
            foreach ($permissionIds as $code => $permissionId) {
                DB::table(self::RULES_TABLE)->updateOrInsert(
                    ['permission_id' => $permissionId, 'subject_type' => $subjectType, 'subject_value' => $subjectValue],
                    ['allowed' => isset($selected[$code]), 'updated_at' => $now, 'created_at' => $now],
                );
            }
        });
    }

    private function permissionsForSubject(Request $request, string $subjectType, string $subjectValue): array
    {
        $original = $request->attributes->get('rbac.permissions');
        $request->attributes->set('rbac.subject_override', [$subjectType, $subjectValue]);
        $subjects = $this->subjectsFor($request);
        $rules = $this->rulesFor($subjects);
        $request->attributes->remove('rbac.subject_override');
        if ($original !== null) {
            $request->attributes->set('rbac.permissions', $original);
        }

        return collect(config('rbac.permissions', []))->keys()->filter(function (string $code) use ($rules): bool {
            $matching = $rules[$code] ?? [];
            if ($matching === []) {
                return false;
            }
            $priority = max(array_keys($matching));

            return ! in_array(false, $matching[$priority], true);
        })->values()->all();
    }

    private function subjectsFor(Request $request): array
    {
        $override = $request->attributes->get('rbac.subject_override');
        if (is_array($override) && count($override) === 2) {
            $subjectType = (string) $override[0];
            $subjectValue = $this->normaliseSubjectValue($subjectType, (string) $override[1]);

            if ($subjectType !== 'employee') {
                return [['type' => $subjectType, 'value' => $subjectValue, 'priority' => 3]];
            }

            $subjects = [['type' => 'employee', 'value' => $subjectValue, 'priority' => 3]];
            $user = Schema::hasTable('tb_user')
                ? TbUser::query()->where('id_pegawai', $subjectValue)->first(['hak_akses', 'jabatan'])
                : null;

            $role = strtolower(trim((string) ($user?->hak_akses ?? '')));
            if ($role !== '') {
                $subjects[] = ['type' => 'role', 'value' => $role, 'priority' => 1];
            }

            $positions = [];
            if (Schema::hasTable('tb_jabatan')) {
                $positions = TbJabatan::query()
                    ->where('id_peg', $subjectValue)
                    ->where('status_jab', 'Aktif')
                    ->pluck('jabatan')
                    ->all();
            }
            if ($user?->jabatan) {
                $positions[] = $user->jabatan;
            }

            foreach (array_unique(array_filter(array_map(fn ($position) => strtolower(trim((string) $position)), $positions))) as $position) {
                $subjects[] = ['type' => 'position', 'value' => $position, 'priority' => 2];
            }

            return $subjects;
        }

        $role = strtolower((string) $request->session()->get('hak_akses'));
        $employee = trim((string) $request->session()->get('id_pegawai', ''));
        $subjects = [['type' => 'role', 'value' => $role, 'priority' => 1]];
        if ($employee !== '') {
            $subjects[] = ['type' => 'employee', 'value' => $employee, 'priority' => 3];
        }

        $positions = [];
        if ($employee !== '' && Schema::hasTable('tb_jabatan')) {
            $positions = TbJabatan::query()->where('id_peg', $employee)->where('status_jab', 'Aktif')->pluck('jabatan')->all();
        }
        if ($employee !== '' && Schema::hasTable('tb_user')) {
            $positions[] = TbUser::query()->where('id_pegawai', $employee)->value('jabatan');
        }
        foreach (array_unique(array_filter(array_map(fn ($position) => strtolower(trim((string) $position)), $positions))) as $position) {
            $subjects[] = ['type' => 'position', 'value' => $position, 'priority' => 2];
        }

        return $subjects;
    }

    private function rulesFor(array $subjects): array
    {
        if (! $this->available() || $subjects === []) {
            return [];
        }

        $query = DB::table(self::RULES_TABLE.' as rules')
            ->join(self::PERMISSIONS_TABLE.' as permissions', 'permissions.id', '=', 'rules.permission_id')
            ->where('permissions.active', true)
            ->where(function (Builder $builder) use ($subjects): void {
                foreach ($subjects as $subject) {
                    $builder->orWhere(function (Builder $scope) use ($subject): void {
                        $scope->where('rules.subject_type', $subject['type'])->where('rules.subject_value', $subject['value']);
                    });
                }
            })
            ->get(['permissions.code', 'rules.subject_type', 'rules.subject_value', 'rules.allowed']);

        $priorities = collect($subjects)->mapWithKeys(fn (array $subject): array => [$subject['type'].'|'.$subject['value'] => $subject['priority']]);
        $rules = [];
        foreach ($query as $rule) {
            $priority = $priorities[$rule->subject_type.'|'.$this->normaliseSubjectValue($rule->subject_type, (string) ($rule->subject_value ?? ''))] ?? 0;
            if ($priority > 0) {
                $rules[$rule->code][$priority][] = (bool) $rule->allowed;
            }
        }

        return $rules;
    }

    private function fallbackPermissions(Request $request): array
    {
        $role = strtolower((string) $request->session()->get('hak_akses'));
        $permissions = config('rbac.default_role_permissions.'.$role, []);

        return $permissions === '*' ? array_keys(config('rbac.permissions', [])) : $permissions;
    }

    private function normaliseSubjectValue(string $type, string $value): string
    {
        return $type === 'position' || $type === 'role' ? strtolower(trim($value)) : trim($value);
    }
}

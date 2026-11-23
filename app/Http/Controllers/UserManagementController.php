<?php

namespace App\Http\Controllers;

use App\Models\Legacy\TbPegawai;
use App\Models\Legacy\TbRecycleBin;
use App\Models\Legacy\TbUser;
use App\Services\RbacService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class UserManagementController extends Controller
{
    public function index(Request $request): Response
    {
        $this->ensureViewAccess($request);
        $role = $request->string('role')->trim()->lower()->value();
        $search = $request->string('search')->trim()->value();
        $roleDefinitions = collect(app(RbacService::class)->roleDefinitions())
            ->mapWithKeys(fn (array $definition): array => [strtolower((string) $definition['code']) => $definition]);
        $roleCounts = TbUser::query()
            ->selectRaw('LOWER(hak_akses) as role_code, COUNT(*) as total')
            ->groupBy('role_code')
            ->pluck('total', 'role_code')
            ->map(fn ($total): int => (int) $total);
        $roleCodes = $roleDefinitions->keys()
            ->merge($roleCounts->keys())
            ->unique()
            ->values();
        $roleSummaries = $roleCodes->map(function (string $roleCode) use ($roleDefinitions, $roleCounts): array {
            $definition = $roleDefinitions->get($roleCode, []);

            return [
                'code' => $roleCode,
                'name' => $definition['name'] ?? $roleCode,
                'description' => $definition['description'] ?? null,
                'total' => (int) ($roleCounts->get($roleCode, 0)),
            ];
        })->sortBy('name')->values()->all();

        $users = TbUser::query()
            ->when($role !== '', fn (Builder $builder) => $builder->whereRaw('LOWER(hak_akses) = ?', [$role]))
            ->when($search !== '', function (Builder $builder) use ($search): void {
                $like = "%{$search}%";
                $builder->where(function (Builder $scope) use ($like): void {
                    $scope->where('id_user', 'like', $like)->orWhere('nama_user', 'like', $like)->orWhere('id_pegawai', 'like', $like)->orWhere('jabatan', 'like', $like);
                });
            })
            ->orderBy('nama_user')
            ->paginate(min(max($request->integer('per_page', 10), 1), 50))
            ->withQueryString()
            ->through(fn (TbUser $user): array => [
                'id_user' => $user->id_user,
                'nama_user' => $user->nama_user,
                'jabatan' => $user->jabatan,
                'hak_akses' => strtolower((string) $user->hak_akses),
                'status_aktif' => $user->status_aktif,
                'id_pegawai' => $user->id_pegawai,
            ]);

        return Inertia::render('Users/Index', [
            'users' => $users,
            'filters' => ['role' => $role, 'search' => $search],
            'roleSummaries' => $roleSummaries,
            'canManage' => app(RbacService::class)->can($request, 'users.manage'),
        ]);
    }

    public function roleMembers(Request $request, string $role): JsonResponse
    {
        $this->ensureViewAccess($request);
        $roleCode = strtolower(trim($role));
        $definition = collect(app(RbacService::class)->roleDefinitions())
            ->first(fn (array $item): bool => strtolower((string) $item['code']) === $roleCode);

        $definition ??= ['code' => $roleCode, 'name' => $roleCode];

        $members = TbUser::query()
            ->whereRaw('LOWER(hak_akses) = ?', [$roleCode])
            ->orderBy('nama_user')
            ->paginate(min(max($request->integer('per_page', 25), 10), 100))
            ->through(fn (TbUser $user): array => [
                'id_user' => $user->id_user,
                'nama_user' => $user->nama_user,
                'id_pegawai' => $user->id_pegawai,
                'jabatan' => $user->jabatan,
                'status_aktif' => $user->status_aktif,
            ]);

        return response()->json([
            'role' => [
                'code' => $roleCode,
                'name' => $definition['name'] ?? $roleCode,
            ],
            'members' => $members,
        ]);
    }

    public function create(Request $request): Response
    {
        $this->ensureAdmin($request);

        return Inertia::render('Users/Form', ['mode' => 'create', 'record' => null, 'employees' => $this->employees(), 'roles' => $this->availableRoles()]);
    }

    public function edit(Request $request, string $id): Response
    {
        $this->ensureAdmin($request);
        $record = TbUser::query()->findOrFail($id);
        $employees = $this->employees();
        if ($record->id_pegawai && ! collect($employees)->contains('id_peg', $record->id_pegawai)) {
            $employee = TbPegawai::query()->where('id_peg', $record->id_pegawai)->first(['id_peg', 'nama']);
            if ($employee) {
                $employees[] = ['id_peg' => $employee->id_peg, 'nama' => $employee->nama];
            }
        }

        return Inertia::render('Users/Form', [
            'mode' => 'edit',
            'record' => ['id_user' => $record->id_user, 'nama_user' => $record->nama_user, 'hak_akses' => strtolower((string) $record->hak_akses), 'status_aktif' => $record->status_aktif, 'id_pegawai' => $record->id_pegawai],
            'employees' => $employees,
            'roles' => $this->availableRoles(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ensureAdmin($request);
        $data = $this->validatedData($request, false);
        $data['password'] = Hash::make($data['password']);
        $data['created_by'] = $request->session()->get('id_user', 'System');
        TbUser::query()->create($data);

        return to_route('users.index')->with('success', 'User berhasil dibuat.');
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $this->ensureAdmin($request);
        $record = TbUser::query()->findOrFail($id);
        $data = $this->validatedData($request, true, $id);
        $password = $data['password'] ?? null;
        unset($data['password']);
        if ($password !== null && $password !== '') {
            $data['password'] = Hash::make($password);
        }
        $data['updated_by'] = $request->session()->get('id_user', 'System');
        $record->update($data);

        return to_route('users.index')->with('success', 'Data user berhasil diperbarui.');
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $this->ensureAdmin($request);
        $validated = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        DB::transaction(function () use ($request, $id, $validated): void {
            $record = TbUser::query()->lockForUpdate()->findOrFail($id);
            TbRecycleBin::query()->create(['tabel_by' => 'tb_user', 'data_json' => json_encode($record->getAttributes(), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), 'alasan' => $validated['reason'], 'created_by' => $request->session()->get('nama_user', 'Admin'), 'created' => now()]);
            $record->delete();
        });

        return to_route('users.index')->with('success', 'User dipindahkan ke Recycle Bin.');
    }

    private function validatedData(Request $request, bool $isEdit, ?string $id = null): array
    {
        $idRule = ['required', 'string', 'max:10'];
        if (! $isEdit) {
            $idRule[] = Rule::unique('tb_user', 'id_user');
        }
        $employeeRule = ['nullable', 'string', 'max:20', Rule::exists('tb_pegawai', 'id_peg')];
        if ($isEdit) {
            $employeeRule[] = Rule::unique('tb_user', 'id_pegawai')->ignore($id, 'id_user');
        } else {
            $employeeRule[] = Rule::unique('tb_user', 'id_pegawai');
        }

        $rules = [
            'id_user' => $idRule,
            'nama_user' => ['required', 'string', 'max:64'],
            'hak_akses' => ['required', Rule::in($this->availableRoles())],
            'password' => [$isEdit ? 'nullable' : 'required', 'string', 'max:255'],
            'status_aktif' => ['required', Rule::in(['Y', 'N'])],
            'id_pegawai' => $employeeRule,
        ];

        $data = $request->validate($rules);
        if ($isEdit) {
            unset($data['id_user']);
        }

        return $data;
    }

    private function employees(): array
    {
        return TbPegawai::query()->where('status_aktif', 1)->orderBy('nama')->get(['id_peg', 'nama'])->map(fn (TbPegawai $employee): array => ['id_peg' => $employee->id_peg, 'nama' => $employee->nama])->all();
    }

    private function availableRoles(): array
    {
        return app(RbacService::class)->roleCodes();
    }

    private function ensureAdmin(Request $request): void
    {
        abort_unless(app(RbacService::class)->can($request, 'users.manage'), 403);
    }

    private function ensureViewAccess(Request $request): void
    {
        abort_unless(app(RbacService::class)->can($request, 'users.view'), 403);
    }
}

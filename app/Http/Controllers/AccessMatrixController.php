<?php

namespace App\Http\Controllers;

use App\Services\RbacService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AccessMatrixController extends Controller
{
    public function __construct(private readonly RbacService $rbac) {}

    public function index(Request $request): Response
    {
        $this->ensurePermission($request, 'users.manage');
        $mode = $request->string('mode')->trim()->lower()->value() ?: 'roles';

        $subjectType = $request->string('subject_type')->trim()->lower()->value() ?: 'role';
        if (! in_array($subjectType, ['role', 'position', 'employee'], true)) {
            $subjectType = 'role';
        }

        $subjects = $this->rbac->subjects();
        $subjectValue = trim($request->string('subject_value')->value());
        $options = $subjects[$subjectType] ?? [];
        if ($subjectValue === '' || ! collect($options)->contains('value', $subjectValue)) {
            $subjectValue = (string) ($options[0]['value'] ?? '');
        }

        return Inertia::render('Users/AccessMatrix', [
            'mode' => $mode === 'special' ? 'special' : 'roles',
            'roles' => $this->rbac->managedRoles(),
            'permissionCatalog' => $this->rbac->permissionCatalog(),
            'available' => $this->rbac->available(),
            'metrics' => $this->rbac->metrics(),
            'subjects' => $subjects,
            'selected' => ['type' => $subjectType, 'value' => $subjectValue],
            'matrix' => $subjectValue !== '' && $this->rbac->available()
                ? $this->rbac->matrix($request, $subjectType, $subjectValue)
                : ['permissions' => [], 'effective' => []],
        ]);
    }

    public function storeRole(Request $request): RedirectResponse
    {
        $this->ensurePermission($request, 'users.roles.manage');
        abort_unless($this->rbac->available(), 503, 'Tabel RBAC belum tersedia. Jalankan migrasi database terlebih dahulu.');

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:80', 'regex:/^[a-z][a-z0-9._-]*$/', Rule::unique('rbac_roles', 'code')],
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::in(array_keys(config('rbac.permissions', [])))],
        ]);

        $this->rbac->createRole(
            strtolower($validated['code']),
            $validated['name'],
            $validated['description'] ?? null,
            $validated['permissions'] ?? [],
        );

        return to_route('users.access')->with('success', 'Role berhasil ditambahkan.');
    }

    public function updateRole(Request $request, string $role): RedirectResponse
    {
        $this->ensurePermission($request, 'users.roles.manage');
        abort_unless($this->rbac->available(), 503, 'Tabel RBAC belum tersedia. Jalankan migrasi database terlebih dahulu.');
        abort_unless(collect($this->rbac->roleDefinitions())->contains('code', strtolower($role)), 404);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::in(array_keys(config('rbac.permissions', [])))],
        ]);

        $this->rbac->updateRole(
            strtolower($role),
            $validated['name'],
            $validated['description'] ?? null,
            $validated['permissions'] ?? [],
        );

        return to_route('users.access')->with('success', 'Role dan permission berhasil diperbarui.');
    }

    public function update(Request $request): RedirectResponse
    {
        $this->ensurePermission($request, 'users.manage');
        abort_unless($this->rbac->available(), 503, 'Tabel RBAC belum tersedia. Jalankan migrasi database terlebih dahulu.');

        $validated = $request->validate([
            'subject_type' => ['required', Rule::in(['role', 'position', 'employee'])],
            'subject_value' => ['required', 'string', 'max:160'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::in(array_keys(config('rbac.permissions', [])))],
        ]);

        $this->rbac->saveMatrix(
            $validated['subject_type'],
            $validated['subject_value'],
            $validated['permissions'] ?? [],
        );

        return to_route('users.access', [
            'subject_type' => $validated['subject_type'],
            'subject_value' => $validated['subject_value'],
        ])->with('success', 'Matriks akses berhasil diperbarui.');
    }

    private function ensurePermission(Request $request, string $permission): void
    {
        abort_unless($this->rbac->can($request, $permission), 403);
    }
}

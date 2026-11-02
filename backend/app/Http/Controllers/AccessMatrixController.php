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
        $this->ensureAdmin($request);

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
            'available' => $this->rbac->available(),
            'metrics' => $this->rbac->metrics(),
            'subjects' => $subjects,
            'selected' => ['type' => $subjectType, 'value' => $subjectValue],
            'matrix' => $subjectValue !== '' && $this->rbac->available()
                ? $this->rbac->matrix($request, $subjectType, $subjectValue)
                : ['permissions' => [], 'effective' => []],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->ensureAdmin($request);
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

    private function ensureAdmin(Request $request): void
    {
        abort_unless(strtolower((string) $request->session()->get('hak_akses')) === 'admin', 403);
    }
}

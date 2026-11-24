<?php

namespace App\Services;

use App\Models\Legacy\TbPegawai;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

final class EmployeeAccessService
{
    private const ADMINISTRATOR_ROLES = ['admin', 'superadmin', 'it_admin'];

    public function role(Request $request): string
    {
        return strtolower(trim((string) $request->session()->get('hak_akses')));
    }

    public function isAdministrator(Request $request): bool
    {
        return in_array($this->role($request), self::ADMINISTRATOR_ROLES, true);
    }

    public function employeeId(Request $request): string
    {
        return trim((string) $request->session()->get('id_pegawai', ''));
    }

    /**
     * Apply the employee row scope for the current session to any query that
     * contains an employee ID column. Administrators retain the existing
     * all-employee access; heads are limited to active employees in their
     * office; users are limited to their own employee ID.
     */
    public function applyToEmployeeQuery(Builder $query, Request $request, string $employeeColumn = 'id_peg'): Builder
    {
        $role = $this->role($request);

        if (in_array($role, self::ADMINISTRATOR_ROLES, true)) {
            return $query;
        }

        if ($role === 'user') {
            $employeeId = $this->employeeId($request);

            return $employeeId === ''
                ? $query->whereRaw('1 = 0')
                : $query->where($employeeColumn, $employeeId);
        }

        if ($role === 'kepala') {
            $office = trim((string) $request->session()->get('kode_kantor', ''));

            if ($office === '') {
                return $query->whereRaw('1 = 0');
            }

            return $query->whereExists(function ($scope) use ($employeeColumn, $office): void {
                $scope->selectRaw('1')
                    ->from('tb_jabatan as access_job')
                    ->whereColumn('access_job.id_peg', $employeeColumn)
                    ->where('access_job.status_jab', 'Aktif')
                    ->where('access_job.unit_kerja', $office);
            });
        }

        return $query->whereRaw('1 = 0');
    }

    public function canAccessEmployee(Request $request, string $employeeId): bool
    {
        $employeeId = trim($employeeId);
        if ($employeeId === '') {
            return false;
        }

        if ($this->isAdministrator($request)) {
            return true;
        }

        if ($this->role($request) === 'user') {
            return hash_equals($this->employeeId($request), $employeeId);
        }

        if ($this->role($request) !== 'kepala') {
            return false;
        }

        $query = TbPegawai::query()->where('id_peg', $employeeId);

        return $this->applyToEmployeeQuery($query, $request)->exists();
    }
}

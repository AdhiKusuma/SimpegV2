<?php

namespace App\Policies;

use App\Models\DataDiriDocument;
use Illuminate\Http\Request;

class DataDiriDocumentPolicy
{
    public function view(Request $request, DataDiriDocument $document): bool
    {
        return $this->canViewEmployee($request, (string) $document->pegawai_id);
    }

    public function manage(Request $request, DataDiriDocument $document): bool
    {
        return $this->canManageEmployee($request, (string) $document->pegawai_id);
    }

    public function delete(Request $request, DataDiriDocument $document): bool
    {
        return $this->manage($request, $document);
    }

    public function canViewEmployee(Request $request, string $employeeId): bool
    {
        return $this->isAdministrator($request)
            || $this->sessionEmployeeId($request) === trim($employeeId);
    }

    public function canManageEmployee(Request $request, string $employeeId): bool
    {
        return $this->canViewEmployee($request, $employeeId);
    }

    public function canManageAll(Request $request): bool
    {
        return $this->isAdministrator($request);
    }

    private function isAdministrator(Request $request): bool
    {
        return in_array(strtolower((string) $request->session()->get('hak_akses')), ['admin', 'superadmin'], true);
    }

    private function sessionEmployeeId(Request $request): string
    {
        return trim((string) ($request->session()->get('id_pegawai') ?? ''));
    }
}

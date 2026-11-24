<?php

namespace App\Policies;

use App\Models\DataDiriDocument;
use App\Services\EmployeeAccessService;
use Illuminate\Http\Request;

class DataDiriDocumentPolicy
{
    public function __construct(private readonly EmployeeAccessService $employeeAccess) {}

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
        return $this->employeeAccess->isAdministrator($request)
            || ($this->employeeAccess->role($request) === 'user'
                && $this->employeeAccess->employeeId($request) === trim($employeeId));
    }

    public function canManageEmployee(Request $request, string $employeeId): bool
    {
        return $this->canViewEmployee($request, $employeeId);
    }

    public function canManageAll(Request $request): bool
    {
        return $this->employeeAccess->isAdministrator($request);
    }
}

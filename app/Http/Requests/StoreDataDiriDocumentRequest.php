<?php

namespace App\Http\Requests;

use App\Models\DataDiriDocument;
use App\Policies\DataDiriDocumentPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDataDiriDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $employeeId = trim((string) $this->route('employeeId'));

        return $employeeId !== ''
            && app(DataDiriDocumentPolicy::class)->canManageEmployee($this, $employeeId);
    }

    public function rules(): array
    {
        return [
            'jenis' => ['required', 'string', Rule::in(array_keys(DataDiriDocument::TYPES))],
            'file_pdf' => ['required', 'file', 'mimes:pdf', 'mimetypes:application/pdf', 'max:2048'],
        ];
    }

    public function attributes(): array
    {
        return [
            'jenis' => 'jenis dokumen',
            'file_pdf' => 'dokumen PDF',
        ];
    }
}

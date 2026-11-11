<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDataDiriDocumentRequest;
use App\Models\DataDiriDocument;
use App\Models\Legacy\TbPegawai;
use App\Policies\DataDiriDocumentPolicy;
use App\Services\DataDiriDocumentService;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class DataDiriController extends Controller
{
    public function __construct(
        private readonly DataDiriDocumentService $service,
        private readonly DataDiriDocumentPolicy $policy,
        private readonly NotificationService $notifications,
    ) {}

    public function index(Request $request): Response
    {
        $isAdministrative = $this->policy->canManageAll($request);
        $employeeId = $isAdministrative
            ? $request->string('pegawai_id')->trim()->value()
            : trim((string) ($request->session()->get('id_pegawai') ?? ''));

        if (! $isAdministrative && $employeeId === '') {
            abort(403, 'Akun Anda belum terhubung ke data pegawai.');
        }

        return $this->renderIndex($employeeId, $isAdministrative);
    }

    public function mine(Request $request): Response
    {
        $employeeId = trim((string) ($request->session()->get('id_pegawai') ?? ''));
        if ($employeeId === '') {
            abort(403, 'Akun Anda belum terhubung ke data pegawai.');
        }

        abort_unless(TbPegawai::query()->where('id_peg', $employeeId)->exists(), 404);

        return $this->renderIndex($employeeId, false);
    }

    private function renderIndex(string $employeeId, bool $isAdministrative): Response
    {

        $employee = $employeeId !== '' ? TbPegawai::query()->where('id_peg', $employeeId)->first(['id_peg', 'nama', 'nik']) : null;
        if ($employeeId !== '' && ! $employee) {
            abort(404);
        }

        $documents = $employeeId !== ''
            ? DataDiriDocument::query()
                ->where('pegawai_id', $employeeId)
                ->orderBy('jenis')
                ->get()
                ->map(fn (DataDiriDocument $document): array => $this->presentDocument($document))
                ->values()
                ->all()
            : [];

        $employees = $isAdministrative
            ? TbPegawai::query()->orderBy('nama')->get(['id_peg', 'nama', 'nik'])->map(fn (TbPegawai $item): array => [
                'id_peg' => (string) $item->id_peg,
                'nama' => (string) $item->nama,
                'nik' => (string) ($item->nik ?? ''),
            ])->values()->all()
            : [];

        return Inertia::render('Files/DataDiri/Index', [
            'documents' => $documents,
            'employees' => $employees,
            'selectedEmployee' => $employee ? [
                'id_peg' => (string) $employee->id_peg,
                'nama' => (string) $employee->nama,
                'nik' => (string) ($employee->nik ?? ''),
            ] : null,
            'isAdministrative' => $isAdministrative,
            'types' => collect(DataDiriDocument::TYPES)->map(fn (string $label, string $value): array => ['value' => $value, 'label' => $label])->values()->all(),
        ]);
    }

    public function store(StoreDataDiriDocumentRequest $request, string $employeeId): RedirectResponse
    {
        $employeeId = trim($employeeId);
        abort_unless($this->policy->canManageEmployee($request, $employeeId), 403);
        abort_unless(TbPegawai::query()->where('id_peg', $employeeId)->exists(), 404);

        $data = $request->validated();
        $document = $this->service->store(
            $employeeId,
            (string) $data['jenis'],
            $request->file('file_pdf'),
            trim((string) $request->session()->get('id_user', '')),
        );

        $this->notifyAdminChange($request, $document, 'updated', $employeeId);

        return back()->with('success', DataDiriDocument::TYPES[$data['jenis']].' berhasil disimpan.');
    }

    public function preview(Request $request, string $employeeId, DataDiriDocument $document): mixed
    {
        $document = $this->resolveDocument($request, $employeeId, $document);

        return $this->stream($document, false);
    }

    public function download(Request $request, string $employeeId, DataDiriDocument $document): mixed
    {
        $document = $this->resolveDocument($request, $employeeId, $document);

        return $this->stream($document, true);
    }

    public function destroy(Request $request, string $employeeId, DataDiriDocument $document): RedirectResponse
    {
        $document = $this->resolveDocument($request, $employeeId, $document);
        abort_unless($this->policy->delete($request, $document), 403);
        $this->service->delete($document);

        $this->notifyAdminChange($request, $document, 'deleted', $employeeId);

        return back()->with('success', $document->typeLabel().' berhasil dihapus.');
    }

    private function resolveDocument(Request $request, string $employeeId, DataDiriDocument $document): DataDiriDocument
    {
        abort_unless((string) $document->pegawai_id === trim($employeeId), 404);
        abort_unless($this->policy->view($request, $document), 403);
        abort_unless(Storage::disk($this->service->diskName())->exists($document->path), 404);

        return $document;
    }

    private function notifyAdminChange(Request $request, DataDiriDocument $document, string $action, string $employeeId): void
    {
        if (
            ! $this->policy->canManageAll($request)
            || trim((string) $request->session()->get('id_pegawai', '')) === trim($employeeId)
        ) {
            return;
        }

        try {
            $this->notifications->notifyDataDiriChanged(
                $document,
                $action,
                trim((string) $request->session()->get('id_user', '')),
            );
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    private function stream(DataDiriDocument $document, bool $download): mixed
    {
        $stream = Storage::disk($this->service->diskName())->readStream($document->path);
        abort_unless(is_resource($stream), 404);
        $filename = rawurlencode($document->nama_asli);

        return response()->stream(function () use ($stream): void {
            fpassthru($stream);
            fclose($stream);
        }, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Length' => (string) $document->ukuran,
            'Content-Disposition' => ($download ? 'attachment' : 'inline')."; filename*=UTF-8''{$filename}",
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function presentDocument(DataDiriDocument $document): array
    {
        return [
            'id' => (int) $document->id,
            'jenis' => $document->jenis,
            'label' => $document->typeLabel(),
            'nama_asli' => $document->nama_asli,
            'ukuran' => (int) $document->ukuran,
            'hash' => $document->hash,
            'diunggah_pada' => $document->updated_at?->format('d-m-Y H:i'),
            'preview_url' => route('files.data-diri.preview', ['employeeId' => $document->pegawai_id, 'document' => $document->id]),
            'download_url' => route('files.data-diri.download', ['employeeId' => $document->pegawai_id, 'document' => $document->id]),
        ];
    }
}

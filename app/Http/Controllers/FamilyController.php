<?php

namespace App\Http\Controllers;

use App\Models\Legacy\TbAnak;
use App\Models\Legacy\TbEditPending;
use App\Models\Legacy\TbJabatan;
use App\Models\Legacy\TbOrtu;
use App\Models\Legacy\TbPegawai;
use App\Models\Legacy\TbRecycleBin;
use App\Models\Legacy\TbSuamiIstri;
use App\Services\NotificationService;
use App\Services\RbacService;
use App\Support\SimpegFieldRules;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class FamilyController extends Controller
{
    public function __construct(private readonly NotificationService $notifications) {}

    private const TYPES = [
        'children' => [
            'label' => 'Data Anak',
            'table' => 'tb_anak',
            'model' => TbAnak::class,
            'key' => 'id_anak',
            'order' => 'f.tgl_lhr',
        ],
        'parents' => [
            'label' => 'Data Orang Tua',
            'table' => 'tb_ortu',
            'model' => TbOrtu::class,
            'key' => 'id_ortu',
            'order' => 'f.id_ortu',
        ],
        'spouses' => [
            'label' => 'Data Suami/Istri',
            'table' => 'tb_suamiistri',
            'model' => TbSuamiIstri::class,
            'key' => 'id_si',
            'order' => 'f.id_si',
        ],
    ];

    /**
     * Slug route lama tetap dipertahankan. Alias Indonesia hanya untuk URL
     * yang lebih ramah pengguna; seluruh logika bisnis tetap memakai key
     * internal yang sama.
     */
    private const TYPE_ALIASES = [
        'anak' => 'children',
        'orang-tua' => 'parents',
        'suami-istri' => 'spouses',
    ];

    public function index(Request $request, string $type): Response
    {
        $type = $this->canonicalType($type);
        $definition = $this->definition($type);
        $role = strtolower((string) $request->session()->get('hak_akses'));
        abort_unless(in_array($role, ['admin', 'superadmin', 'kepala', 'user'], true), 403);

        $uid = $request->string('uid')->trim()->value();
        if ($role === 'user') {
            $uid = (string) $request->session()->get('id_pegawai');
        }

        $query = $this->familyQuery($definition);
        $this->applyScope($query, $request, $uid);

        $search = $request->string('search')->trim()->value();
        if ($search !== '') {
            $query->where(function (Builder $builder) use ($search): void {
                $like = "%{$search}%";
                $builder->where('f.nama', 'like', $like)
                    ->orWhere('f.nik', 'like', $like)
                    ->orWhere('f.status_hub', 'like', $like)
                    ->orWhere('f.pekerjaan', 'like', $like)
                    ->orWhere('p.nama', 'like', $like);
            });
        }

        $items = $query
            ->orderBy($definition['order'], $definition['order'] === 'f.tgl_lhr' ? 'asc' : 'desc')
            ->paginate(min(max($request->integer('per_page', 10), 1), 50))
            ->withQueryString()
            ->through(fn (Model $item): array => $this->presentItem($item, $type));

        $employee = $uid !== '' ? $this->employeeForScope($request, $uid) : null;

        $canManage = $role === 'admin' && app(RbacService::class)->can($request, 'family.manage');
        $canRequest = $role === 'user' && app(RbacService::class)->can($request, 'family.request');

        return Inertia::render('Family/Index', [
            'type' => $type,
            'label' => $definition['label'],
            'items' => $items,
            'employee' => $employee ? [
                'id_peg' => $employee->id_peg,
                'nama' => $employee->nama,
            ] : null,
            'filters' => [
                'uid' => $uid,
                'search' => $search,
            ],
            'canManage' => $canManage,
            'canRequest' => $canRequest,
            'canDelete' => $canManage || $canRequest,
            'pendingRequests' => $canRequest && $uid !== ''
                ? $this->pendingRequests($request, $type, $uid)
                : [],
        ]);
    }

    public function create(Request $request, string $type): Response
    {
        $type = $this->canonicalType($type);
        $this->ensureCanSubmit($request);
        $this->definition($type);

        return Inertia::render('Family/Form', [
            'type' => $type,
            'label' => self::TYPES[$type]['label'],
            'mode' => 'create',
            'record' => null,
            'employees' => $this->employeesForScope($request),
            'employeeId' => $request->string('uid')->trim()->value() ?: (string) $request->session()->get('id_pegawai', ''),
            'canRequest' => $this->isRequest($request),
        ]);
    }

    public function edit(Request $request, string $type, string $id): Response
    {
        $type = $this->canonicalType($type);
        $this->ensureCanSubmit($request);
        $definition = $this->definition($type);
        $record = $this->recordForScope($request, $definition, $id);

        return Inertia::render('Family/Form', [
            'type' => $type,
            'label' => $definition['label'],
            'mode' => 'edit',
            'record' => $this->presentFormRecord($record, $type),
            'employees' => $this->employeesForScope($request),
            'employeeId' => $record->id_peg,
            'canRequest' => $this->isRequest($request),
        ]);
    }

    public function store(Request $request, string $type): RedirectResponse
    {
        $type = $this->canonicalType($type);
        $this->ensureCanSubmit($request);
        $definition = $this->definition($type);
        $data = $this->validatedData($request, $type);
        $this->ensureTargetScope($request, $data['id_peg']);

        if ($type === 'children') {
            $this->ensureChildPositionAvailable($data['id_peg'], $data['anak_ke'] ?? null);
        }

        if ($type === 'spouses') {
            // The legacy column is NOT NULL, but is no longer part of the user-facing form.
            $data['id_pekerjaan'] = '';
        }

        if ($this->isRequest($request)) {
            $this->createPendingRequest($request, $type, 'create', $data['id_peg'], null, [], $data);

            return $this->redirectToIndex($type, $data['id_peg'], 'Pengajuan data keluarga berhasil dikirim dan menunggu persetujuan kepala cabang.');
        }

        $data['date_reg'] = now()->toDateString();

        DB::transaction(function () use ($definition, $type, &$data): void {
            if ($type === 'spouses') {
                $data['id_si'] = $this->nextSpouseId();
            }

            $definition['model']::query()->create($data);
        });

        return $this->redirectToIndex($type, $data['id_peg'], 'Data keluarga berhasil disimpan.');
    }

    public function update(Request $request, string $type, string $id): RedirectResponse
    {
        $type = $this->canonicalType($type);
        $this->ensureCanSubmit($request);
        $definition = $this->definition($type);
        $record = $this->recordForScope($request, $definition, $id);
        $data = $this->validatedData($request, $type, $id);
        $this->ensureTargetScope($request, $record->id_peg);

        $data['id_peg'] = $record->id_peg;

        if ($type === 'children') {
            $this->ensureChildPositionAvailable($record->id_peg, $data['anak_ke'] ?? null, (string) $record->getKey());
        }

        if ($this->isRequest($request)) {
            $this->createPendingRequest($request, $type, 'update', $record->id_peg, (string) $record->getKey(), $this->payloadFromRecord($record, $type), $data);

            return $this->redirectToIndex($type, $record->id_peg, 'Perubahan data keluarga berhasil diajukan dan menunggu persetujuan kepala cabang.');
        }

        $record->fill($data)->save();

        return $this->redirectToIndex($type, $record->id_peg, 'Data keluarga berhasil diperbarui.');
    }

    public function destroy(Request $request, string $type, string $id): RedirectResponse
    {
        $type = $this->canonicalType($type);
        $this->ensureCanSubmit($request);
        $definition = $this->definition($type);
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);
        $record = $this->recordForScope($request, $definition, $id);
        $this->ensureTargetScope($request, $record->id_peg);

        if ($this->isRequest($request)) {
            $this->createPendingRequest($request, $type, 'delete', $record->id_peg, (string) $record->getKey(), $this->payloadFromRecord($record, $type), $this->payloadFromRecord($record, $type), $validated['reason']);

            return $this->redirectToIndex($type, $record->id_peg, 'Pengajuan penghapusan data keluarga berhasil dikirim dan menunggu persetujuan kepala cabang.');
        }

        $uid = null;
        DB::transaction(function () use ($definition, $id, $validated, $request, &$uid): void {
            $record = $definition['model']::query()->lockForUpdate()->findOrFail($id);
            $uid = $record->id_peg;

            TbRecycleBin::query()->create([
                'tabel_by' => $definition['table'],
                'data_json' => json_encode($record->getAttributes(), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'alasan' => $validated['reason'],
                'created_by' => $request->session()->get('nama_user', 'System'),
                'created' => now(),
            ]);

            $record->delete();
        });

        return $this->redirectToIndex($type, $uid, 'Data dipindahkan ke Recycle Bin.');
    }

    private function definition(string $type): array
    {
        $type = $this->canonicalType($type);
        abort_unless(isset(self::TYPES[$type]), 404);

        return self::TYPES[$type];
    }

    private function canonicalType(string $type): string
    {
        $type = strtolower(trim($type));

        return self::TYPE_ALIASES[$type] ?? $type;
    }

    private function localizedType(string $type): string
    {
        return array_search($this->canonicalType($type), self::TYPE_ALIASES, true) ?: $this->canonicalType($type);
    }

    private function familyQuery(array $definition): Builder
    {
        return $definition['model']::query()
            ->from(''.$definition['table'].' as f')
            ->leftJoin('tb_pegawai as p', 'f.id_peg', '=', 'p.id_peg')
            ->select('f.*', 'p.nama as nama_pegawai');
    }

    private function applyScope(Builder $query, Request $request, string $uid): void
    {
        $role = strtolower((string) $request->session()->get('hak_akses'));
        $alias = 'f';

        if ($uid !== '') {
            $query->where("{$alias}.id_peg", $uid);
        }

        if ($role === 'user') {
            $query->where("{$alias}.id_peg", $request->session()->get('id_pegawai'));
        }

        if ($role === 'kepala') {
            $query->whereExists(function ($scope) use ($alias, $request): void {
                $scope->selectRaw('1')
                    ->from('tb_jabatan as scope_j')
                    ->whereColumn('scope_j.id_peg', "{$alias}.id_peg")
                    ->where('scope_j.status_jab', 'Aktif')
                    ->where('scope_j.unit_kerja', $request->session()->get('kode_kantor'));
            });
        }
    }

    private function employeeForScope(Request $request, string $uid): ?TbPegawai
    {
        $query = TbPegawai::query()->where('id_peg', $uid);
        $role = strtolower((string) $request->session()->get('hak_akses'));

        if ($role === 'user') {
            $query->where('id_peg', $request->session()->get('id_pegawai'));
        }

        if ($role === 'kepala') {
            $query->whereHas('jabatan', function (Builder $builder) use ($request): void {
                $builder->where('status_jab', 'Aktif')
                    ->where('unit_kerja', $request->session()->get('kode_kantor'));
            });
        }

        return $query->first();
    }

    private function employeesForScope(Request $request): array
    {
        $query = TbPegawai::query()->where('status_aktif', 1);
        $role = strtolower((string) $request->session()->get('hak_akses'));

        if ($role === 'user') {
            $query->where('id_peg', $request->session()->get('id_pegawai'));
        }

        if ($role === 'kepala') {
            $query->whereHas('jabatan', function (Builder $builder) use ($request): void {
                $builder->where('status_jab', 'Aktif')
                    ->where('unit_kerja', $request->session()->get('kode_kantor'));
            });
        }

        return $query->orderBy('nama')->get(['id_peg', 'nama'])->map(fn (TbPegawai $employee): array => [
            'id_peg' => $employee->id_peg,
            'nama' => $employee->nama,
        ])->all();
    }

    private function presentItem(Model $item, string $type): array
    {
        $id = (string) $item->getKey();
        $date = $item->tgl_lhr?->format('d-m-Y');

        return [
            'id' => $id,
            'id_peg' => $item->id_peg,
            'nama_pegawai' => $item->nama_pegawai ?: '-',
            'nama' => $item->nama,
            'nik' => $item->nik,
            'ttl' => trim(implode(', ', array_filter([$item->tmp_lhr, $date]))),
            'pendidikan' => $item->pendidikan,
            'pekerjaan' => $item->pekerjaan,
            'status_hub' => $item->status_hub,
            'anak_ke' => $type === 'children' ? $item->anak_ke : null,
            'bpjs' => $type === 'children' ? $item->bpjs_anak : $item->bpjs_pasangan,
            'hp' => $type === 'spouses' ? $item->hp : null,
        ];
    }

    private function presentFormRecord(Model $record, string $type): array
    {
        $fields = $type === 'children'
            ? ['id_anak', 'id_peg', 'nik', 'nama', 'tmp_lhr', 'tgl_lhr', 'pendidikan', 'pekerjaan', 'status_hub', 'anak_ke', 'bpjs_anak']
            : ($type === 'parents'
                ? ['id_ortu', 'id_peg', 'nik', 'nama', 'tmp_lhr', 'tgl_lhr', 'pendidikan', 'pekerjaan', 'status_hub']
                : ['id_si', 'id_peg', 'nik', 'nama', 'tmp_lhr', 'tgl_lhr', 'pendidikan', 'pekerjaan', 'status_hub', 'hp', 'bpjs_pasangan']);

        $data = [];
        foreach ($fields as $field) {
            $value = $record->{$field};
            $data[$field] = $value instanceof \DateTimeInterface ? $value->format('Y-m-d') : $value;
        }

        return $data;
    }

    private function validatedData(Request $request, string $type, ?string $id = null): array
    {
        $definition = $this->definition($type);

        if ($type === 'parents' && $id === null) {
            return $request->validate([
                'id_peg' => ['required', 'string', 'max:12', Rule::exists('tb_pegawai', 'id_peg')->where(fn ($query) => $query->where('status_aktif', 1))],
                'nama' => ['required', 'string', 'max:64'],
                'status_hub' => ['required', 'string', 'max:16'],
            ]);
        }

        $rules = [
            'id_peg' => ['required', 'string', 'max:12', Rule::exists('tb_pegawai', 'id_peg')->where(fn ($query) => $query->where('status_aktif', 1))],
            'nik' => ['nullable', 'string', 'max:16', SimpegFieldRules::DIGITS_ONLY],
            'nama' => ['required', 'string', 'max:255'],
            'tmp_lhr' => ['nullable', 'string', 'max:64'],
            'tgl_lhr' => ['nullable', 'date'],
            'pendidikan' => ['nullable', 'string', 'max:26'],
            'pekerjaan' => ['nullable', 'string', 'max:64', SimpegFieldRules::CONTAINS_LETTER],
            'status_hub' => ['nullable', 'string', 'max:16'],
        ];

        if ($type === 'children') {
            $uniqueNik = Rule::unique($definition['table'], 'nik');
            if ($id !== null) {
                $uniqueNik = $uniqueNik->ignore($id, $definition['key']);
            }
            $rules['nik'][] = $uniqueNik;
            $rules['anak_ke'] = ['nullable', 'string', 'max:3'];
            $rules['bpjs_anak'] = ['nullable', 'string', 'max:32', SimpegFieldRules::DIGITS_ONLY];
        }

        if ($type === 'spouses') {
            $rules['tmp_lhr'] = ['required', 'string', 'max:64'];
            $rules['tgl_lhr'] = ['required', 'date'];
            $rules['status_hub'] = ['required', 'string', 'max:16'];
            $rules['hp'] = ['nullable', 'string', 'max:13', SimpegFieldRules::DIGITS_ONLY];
            $rules['bpjs_pasangan'] = ['nullable', 'string', 'max:20', SimpegFieldRules::DIGITS_ONLY];
        }

        return $request->validate($rules, [
            'nik.regex' => 'NIK hanya boleh berisi angka.',
            'hp.regex' => 'Nomor HP hanya boleh berisi angka.',
            'pekerjaan.regex' => 'Pekerjaan harus memuat huruf.',
            'bpjs_anak.regex' => 'Nomor BPJS Anak hanya boleh berisi angka.',
            'bpjs_pasangan.regex' => 'Nomor BPJS Pasangan hanya boleh berisi angka.',
        ]);
    }

    private function ensureChildPositionAvailable(string $idPeg, ?string $anakKe, ?string $ignoreId = null): void
    {
        if ($anakKe === null || $anakKe === '') {
            return;
        }

        $exists = TbAnak::query()
            ->where('id_peg', $idPeg)
            ->where('anak_ke', $anakKe)
            ->when($ignoreId !== null, fn (Builder $query) => $query->where('id_anak', '!=', $ignoreId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'anak_ke' => 'Urutan anak tersebut sudah digunakan oleh pegawai ini.',
            ]);
        }
    }

    private function nextSpouseId(): string
    {
        $max = TbSuamiIstri::query()
            ->selectRaw('MAX(CAST(SUBSTRING(id_si, 3) AS UNSIGNED)) AS max_id')
            ->lockForUpdate()
            ->value('max_id');

        return 'SI'.str_pad((string) (((int) $max) + 1), 4, '0', STR_PAD_LEFT);
    }

    private function ensureCanSubmit(Request $request): void
    {
        $role = strtolower((string) $request->session()->get('hak_akses'));
        $permission = $role === 'admin' ? 'family.manage' : 'family.request';

        abort_unless(in_array($role, ['admin', 'user'], true) && app(RbacService::class)->can($request, $permission), 403);
    }

    private function isRequest(Request $request): bool
    {
        return strtolower((string) $request->session()->get('hak_akses')) === 'user';
    }

    private function ensureTargetScope(Request $request, string $idPeg): void
    {
        if ($this->isRequest($request)) {
            abort_unless((string) $request->session()->get('id_pegawai') === (string) $idPeg, 403);
        }
    }

    private function recordForScope(Request $request, array $definition, string $id): Model
    {
        $query = $definition['model']::query()->where($definition['key'], $id);
        if ($this->isRequest($request)) {
            $query->where('id_peg', $request->session()->get('id_pegawai'));
        }

        return $query->firstOrFail();
    }

    private function createPendingRequest(Request $request, string $type, string $action, string $idPeg, ?string $recordId, array $oldPayload, array $newPayload, ?string $requestComment = null): void
    {
        $metadata = [
            'scope' => 'family',
            'action' => $action,
            'record_id' => $recordId,
            'id_peg' => $idPeg,
        ];

        if ($requestComment !== null && trim($requestComment) !== '') {
            $metadata['request_comment'] = trim($requestComment);
        }

        $attributes = [
            'id_peg' => $idPeg,
            'jenis_data' => $this->pendingType($type, $action),
            'data_lama' => json_encode([...$metadata, 'payload' => $oldPayload], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'data_baru' => json_encode([...$metadata, 'payload' => $newPayload], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'id_user' => $request->session()->get('id_user'),
            'status_otorisasi' => 'Menunggu',
            'tanggal_pengajuan' => now(),
        ];

        if (Schema::hasColumn('tb_edit_pending', 'kode_kantor')) {
            $attributes['kode_kantor'] = $this->pendingOfficeCode($request, $idPeg);
        }

        $pending = TbEditPending::query()->create($attributes);
        $this->notifications->notifyPendingCreated($pending);
    }

    private function pendingOfficeCode(Request $request, string $idPeg): string
    {
        $office = $this->officeForEmployee($idPeg)
            ?: ((string) $request->session()->get('kode_kantor') ?: '000');

        return substr(trim($office), 0, 3) ?: '000';
    }

    private function pendingType(string $type, string $action): string
    {
        return [
            'children' => [
                'create' => 'keluarga_anak_create',
                'update' => 'keluarga_anak_update',
                'delete' => 'keluarga_anak_delete',
            ],
            'parents' => [
                'create' => 'keluarga_ortu_create',
                'update' => 'keluarga_ortu_update',
                'delete' => 'keluarga_ortu_delete',
            ],
            'spouses' => [
                'create' => 'keluarga_pasangan_create',
                'update' => 'keluarga_pasangan_update',
                'delete' => 'keluarga_pasangan_delete',
            ],
        ][$type][$action];
    }

    private function officeForEmployee(string $idPeg): ?string
    {
        return TbJabatan::query()
            ->where('id_peg', $idPeg)
            ->where('status_jab', 'Aktif')
            ->orderByDesc('tmt_jabatan')
            ->value('unit_kerja');
    }

    private function payloadFromRecord(Model $record, string $type): array
    {
        $fields = $type === 'children'
            ? ['nik', 'nama', 'tmp_lhr', 'tgl_lhr', 'pendidikan', 'pekerjaan', 'status_hub', 'anak_ke', 'bpjs_anak']
            : ($type === 'parents'
                ? ['nik', 'nama', 'tmp_lhr', 'tgl_lhr', 'pendidikan', 'pekerjaan', 'status_hub']
                : ['nik', 'nama', 'tmp_lhr', 'tgl_lhr', 'pendidikan', 'pekerjaan', 'status_hub', 'hp', 'bpjs_pasangan']);

        $payload = ['id_peg' => $record->id_peg];
        foreach ($fields as $field) {
            $value = $record->{$field};
            $payload[$field] = $value instanceof \DateTimeInterface ? $value->format('Y-m-d') : $value;
        }

        return $payload;
    }

    private function pendingRequests(Request $request, string $type, string $idPeg): array
    {
        $types = array_values($this->pendingTypesFor($type));

        return TbEditPending::query()
            ->where('id_peg', $idPeg)
            ->where('id_user', $request->session()->get('id_user'))
            ->whereIn('jenis_data', $types)
            ->orderByDesc('tanggal_pengajuan')
            ->orderByDesc('id_edit')
            ->get()
            ->map(function (TbEditPending $pending): array {
                $data = json_decode((string) $pending->data_baru, true) ?: [];
                $payload = is_array($data['payload'] ?? null) ? $data['payload'] : [];

                return [
                    'id_edit' => $pending->id_edit,
                    'action' => match ($data['action'] ?? null) {
                        'create' => 'Tambah',
                        'update' => 'Perubahan',
                        'delete' => 'Penghapusan',
                        default => 'Perubahan',
                    },
                    'nama' => $payload['nama'] ?? '-',
                    'status_hub' => $payload['status_hub'] ?? '-',
                    'status' => $pending->status_otorisasi,
                    'submitted_at' => $pending->tanggal_pengajuan?->format('d-m-Y H:i'),
                    'comment' => $data['request_comment'] ?? null,
                ];
            })
            ->values()
            ->all();
    }

    private function pendingTypesFor(string $type): array
    {
        return match ($type) {
            'children' => ['create' => 'keluarga_anak_create', 'update' => 'keluarga_anak_update', 'delete' => 'keluarga_anak_delete'],
            'parents' => ['create' => 'keluarga_ortu_create', 'update' => 'keluarga_ortu_update', 'delete' => 'keluarga_ortu_delete'],
            default => ['create' => 'keluarga_pasangan_create', 'update' => 'keluarga_pasangan_update', 'delete' => 'keluarga_pasangan_delete'],
        };
    }

    private function redirectToIndex(string $type, ?string $uid, string $message): RedirectResponse
    {
        $parameters = ['type' => $this->localizedType($type)];
        if ($uid) {
            $parameters['uid'] = $uid;
        }

        return redirect()->route('family.index', $parameters)->with('success', $message);
    }
}

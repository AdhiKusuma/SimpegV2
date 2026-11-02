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
use App\Support\SimpegFieldRules;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AuthorizationController extends Controller
{
    public function __construct(private readonly NotificationService $notifications) {}

    private const STATUSES = ['Semua', 'Menunggu', 'Disetujui', 'Ditolak'];

    private const BIODATA_FIELDS = [
        'nip' => 'nik',
        'nama' => 'nama',
        'tempat_lhr' => 'tempat_lhr',
        'tgl_lhr' => 'tgl_lhr',
        'jk' => 'jk',
        'agama' => 'agama',
        'gol_darah' => 'gol_darah',
        'status_nikah' => 'status_nikah',
        'status_kepeg' => 'status_kepeg',
        'alamat' => 'alamat',
        'no_hp' => 'telp',
        'telp' => 'telp',
        'email' => 'email',
        'bpjstk' => 'bpjstk',
        'bpjskes' => 'bpjskes',
    ];

    private const FAMILY_DEFINITIONS = [
        'keluarga_anak_create' => [
            'model' => TbAnak::class,
            'table' => 'tb_anak',
            'key' => 'id_anak',
            'action' => 'create',
            'fields' => ['nik', 'nama', 'tmp_lhr', 'tgl_lhr', 'pendidikan', 'pekerjaan', 'status_hub', 'anak_ke', 'bpjs_anak'],
        ],
        'keluarga_anak_update' => [
            'model' => TbAnak::class,
            'table' => 'tb_anak',
            'key' => 'id_anak',
            'action' => 'update',
            'fields' => ['nik', 'nama', 'tmp_lhr', 'tgl_lhr', 'pendidikan', 'pekerjaan', 'status_hub', 'anak_ke', 'bpjs_anak'],
        ],
        'keluarga_anak_delete' => [
            'model' => TbAnak::class,
            'table' => 'tb_anak',
            'key' => 'id_anak',
            'action' => 'delete',
            'fields' => [],
        ],
        'keluarga_ortu_create' => [
            'model' => TbOrtu::class,
            'table' => 'tb_ortu',
            'key' => 'id_ortu',
            'action' => 'create',
            'fields' => ['nik', 'nama', 'tmp_lhr', 'tgl_lhr', 'pendidikan', 'pekerjaan', 'status_hub'],
        ],
        'keluarga_ortu_update' => [
            'model' => TbOrtu::class,
            'table' => 'tb_ortu',
            'key' => 'id_ortu',
            'action' => 'update',
            'fields' => ['nik', 'nama', 'tmp_lhr', 'tgl_lhr', 'pendidikan', 'pekerjaan', 'status_hub'],
        ],
        'keluarga_ortu_delete' => [
            'model' => TbOrtu::class,
            'table' => 'tb_ortu',
            'key' => 'id_ortu',
            'action' => 'delete',
            'fields' => [],
        ],
        'keluarga_pasangan_create' => [
            'model' => TbSuamiIstri::class,
            'table' => 'tb_suamiistri',
            'key' => 'id_si',
            'action' => 'create',
            'fields' => ['nik', 'nama', 'tmp_lhr', 'tgl_lhr', 'pendidikan', 'pekerjaan', 'status_hub', 'hp', 'bpjs_pasangan'],
        ],
        'keluarga_pasangan_delete' => [
            'model' => TbSuamiIstri::class,
            'table' => 'tb_suamiistri',
            'key' => 'id_si',
            'action' => 'delete',
            'fields' => [],
        ],
        'keluarga_pasangan_update' => [
            'model' => TbSuamiIstri::class,
            'table' => 'tb_suamiistri',
            'key' => 'id_si',
            'action' => 'update',
            'fields' => ['nik', 'nama', 'tmp_lhr', 'tgl_lhr', 'pendidikan', 'pekerjaan', 'status_hub', 'hp', 'bpjs_pasangan'],
        ],
    ];

    private const FAMILY_LABELS = [
        'keluarga_anak_create' => 'Tambah data anak',
        'keluarga_anak_update' => 'Perubahan data anak',
        'keluarga_anak_delete' => 'Penghapusan data anak',
        'keluarga_ortu_create' => 'Tambah data orang tua',
        'keluarga_ortu_update' => 'Perubahan data orang tua',
        'keluarga_ortu_delete' => 'Penghapusan data orang tua',
        'keluarga_pasangan_create' => 'Tambah data pasangan',
        'keluarga_pasangan_update' => 'Perubahan data pasangan',
        'keluarga_pasangan_delete' => 'Penghapusan data pasangan',
    ];

    public function index(Request $request): Response
    {
        $this->ensureListAccess($request);

        $status = $request->string('status')->trim()->value() ?: 'Menunggu';
        if (! in_array($status, self::STATUSES, true)) {
            $status = 'Menunggu';
        }

        $dateBy = strtolower($request->string('date_by')->trim()->value() ?: 'pengajuan');
        if (! in_array($dateBy, ['pengajuan', 'otorisasi'], true)) {
            $dateBy = 'pengajuan';
        }

        $startDate = $this->normaliseDate($request->string('tgl_awal')->trim()->value());
        $endDate = $this->normaliseDate($request->string('tgl_akhir')->trim()->value());
        $dateColumn = $dateBy === 'otorisasi' ? 'ep.tanggal_otorisasi' : 'ep.tanggal_pengajuan';

        $query = $this->applyStatusFilter($this->pendingQuery($request), 'ep.status_otorisasi', $status)
            ->when($startDate !== null, fn (Builder $builder) => $builder->whereDate($dateColumn, '>=', $startDate))
            ->when($endDate !== null, fn (Builder $builder) => $builder->whereDate($dateColumn, '<=', $endDate))
            ->orderByRaw('COALESCE(ep.tanggal_otorisasi, ep.tanggal_pengajuan) DESC')
            ->orderByDesc('ep.id_edit');

        $pending = $query
            ->paginate(20)
            ->withQueryString()
            ->through(fn (TbEditPending $item): array => $this->presentListItem($item, $dateBy));

        return Inertia::render('Authorization/Index', [
            'pending' => $pending,
            'filters' => [
                'status' => $status,
                'date_by' => $dateBy,
                'tgl_awal' => $startDate,
                'tgl_akhir' => $endDate,
            ],
        ]);
    }

    public function history(Request $request): Response
    {
        $idUser = (string) $request->session()->get('id_user');
        $status = $request->string('status')->trim()->value() ?: 'Semua';
        if (! in_array($status, self::STATUSES, true)) {
            $status = 'Semua';
        }

        $requests = $this->applyStatusFilter(
            TbEditPending::query()->where('id_user', $idUser),
            'status_otorisasi',
            $status,
        )
            ->orderByRaw('COALESCE(tanggal_otorisasi, tanggal_pengajuan) DESC')
            ->orderByDesc('id_edit')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (TbEditPending $item): array => $this->presentHistoryItem($item));

        return Inertia::render('Authorization/History', [
            'requests' => $requests,
            'filters' => ['status' => $status],
        ]);
    }

    public function show(Request $request, int $id): Response
    {
        $this->ensureActionAccess($request);
        $pending = $this->pendingQuery($request)->where('ep.id_edit', $id)->firstOrFail();

        return Inertia::render('Authorization/Detail', [
            'pending' => $this->presentDetail($pending),
            'canProcess' => $pending->status_otorisasi === 'Menunggu',
        ]);
    }

    public function process(Request $request, int $id): RedirectResponse
    {
        $this->ensureActionAccess($request);
        $validated = $request->validate([
            'action' => ['required', 'in:approve,reject,setuju,tolak'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        $status = in_array($validated['action'], ['approve', 'setuju'], true) ? 'Disetujui' : 'Ditolak';
        $actor = (string) $request->session()->get('id_user', '0');
        $comment = trim((string) ($validated['comment'] ?? ''));

        DB::transaction(function () use ($actor, $comment, $id, $status): void {
            $pending = TbEditPending::query()->lockForUpdate()->findOrFail($id);
            if ($pending->status_otorisasi !== 'Menunggu') {
                throw ValidationException::withMessages(['action' => 'Pengajuan ini sudah diproses sebelumnya.']);
            }

            if ($status === 'Disetujui') {
                $this->applyApprovedChange($pending, $actor, $comment);
            }

            $pending->update([
                'status_otorisasi' => $status,
                'tanggal_otorisasi' => now(),
                'otorisator' => $actor,
                'komentar_otorisasi' => $comment !== '' ? $comment : null,
            ]);

            if (Schema::hasTable('tb_edit_history')) {
                DB::table('tb_edit_history')->insert([
                    'id_edit' => $pending->id_edit,
                    'action' => $status,
                    'actor' => $actor,
                    'komentar' => $comment !== '' ? $comment : null,
                    'acted_at' => now(),
                ]);
            }

            $this->notifications->notifyPendingResult($pending, $status);
        });

        return to_route('authorizations.show', ['id' => $id])->with('success', "Pengajuan berhasil {$status}.");
    }

    private function pendingQuery(Request $request): Builder
    {
        $query = TbEditPending::query()
            ->from('tb_edit_pending as ep')
            ->leftJoin('tb_user as u', 'ep.id_user', '=', 'u.id_user')
            ->leftJoin('tb_pegawai as p', 'ep.id_peg', '=', 'p.id_peg')
            ->select([
                'ep.id_edit',
                'ep.id_peg',
                'ep.jenis_data',
                'ep.data_lama',
                'ep.data_baru',
                'ep.id_user',
                'ep.status_otorisasi',
                'ep.tanggal_pengajuan',
                'ep.tanggal_otorisasi',
                'ep.otorisator',
                'ep.komentar_otorisasi',
                'u.nama_user',
                'p.nama as nama_pegawai',
            ])
            ->selectSub(
                TbJabatan::query()
                    ->select('unit_kerja')
                    ->whereColumn('tb_jabatan.id_peg', 'u.id_pegawai')
                    ->where('status_jab', 'Aktif')
                    ->orderByDesc('tmt_jabatan')
                    ->limit(1),
                'kantor_pemohon'
            );

        $role = strtolower((string) $request->session()->get('hak_akses'));
        if ($role !== 'kepala') {
            return $query;
        }

        $office = substr(trim((string) $request->session()->get('kode_kantor', '')), 0, 3);
        if ($office === '') {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $scope) use ($office): void {
            $scope->whereExists(function ($subquery) use ($office): void {
                $subquery->selectRaw('1')
                    ->from('tb_jabatan as requester_job')
                    ->whereColumn('requester_job.id_peg', 'u.id_pegawai')
                    ->where('requester_job.status_jab', 'Aktif')
                    ->whereRaw('LEFT(requester_job.unit_kerja, 3) = ?', [$office]);
            })->orWhereExists(function ($subquery) use ($office): void {
                $subquery->selectRaw('1')
                    ->from('tb_jabatan as target_job')
                    ->whereColumn('target_job.id_peg', 'ep.id_peg')
                    ->where('target_job.status_jab', 'Aktif')
                    ->whereRaw('LEFT(target_job.unit_kerja, 3) = ?', [$office]);
            });
        });
    }

    private function applyStatusFilter(Builder $query, string $column, string $status): Builder
    {
        return match ($status) {
            'Menunggu' => $query->where(function (Builder $builder) use ($column): void {
                $builder->where($column, 'pending')
                    ->orWhereNull($column)
                    ->orWhere($column, '');
            }),
            'Disetujui' => $query->where($column, 'approved'),
            'Ditolak' => $query->where($column, 'rejected'),
            default => $query,
        };
    }

    private function presentListItem(TbEditPending $item, string $dateBy): array
    {
        $date = $dateBy === 'otorisasi' && $item->tanggal_otorisasi
            ? $item->tanggal_otorisasi
            : $item->tanggal_pengajuan;

        return [
            'id_edit' => $item->id_edit,
            'id_peg' => $item->id_peg,
            'nama_pegawai' => $item->nama_pegawai,
            'jenis_data' => $this->changeLabel($item->jenis_data),
            'status_otorisasi' => $item->status_otorisasi,
            'nama_user' => $item->nama_user,
            'kantor_pemohon' => $item->kantor_pemohon,
            'waktu' => $date?->format('d-m-Y H:i'),
        ];
    }

    private function presentHistoryItem(TbEditPending $item): array
    {
        $data = $this->decodePayload($item->data_baru);
        $payload = $this->payloadValues($data);
        $subject = $payload['nama'] ?? $payload['value'] ?? $payload['jabatan'] ?? null;

        return [
            'id_edit' => $item->id_edit,
            'id_peg' => $item->id_peg,
            'nama_pegawai' => $item->nama_pegawai,
            'jenis_data' => $this->changeLabel($item->jenis_data),
            'status_otorisasi' => $item->status_otorisasi,
            'tanggal_pengajuan' => $item->tanggal_pengajuan?->format('d-m-Y H:i'),
            'tanggal_otorisasi' => $item->tanggal_otorisasi?->format('d-m-Y H:i'),
            'subject' => $subject !== null ? (string) $subject : '-',
            'komentar_otorisasi' => $item->komentar_otorisasi,
        ];
    }

    private function presentDetail(TbEditPending $item): array
    {
        $old = $this->decodePayload($item->data_lama);
        $new = $this->decodePayload($item->data_baru);
        $oldValues = $this->payloadValues($old);
        $newValues = $this->payloadValues($new);
        $keys = array_values(array_unique([...array_keys($oldValues), ...array_keys($newValues)]));
        $changes = [];

        foreach ($keys as $key) {
            if (in_array($key, ['scope', 'action', 'record_id', 'id_peg', 'request_comment'], true)) {
                continue;
            }

            $changes[] = [
                'field' => $this->fieldLabel($key),
                'old' => $this->displayValue($oldValues[$key] ?? null),
                'new' => $this->displayValue($newValues[$key] ?? null),
            ];
        }

        return [
            'id_edit' => $item->id_edit,
            'id_peg' => $item->id_peg,
            'nama_pegawai' => $item->nama_pegawai,
            'jenis_data' => $this->changeLabel($item->jenis_data),
            'status_otorisasi' => $item->status_otorisasi,
            'nama_user' => $item->nama_user,
            'kantor_pemohon' => $item->kantor_pemohon,
            'tanggal_pengajuan' => $item->tanggal_pengajuan?->format('d-m-Y H:i'),
            'tanggal_otorisasi' => $item->tanggal_otorisasi?->format('d-m-Y H:i'),
            'otorisator' => $item->otorisator,
            'komentar_otorisasi' => $item->komentar_otorisasi,
            'komentar_pemohon' => $new['request_comment'] ?? null,
            'changes' => $changes,
        ];
    }

    private function changeLabel(string $type): string
    {
        return self::FAMILY_LABELS[$type] ?? ucwords(str_replace('_', ' ', $type));
    }

    private function applyApprovedChange(TbEditPending $pending, string $actor, string $comment): void
    {
        $data = $this->decodePayload($pending->data_baru);
        if ($pending->jenis_data === 'biodata_update') {
            $this->applyBiodata($pending, $data, $actor);

            return;
        }

        if (isset(self::FAMILY_DEFINITIONS[$pending->jenis_data])) {
            $this->applyFamilyChange($pending, $data, $actor, $comment);

            return;
        }

        $column = self::BIODATA_FIELDS[$pending->jenis_data] ?? null;
        if ($column !== null) {
            $value = array_key_exists('value', $data) && count($data) === 1 ? $data['value'] : $data;
            $attributes = [$column => $this->normaliseAttribute($column, $value)];
            $this->validateEmployeeNumericAttributes($attributes);
            $this->updateEmployee($pending->id_peg, $attributes, $actor);
        }
    }

    private function applyBiodata(TbEditPending $pending, array $data, string $actor): void
    {
        $attributes = [];
        foreach (self::BIODATA_FIELDS as $source => $column) {
            if (array_key_exists($source, $data)) {
                $attributes[$column] = $this->normaliseAttribute($column, $data[$source]);
            }
        }

        $this->validateEmployeeNumericAttributes($attributes);

        if ($attributes !== []) {
            $this->updateEmployee($pending->id_peg, $attributes, $actor);
        }
    }

    private function updateEmployee(string $idPeg, array $attributes, string $actor): void
    {
        $employee = TbPegawai::query()->where('id_peg', $idPeg)->lockForUpdate()->firstOrFail();
        $employee->fill($attributes);
        $employee->updated_at = now();
        $employee->updated_by = $actor;
        $employee->save();
    }

    private function validateEmployeeNumericAttributes(array &$attributes): void
    {
        foreach (['nik', 'telp', 'bpjstk', 'bpjskes'] as $field) {
            if (($attributes[$field] ?? null) === '-') {
                $attributes[$field] = '';
            }
        }

        $rules = [
            'nik' => ['nullable', 'string', 'max:16', SimpegFieldRules::DIGITS_ONLY],
            'telp' => ['nullable', 'string', 'max:13', SimpegFieldRules::DIGITS_ONLY],
            'bpjstk' => ['nullable', 'string', 'max:12', SimpegFieldRules::DIGITS_ONLY],
            'bpjskes' => ['nullable', 'string', 'max:12', SimpegFieldRules::DIGITS_ONLY],
        ];
        $applicableRules = array_intersect_key($rules, $attributes);
        if ($applicableRules === []) {
            return;
        }

        Validator::make($attributes, $applicableRules, [
            'nik.regex' => 'NIK hanya boleh berisi angka.',
            'telp.regex' => 'Nomor telepon hanya boleh berisi angka.',
            'bpjstk.regex' => 'Nomor BPJS Ketenagakerjaan hanya boleh berisi angka.',
            'bpjskes.regex' => 'Nomor BPJS Kesehatan hanya boleh berisi angka.',
        ])->validate();
    }

    private function applyFamilyChange(TbEditPending $pending, array $data, string $actor, string $comment): void
    {
        $definition = self::FAMILY_DEFINITIONS[$pending->jenis_data];
        $payload = isset($data['payload']) && is_array($data['payload']) ? $data['payload'] : [];
        if (($payload['id_peg'] ?? $pending->id_peg) !== $pending->id_peg) {
            throw ValidationException::withMessages(['action' => 'Pengajuan keluarga tidak cocok dengan pegawai tujuan.']);
        }

        foreach (['nik', 'pekerjaan', 'hp', 'bpjs_anak', 'bpjs_pasangan'] as $field) {
            if (($payload[$field] ?? null) === '-') {
                $payload[$field] = '';
            }
        }

        Validator::make($payload, [
            'nik' => ['nullable', 'string', 'max:16', SimpegFieldRules::DIGITS_ONLY],
            'pekerjaan' => ['nullable', 'string', 'max:64', SimpegFieldRules::CONTAINS_LETTER],
            'hp' => ['nullable', 'string', 'max:13', SimpegFieldRules::DIGITS_ONLY],
            'bpjs_anak' => ['nullable', 'string', 'max:32', SimpegFieldRules::DIGITS_ONLY],
            'bpjs_pasangan' => ['nullable', 'string', 'max:20', SimpegFieldRules::DIGITS_ONLY],
        ], [
            'nik.regex' => 'NIK hanya boleh berisi angka.',
            'pekerjaan.regex' => 'Pekerjaan harus memuat huruf.',
            'hp.regex' => 'Nomor HP hanya boleh berisi angka.',
            'bpjs_anak.regex' => 'Nomor BPJS Anak hanya boleh berisi angka.',
            'bpjs_pasangan.regex' => 'Nomor BPJS Pasangan hanya boleh berisi angka.',
        ])->validate();

        $attributes = [];
        foreach ($definition['fields'] as $field) {
            if (array_key_exists($field, $payload)) {
                $attributes[$field] = $this->normaliseAttribute($field, $payload[$field]);
            }
        }

        $model = $definition['model'];
        $recordId = trim((string) ($data['record_id'] ?? ''));

        if ($definition['action'] === 'create') {
            $attributes['id_peg'] = $pending->id_peg;
            $attributes['date_reg'] = now()->toDateString();
            if ($model === TbSuamiIstri::class) {
                // Keep the legacy NOT NULL column compatible without exposing it in the form.
                $attributes['id_pekerjaan'] = '';
                $attributes['id_si'] = $this->nextSpouseId();
            }
            $model::query()->create($attributes);

            return;
        }

        $record = $model::query()
            ->where($definition['key'], $recordId)
            ->where('id_peg', $pending->id_peg)
            ->lockForUpdate()
            ->firstOrFail();

        if ($definition['action'] === 'delete') {
            $requestComment = trim((string) ($data['request_comment'] ?? ''));
            TbRecycleBin::query()->create([
                'tabel_by' => $definition['table'],
                'data_json' => json_encode($record->getAttributes(), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'alasan' => $comment !== '' ? $comment : ($requestComment !== '' ? $requestComment : 'Permintaan penghapusan disetujui melalui otorisasi.'),
                'created_by' => $actor,
                'created' => now(),
            ]);
            $record->delete();

            return;
        }

        $record->fill($attributes)->save();
    }

    private function nextSpouseId(): string
    {
        $max = TbSuamiIstri::query()
            ->selectRaw('MAX(CAST(SUBSTRING(id_si, 3) AS UNSIGNED)) AS max_id')
            ->lockForUpdate()
            ->value('max_id');

        return 'SI'.str_pad((string) (((int) $max) + 1), 4, '0', STR_PAD_LEFT);
    }

    private function decodePayload(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        $decoded = json_decode((string) $value, true);

        return is_array($decoded) ? $decoded : ['value' => (string) $value];
    }

    private function payloadValues(array $data): array
    {
        return isset($data['payload']) && is_array($data['payload']) ? $data['payload'] : $data;
    }

    private function fieldLabel(string $field): string
    {
        return [
            'nip' => 'NIK',
            'nik' => 'NIK',
            'tmp_lhr' => 'Tempat lahir',
            'tgl_lhr' => 'Tanggal lahir',
            'jk' => 'Jenis kelamin',
            'gol_darah' => 'Golongan darah',
            'status_nikah' => 'Status pernikahan',
            'status_kepeg' => 'Status kepegawaian',
            'id_pekerjaan' => 'Kategori pekerjaan',
            'status_hub' => 'Status hubungan',
            'anak_ke' => 'Urutan anak',
            'bpjs_anak' => 'BPJS anak',
            'bpjs_pasangan' => 'BPJS pasangan',
        ][$field] ?? ucwords(str_replace('_', ' ', $field));
    }

    private function displayValue(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '-';
        }

        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE) ?: '-';
        }

        return (string) $value;
    }

    private function normaliseAttribute(string $field, mixed $value): mixed
    {
        if ($value === '') {
            return null;
        }

        if (in_array($field, ['tgl_lhr'], true) && $value !== null) {
            try {
                return Carbon::parse((string) $value)->toDateString();
            } catch (\Throwable) {
                throw ValidationException::withMessages(['action' => "Format {$field} pada pengajuan tidak valid."]);
            }
        }

        return $value;
    }

    private function normaliseDate(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', trim($value))->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function ensureListAccess(Request $request): void
    {
        abort_unless(in_array(strtolower((string) $request->session()->get('hak_akses')), ['kepala', 'admin'], true), 403);
    }

    private function ensureActionAccess(Request $request): void
    {
        abort_unless(in_array(strtolower((string) $request->session()->get('hak_akses')), ['kepala', 'admin'], true), 403);
    }
}

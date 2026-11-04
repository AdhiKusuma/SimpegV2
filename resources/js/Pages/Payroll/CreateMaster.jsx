import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Banknote, CalendarDays, Landmark, Save } from 'lucide-react';
import LegacyPageShell from '../../Components/LegacyPageShell';
import { EmployeeCombobox } from '../../Components/SearchableCombobox';
import SelectCombobox from '../../Components/SelectCombobox';
import PayrollAmountInput from '../../Components/PayrollAmountInput';

const months = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

const salaryFields = [
    ['gaji_pokok', 'Gaji pokok dasar'],
    ['golongan_pegawai', 'Nominal golongan pegawai'],
    ['tunj_suami_istri', 'Tunjangan suami/istri'],
    ['tunj_anak', 'Tunjangan anak'],
    ['tunj_jabatan', 'Tunjangan jabatan'],
    ['tunj_ops', 'Tunjangan operasional'],
    ['tunj_pajak', 'Tunjangan pajak'],
    ['tunj_kinerja', 'Tunjangan kinerja'],
    ['tunj_fungsional', 'Tunjangan fungsional'],
    ['tunj_masa_kerja', 'Tunjangan masa kerja'],
    ['honor_kontrak', 'Honor kontrak'],
    ['thl', 'Tenaga harian lepas'],
    ['uang_makan_kontrak', 'Uang makan'],
    ['bpjs_kesehatan', 'BPJS kesehatan'],
    ['bpjs_tenaga_kerja', 'BPJS ketenagakerjaan'],
    ['absensi', 'Potongan absensi'],
    ['angsuran_kredit', 'Angsuran kredit'],
    ['keris', 'Potongan Keris'],
];

const deductionFieldNames = new Set([
    'bpjs_kesehatan',
    'bpjs_tenaga_kerja',
    'absensi',
    'angsuran_kredit',
    'keris',
]);

const earningFields = salaryFields.filter(([name]) => !deductionFieldNames.has(name));
const deductionFields = salaryFields.filter(([name]) => deductionFieldNames.has(name));

const initialData = {
    employee_id: '',
    office_code: '',
    bank_office_code: '',
    no_rekening: '',
    nama_nasabah: '',
    rekening_tabungan: '',
    no_hp_keris: '',
    tahun: String(new Date().getFullYear()),
    bulan: String(new Date().getMonth() + 1),
    tgl_gajian: '',
    ...Object.fromEntries(salaryFields.map(([field]) => [field, '0'])),
};

function FieldError({ id, message }) {
    return message ? <p id={id} role="alert" className="mt-1 text-xs text-red-600">{message}</p> : null;
}

function tenureLabel(startDate, endDate) {
    if (!startDate) return 'Belum tersedia';

    const parseDate = (value) => {
        const [year, month, day] = value.split('-').map(Number);
        return new Date(year, month - 1, day);
    };
    const start = parseDate(startDate);
    const end = endDate ? parseDate(endDate) : new Date();
    if (Number.isNaN(start.getTime()) || Number.isNaN(end.getTime())) return 'Belum tersedia';

    let totalMonths = (end.getFullYear() - start.getFullYear()) * 12 + end.getMonth() - start.getMonth();
    if (end.getDate() < start.getDate()) totalMonths -= 1;
    if (totalMonths < 0) return 'Belum dimulai';

    const years = Math.floor(totalMonths / 12);
    const months = totalMonths % 12;
    if (years === 0 && months === 0) return 'Kurang dari 1 bulan';
    return [years ? `${years} tahun` : null, months ? `${months} bulan` : null].filter(Boolean).join(' ');
}

function ReadOnlyItem({ label, value }) {
    return <div className="simpeg-card-soft min-w-0 rounded-xl border p-3">
        <dt className="simpeg-muted text-xs font-semibold">{label}</dt>
        <dd className="simpeg-text mt-1 break-words text-sm font-medium">{value || 'Belum tersedia'}</dd>
    </div>;
}

function SectionHeading({ icon: Icon, title, description }) {
    return <div className="mb-5 flex items-start gap-3">
        <span className="simpeg-card-soft inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border"><Icon aria-hidden="true" className="h-5 w-5" /></span>
        <div><h2 className="simpeg-text font-semibold">{title}</h2><p className="simpeg-muted mt-1 text-sm">{description}</p></div>
    </div>;
}

export default function CreateMaster({ employees = [], scopeLabel, willRequireApproval = false, flash = {} }) {
    const form = useForm(initialData);
    const employee = employees.find((item) => item.id_peg === form.data.employee_id);
    const payrollRules = employee?.payroll_field_rules ?? { supported: false, required: [], optional: [], status_label: employee?.employment_status || 'status ini' };
    const requiredPayrollFields = new Set(payrollRules.required ?? []);

    function updateField(name, value) {
        form.setData(name, value);
        form.clearErrors(name, 'payroll');
    }

    function selectEmployee(employeeId) {
        const selected = employees.find((item) => item.id_peg === employeeId);
        form.setData({
            ...form.data,
            ...Object.fromEntries(salaryFields.map(([field]) => [field, '0'])),
            employee_id: employeeId,
            office_code: selected?.office_code || '',
            bank_office_code: selected?.bank_office_code || selected?.no_rekening?.replace(/\D/g, '').slice(0, 3) || '',
            no_rekening: selected?.no_rekening || '',
            nama_nasabah: selected?.nama || '',
            rekening_tabungan: selected?.rekening_tabungan || '',
            no_hp_keris: selected?.no_hp_keris || '',
        });
        form.clearErrors('employee_id', 'office_code', 'bank_office_code', 'no_rekening', 'nama_nasabah', 'rekening_tabungan', 'no_hp_keris', 'payroll');
    }

    function updateAccount(account) {
        const bankCode = account.replace(/\D/g, '').slice(0, 3);
        form.setData({ ...form.data, no_rekening: account, bank_office_code: bankCode });
        form.clearErrors('no_rekening', 'bank_office_code', 'payroll');
    }

    function renderAmountFields(fields) {
        return fields.map(([name, label]) => <div key={name}>
            <label htmlFor={`master-${name}`} className="simpeg-muted mb-2 block text-xs font-bold uppercase tracking-wide">
                {label}{requiredPayrollFields.has(name) && <span className="ml-1 text-red-600">*</span>}
            </label>
            <PayrollAmountInput
                id={`master-${name}`}
                value={form.data[name]}
                required={requiredPayrollFields.has(name)}
                error={form.errors[name]}
                onChange={(value) => updateField(name, value)}
            />
            <FieldError id={`master-${name}-error`} message={form.errors[name]} />
        </div>);
    }

    function submit(event) {
        event.preventDefault();
        form.post('/payroll/edit', { preserveScroll: true });
    }

    return (
        <LegacyPageShell title="Tambah Master Gaji">
            <Head title="Tambah Master Gaji" />
            <Link href="/payroll/edit" className="simpeg-muted mb-5 inline-flex items-center gap-2 text-sm font-semibold hover:underline"><ArrowLeft aria-hidden="true" className="h-4 w-4" />Kembali ke Master Gaji</Link>
            <div className="mb-6">
                <p className="simpeg-muted mb-2 text-sm font-semibold uppercase tracking-[0.18em]">Master Gaji</p>
                <h1 className="simpeg-text text-3xl font-extrabold tracking-tight">Tambah data gaji pegawai</h1>
                <p className="simpeg-muted mt-2">Isi data dengan susunan yang sama seperti template import payroll.</p>
                {scopeLabel && <p className="simpeg-muted mt-2 text-sm">Cakupan: {scopeLabel}</p>}
            </div>

            {flash.error && <div role="alert" className="simpeg-card mb-5 rounded-xl border px-4 py-3 text-sm">{flash.error}</div>}
            {form.errors.payroll && <div role="alert" className="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-800">{form.errors.payroll}</div>}
            {willRequireApproval && <div role="note" className="simpeg-card-soft mb-5 rounded-xl border px-4 py-3 text-sm">Pengajuan akan dikirim ke supervisor sesuai kantor pegawai. Master Gaji dibuat setelah disetujui.</div>}

            <form onSubmit={submit} className="space-y-5">
                <section className="simpeg-card rounded-2xl border p-4 shadow-sm sm:p-6">
                    <SectionHeading icon={Banknote} title="Identitas pegawai" description="Pilih pegawai aktif. Data kepegawaian di bawah terisi dari database." />
                    <div className="grid gap-4 sm:grid-cols-2">
                        <EmployeeCombobox
                            id="employee_id"
                            employees={employees}
                            value={form.data.employee_id}
                            onChange={selectEmployee}
                            error={form.errors.employee_id}
                        />
                        <div>
                            <label htmlFor="master-office" className="simpeg-muted mb-2 block text-xs font-bold uppercase tracking-wide">Kode kantor</label>
                            <input id="master-office" value={employee ? `${employee.office_code} · ${employee.office_name}` : ''} readOnly placeholder="Terisi setelah pegawai dipilih" className="simpeg-input simpeg-text h-11 w-full rounded-xl border px-3 text-sm outline-none" />
                            <FieldError message={form.errors.office_code} />
                        </div>
                    </div>
                    {employee && <div className="mt-5 border-t pt-4">
                        <p className="simpeg-muted mb-3 text-xs font-bold uppercase tracking-wide">Data pegawai dari database</p>
                        <dl className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                            <ReadOnlyItem label="ID pegawai" value={employee.id_peg} />
                            <ReadOnlyItem label="Nama pegawai" value={employee.nama} />
                            <ReadOnlyItem label="Kode kantor" value={`${employee.office_code} · ${employee.office_name}`} />
                            <ReadOnlyItem label="Jabatan" value={employee.position} />
                            <ReadOnlyItem label="Unit kerja" value={employee.work_unit} />
                            <ReadOnlyItem label="Status" value={employee.employment_status} />
                            <ReadOnlyItem label="Masa kerja" value={tenureLabel(employee.start_date, form.data.tgl_gajian)} />
                            <ReadOnlyItem label="Suami/istri" value={`${employee.spouse_count} data`} />
                            <ReadOnlyItem label="Anak" value={`${employee.child_count} data`} />
                        </dl>
                    </div>}
                    {employee && payrollRules.supported === false && <p role="alert" className="mt-4 rounded-xl border border-amber-300 bg-amber-50 p-3 text-sm font-medium text-amber-900">Status kepegawaian ini belum memiliki aturan payroll. Hubungi administrator sebelum melanjutkan.</p>}
                    {!employees.length && <p className="simpeg-muted mt-4 rounded-xl border p-3 text-sm">Tidak ada pegawai aktif dengan jabatan dan kantor terdaftar pada cakupan ini.</p>}
                </section>

                <section className="simpeg-card rounded-2xl border p-4 shadow-sm sm:p-6">
                    <SectionHeading icon={Landmark} title="Rekening payroll" description="Jika tersedia, rekening dan data nasabah diisi dari payroll terakhir pegawai. Periksa kembali sebelum menyimpan." />
                    <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                        <div>
                            <label htmlFor="master-bank-code" className="simpeg-muted mb-2 block text-xs font-bold uppercase tracking-wide">Kode kantor rekening <span className="text-red-600">*</span></label>
                            <input id="master-bank-code" inputMode="numeric" maxLength="3" required value={form.data.bank_office_code} onChange={(event) => updateField('bank_office_code', event.target.value.replace(/\D/g, '').slice(0, 3))} className="simpeg-input h-11 w-full rounded-xl border px-3 text-sm outline-none focus:ring-4 focus:ring-[var(--simpeg-focus-ring)]" />
                            <FieldError message={form.errors.bank_office_code} />
                        </div>
                        <div>
                            <label htmlFor="master-account" className="simpeg-muted mb-2 block text-xs font-bold uppercase tracking-wide">No. rekening <span className="text-red-600">*</span></label>
                            <input id="master-account" required maxLength="64" value={form.data.no_rekening} onChange={(event) => updateAccount(event.target.value)} className="simpeg-input h-11 w-full rounded-xl border px-3 text-sm outline-none focus:ring-4 focus:ring-[var(--simpeg-focus-ring)]" />
                            <FieldError message={form.errors.no_rekening} />
                        </div>
                        <div>
                            <label htmlFor="master-account-name" className="simpeg-muted mb-2 block text-xs font-bold uppercase tracking-wide">Nama nasabah <span className="text-red-600">*</span></label>
                            <input id="master-account-name" required maxLength="150" value={form.data.nama_nasabah} onChange={(event) => updateField('nama_nasabah', event.target.value)} className="simpeg-input h-11 w-full rounded-xl border px-3 text-sm outline-none focus:ring-4 focus:ring-[var(--simpeg-focus-ring)]" />
                            <FieldError message={form.errors.nama_nasabah} />
                        </div>
                        <div>
                            <label htmlFor="master-savings-account" className="simpeg-muted mb-2 block text-xs font-bold uppercase tracking-wide">No. rekening tabungan</label>
                            <input id="master-savings-account" maxLength="64" value={form.data.rekening_tabungan} onChange={(event) => updateField('rekening_tabungan', event.target.value)} className="simpeg-input h-11 w-full rounded-xl border px-3 text-sm outline-none focus:ring-4 focus:ring-[var(--simpeg-focus-ring)]" />
                            <FieldError message={form.errors.rekening_tabungan} />
                        </div>
                        <div>
                            <label htmlFor="master-keris-phone" className="simpeg-muted mb-2 block text-xs font-bold uppercase tracking-wide">No. HP Keris</label>
                            <input id="master-keris-phone" inputMode="tel" maxLength="32" value={form.data.no_hp_keris} onChange={(event) => updateField('no_hp_keris', event.target.value)} className="simpeg-input h-11 w-full rounded-xl border px-3 text-sm outline-none focus:ring-4 focus:ring-[var(--simpeg-focus-ring)]" />
                            <FieldError message={form.errors.no_hp_keris} />
                        </div>
                    </div>
                </section>

                <section className="simpeg-card rounded-2xl border p-4 shadow-sm sm:p-6">
                    <SectionHeading icon={CalendarDays} title="Periode dan tanggal gajian" description="Tanggal gajian wajib berada pada tahun dan bulan yang dipilih." />
                    <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                        <div>
                            <label htmlFor="master-year" className="simpeg-muted mb-2 block text-xs font-bold uppercase tracking-wide">Tahun <span className="text-red-600">*</span></label>
                            <input id="master-year" type="number" min="2000" max="2100" required value={form.data.tahun} onChange={(event) => updateField('tahun', event.target.value)} className="simpeg-input h-11 w-full rounded-xl border px-3 text-sm outline-none focus:ring-4 focus:ring-[var(--simpeg-focus-ring)]" />
                            <FieldError message={form.errors.tahun} />
                        </div>
                        <div>
                            <label htmlFor="master-month" className="simpeg-muted mb-2 block text-xs font-bold uppercase tracking-wide">Bulan <span className="text-red-600">*</span></label>
                            <SelectCombobox
                                id="master-month"
                                value={form.data.bulan}
                                onChange={(value) => updateField('bulan', value)}
                                options={months.map((month, index) => ({ value: String(index + 1), label: month }))}
                                placeholder="Pilih bulan"
                                required
                            />
                            <FieldError message={form.errors.bulan} />
                        </div>
                        <div>
                            <label htmlFor="master-pay-date" className="simpeg-muted mb-2 block text-xs font-bold uppercase tracking-wide">Tanggal gajian <span className="text-red-600">*</span></label>
                            <input id="master-pay-date" type="date" required value={form.data.tgl_gajian} onChange={(event) => updateField('tgl_gajian', event.target.value)} className="simpeg-input h-11 w-full rounded-xl border px-3 text-sm outline-none focus:ring-4 focus:ring-[var(--simpeg-focus-ring)]" />
                            <FieldError message={form.errors.tgl_gajian} />
                        </div>
                    </div>
                </section>

                <section className="simpeg-card overflow-hidden rounded-2xl border shadow-sm">
                    <div className="border-b p-4 sm:p-6"><SectionHeading icon={Banknote} title="Komponen gaji dan potongan" description="Nominal golongan pegawai ditambahkan ke gaji pokok; jika kosong dihitung Rp0. Jumlah gaji, potongan, dan gaji bersih dihitung otomatis." /></div>
                    <div className="space-y-6 p-4 sm:p-6">
                        <section aria-labelledby="master-earnings-title">
                            <div className="mb-4 flex flex-col gap-2 border-b pb-3 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                    <div className="flex flex-wrap items-center gap-2">
                                        <h3 id="master-earnings-title" className="simpeg-text text-base font-bold">Pendapatan dan tunjangan</h3>
                                        <span className="simpeg-card-soft simpeg-text inline-flex items-center rounded-full border px-3 py-1 text-[11px] font-bold uppercase tracking-wide">Non-potongan</span>
                                    </div>
                                    <p className="simpeg-muted mt-1 text-sm">Komponen yang menambah jumlah gaji sebelum potongan diterapkan.</p>
                                </div>
                            </div>
                            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">{renderAmountFields(earningFields)}</div>
                        </section>

                        <section aria-labelledby="master-deductions-title" className="border-t pt-6">
                            <div className="mb-4 flex flex-col gap-2 border-b pb-3 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                    <div className="flex flex-wrap items-center gap-2">
                                        <h3 id="master-deductions-title" className="simpeg-text text-base font-bold">Potongan</h3>
                                        <span className="simpeg-card-soft simpeg-text inline-flex items-center rounded-full border px-3 py-1 text-[11px] font-bold uppercase tracking-wide">Mengurangi gaji bersih</span>
                                    </div>
                                    <p className="simpeg-muted mt-1 text-sm">BPJS, absensi, angsuran kredit, dan potongan Keris.</p>
                                </div>
                            </div>
                            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">{renderAmountFields(deductionFields)}</div>
                        </section>
                    </div>
                    <p className="simpeg-muted px-4 pb-4 text-sm sm:px-6">Field bertanda * wajib diisi. Field lainnya bernilai awal 0 dan dapat diubah jika ada nominal. Potongan absensi dicatat terpisah setelah rekap bulanan; angsuran kredit dan potongan lain ikut mengurangi gaji bersih serta Total ekspor.</p>
                    <div className="flex flex-col-reverse justify-end gap-2 border-t p-4 sm:flex-row sm:p-5">
                        <Link href="/payroll/edit" className="simpeg-utility inline-flex min-h-11 items-center justify-center rounded-xl border px-5 text-sm font-semibold">Batal</Link>
                        <button type="submit" disabled={form.processing || !employees.length} className="simpeg-primary inline-flex min-h-11 items-center justify-center gap-2 rounded-xl px-5 text-sm font-bold disabled:opacity-60"><Save aria-hidden="true" className="h-4 w-4" />{form.processing ? 'Memproses…' : willRequireApproval ? 'Ajukan Master Gaji' : 'Simpan Master Gaji'}</button>
                    </div>
                </section>
            </form>
        </LegacyPageShell>
    );
}

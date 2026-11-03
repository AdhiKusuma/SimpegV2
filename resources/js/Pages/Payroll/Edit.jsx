import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Banknote, FileSpreadsheet, Save } from 'lucide-react';
import LegacyPageShell from '../../Components/LegacyPageShell';
import PayrollAmountInput from '../../Components/PayrollAmountInput';

const fields = [
    ['gaji_pokok', 'Gaji pokok dasar'], ['golongan_pegawai', 'Nominal golongan pegawai'], ['tunj_suami_istri', 'Tunjangan suami/istri'], ['tunj_anak', 'Tunjangan anak'],
    ['tunj_jabatan', 'Tunjangan jabatan'], ['tunj_ops', 'Tunjangan operasional'], ['tunj_pajak', 'Tunjangan pajak'],
    ['tunj_kinerja', 'Tunjangan kinerja'], ['tunj_fungsional', 'Tunjangan fungsional'], ['tunj_masa_kerja', 'Tunjangan masa kerja'],
    ['honor_kontrak', 'Honor kontrak'], ['thl', 'Tenaga harian lepas'], ['uang_makan_kontrak', 'Uang makan'],
    ['bpjs_kesehatan', 'BPJS kesehatan'], ['bpjs_tenaga_kerja', 'BPJS ketenagakerjaan'],
    ['absensi', 'Potongan absensi'], ['angsuran_kredit', 'Angsuran kredit'], ['keris', 'Potongan Keris'],
];
const detailFields = [['rekening_tabungan', 'No. rekening tabungan'], ['no_hp_keris', 'No. HP Keris']];

export default function Edit({ employee, willRequireApproval = false, canExport = false, flash = {} }) {
    const editableFields = [...fields, ...detailFields];
    const form = useForm(Object.fromEntries(editableFields.map(([name]) => [name, employee.amounts[name] ?? (name === 'rekening_tabungan' || name === 'no_hp_keris' ? '' : '0.00')])));
    const payrollRules = employee.payroll_field_rules ?? { supported: false, required: [], optional: [], status_label: employee.employment_status || 'status ini' };
    const requiredPayrollFields = new Set(payrollRules.required ?? []);
    const exportHref = `/payroll/master/export?${new URLSearchParams({ employee_id: employee.employee_id })}`;
    function submit(event) {
        event.preventDefault();
        form.put(`/payroll/edit/${encodeURIComponent(employee.employee_id)}`, { preserveScroll: true });
    }

    return (
        <LegacyPageShell title="Edit Master Gaji">
            <Head title="Edit Master Gaji" />
            <Link href="/payroll/edit" className="simpeg-muted mb-5 inline-flex items-center gap-2 text-sm font-semibold hover:underline"><ArrowLeft aria-hidden="true" className="h-4 w-4" /> Kembali ke Master Gaji</Link>
            <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p className="simpeg-muted mb-2 text-sm font-semibold uppercase tracking-[0.18em]">Master Gaji</p>
                    <h1 className="simpeg-text text-3xl font-extrabold tracking-tight">Edit data gaji</h1>
                    <p className="simpeg-muted mt-2">{employee.employee_name} · ID {employee.employee_id} · {employee.office_name}</p>
                </div>
                {canExport && <a href={exportHref} className="simpeg-utility inline-flex min-h-11 items-center justify-center gap-2 self-start rounded-xl border px-4 text-sm font-bold sm:self-auto"><FileSpreadsheet aria-hidden="true" className="h-4 w-4" />Export XLSX</a>}
            </div>
            {flash.error && <div role="alert" className="mb-5 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-medium text-amber-900">{flash.error}</div>}
            {willRequireApproval && <div role="note" className="mb-5 rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900">Perubahan akan dikirim ke approval supervisor kantor terkait. Master Gaji baru diperbarui setelah disetujui.</div>}
            {form.errors.payroll && <div role="alert" className="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-800">{form.errors.payroll}</div>}
            <form onSubmit={submit} className="simpeg-card overflow-hidden rounded-2xl border shadow-sm">
                <div className="flex items-center gap-3 border-b p-4 sm:p-5"><span className="simpeg-card-soft inline-flex h-10 w-10 items-center justify-center rounded-xl border"><Banknote aria-hidden="true" className="h-5 w-5" /></span><div><h2 className="simpeg-text font-semibold">Komponen gaji dan potongan</h2><p className="simpeg-muted text-sm">Nominal golongan pegawai ditambahkan ke gaji pokok; nilai kosong dihitung Rp0. Field bertanda * wajib diisi.</p></div></div>
                {payrollRules.supported === false && <p role="alert" className="m-4 rounded-xl border border-amber-300 bg-amber-50 p-3 text-sm font-medium text-amber-900 sm:m-6">Status kepegawaian ini belum memiliki aturan payroll. Hubungi administrator sebelum menyimpan.</p>}
                <div className="grid gap-4 p-4 sm:grid-cols-2 sm:p-6 xl:grid-cols-3">
                    {fields.map(([name, label]) => <div key={name}>
                        <label htmlFor={`salary-${name}`} className="simpeg-muted mb-2 block text-xs font-bold uppercase tracking-wide">
                            {label}{requiredPayrollFields.has(name) && <span className="ml-1 text-red-600">*</span>}
                        </label>
                        <PayrollAmountInput
                            id={`salary-${name}`}
                            value={form.data[name] ?? ''}
                            required={requiredPayrollFields.has(name)}
                            error={form.errors[name]}
                            onChange={(value) => { form.setData(name, value); form.clearErrors(name, 'payroll'); }}
                        />
                        {form.errors[name] && <p id={`salary-${name}-error`} role="alert" className="mt-1 text-xs text-red-600">{form.errors[name]}</p>}
                    </div>)}
                </div>
                <div className="grid gap-4 border-t p-4 sm:grid-cols-2 sm:p-6">
                    {detailFields.map(([name, label]) => <div key={name}>
                        <label htmlFor={`salary-${name}`} className="simpeg-muted mb-2 block text-xs font-bold uppercase tracking-wide">{label}</label>
                        <input id={`salary-${name}`} type="text" inputMode={name === 'no_hp_keris' ? 'tel' : undefined} maxLength={name === 'no_hp_keris' ? 32 : 64} value={form.data[name]} onChange={(event) => form.setData(name, event.target.value)} className="simpeg-input h-11 w-full rounded-xl border px-3 text-sm outline-none focus:ring-4 focus:ring-[var(--simpeg-focus-ring)]" />
                        {form.errors[name] && <p role="alert" className="mt-1 text-xs text-red-600">{form.errors[name]}</p>}
                    </div>)}
                </div>
                <p className="simpeg-muted px-4 pb-4 text-sm sm:px-6">Absensi dicatat terpisah setelah rekap bulanan. Sistem mengaitkan potongannya ke Tunj. Operasional untuk Tetap/Capeg atau Uang Makan Kontrak untuk Kontrak, lalu menghitung ulang gaji bersih setelah semua potongan.</p>
                {form.errors.employee_id && <p role="alert" className="px-5 pb-3 text-sm text-red-600">{form.errors.employee_id}</p>}
                <div className="flex flex-col-reverse justify-end gap-2 border-t p-4 sm:flex-row sm:p-5">
                    <Link href="/payroll/edit" className="simpeg-utility inline-flex min-h-11 items-center justify-center rounded-xl border px-5 text-sm font-semibold">Batal</Link>
                    <button type="submit" disabled={form.processing} className="simpeg-primary inline-flex min-h-11 items-center justify-center gap-2 rounded-xl px-5 text-sm font-bold disabled:opacity-60"><Save aria-hidden="true" className="h-4 w-4" />{form.processing ? 'Memproses…' : willRequireApproval ? 'Ajukan perubahan' : 'Simpan perubahan'}</button>
                </div>
            </form>
        </LegacyPageShell>
    );
}

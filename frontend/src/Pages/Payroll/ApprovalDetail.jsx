import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Check, LockKeyhole, X } from 'lucide-react';
import LegacyPageShell from '../../Components/LegacyPageShell';
import { formatRupiah } from '../../lib/payrollMoney';

const fields = [
    ['gaji_pokok', 'Gaji pokok dasar'], ['golongan_pegawai', 'Nominal golongan pegawai'], ['tunj_suami_istri', 'Tunjangan suami/istri'], ['tunj_anak', 'Tunjangan anak'],
    ['tunj_jabatan', 'Tunjangan jabatan'], ['tunj_ops', 'Tunjangan operasional'], ['tunj_pajak', 'Tunjangan pajak'],
    ['tunj_kinerja', 'Tunjangan kinerja'], ['tunj_fungsional', 'Tunjangan fungsional'], ['tunj_masa_kerja', 'Tunjangan masa kerja'],
    ['honor_kontrak', 'Honor kontrak'], ['thl', 'Tenaga harian lepas'], ['uang_makan_kontrak', 'Uang makan'],
    ['bpjs_kesehatan', 'BPJS kesehatan'], ['bpjs_tenaga_kerja', 'BPJS ketenagakerjaan'], ['keris', 'Potongan Keris'], ['total', 'Total'],
];

function pageLabel(label) {
    return label === '&laquo; Previous' ? 'Sebelumnya' : label === 'Next &raquo;' ? 'Berikutnya' : label;
}

function approvalTitle(type) {
    if (type === 'import') return 'Import payroll';
    if (type === 'manual_create') return 'Tambah Master Gaji';
    if (type === 'manual_delete') return 'Hapus Master Gaji';
    return 'Perubahan Master Gaji';
}

export default function ApprovalDetail({ approval, entries, canDecide, flash = {} }) {
    const form = useForm({ reason: '' });
    function decide(decision, event) {
        event.preventDefault();
        if (decision === 'reject' && form.data.reason.trim().length < 3) {
            form.setError('reason', 'Alasan penolakan minimal 3 karakter.');
            return;
        }
        form.clearErrors();
        form.transform((data) => ({ ...data, decision })).post(`/payroll/approvals/${approval.id}/decision`, { preserveScroll: true });
    }

    return (
        <LegacyPageShell title="Detail Approval Payroll">
            <Head title="Detail Approval Payroll" />
            <Link href="/payroll/approvals" className="simpeg-muted mb-5 inline-flex items-center gap-2 text-sm font-semibold hover:underline"><ArrowLeft aria-hidden="true" className="h-4 w-4" /> Kembali ke approval</Link>
            <div className="mb-6">
                <p className="simpeg-muted mb-2 text-sm font-semibold uppercase tracking-[0.18em]">Pengajuan Payroll</p>
                <h1 className="simpeg-text text-3xl font-extrabold tracking-tight">{approvalTitle(approval.type)}</h1>
                <p className="simpeg-muted mt-2">Diajukan oleh {approval.requester} · {approval.submitted_at || '—'}{approval.period ? ` · Periode ${approval.period}` : ''}</p>
                {approval.office_names?.length > 0 && <p className="simpeg-muted mt-1 text-sm">Kantor: {approval.office_names.map((office) => `${office.name} (${office.code})`).join(', ')}</p>}
            </div>
            {flash.error && <div role="alert" className="mb-5 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-medium text-amber-900">{flash.error}</div>}
            <section className="simpeg-card overflow-hidden rounded-2xl border shadow-sm">
                <div className="border-b p-4 sm:p-5"><h2 className="simpeg-text font-semibold">Perubahan yang diajukan</h2><p className="simpeg-muted mt-1 text-sm">{Number(approval.employee_count || 0).toLocaleString('id-ID')} pegawai</p></div>
                <div className="divide-y simpeg-divider">
                    {entries.data.map((entry) => <article key={entry.employee_id} className="p-4 sm:p-5">
                        <div className="mb-4 flex flex-wrap items-start justify-between gap-2"><div><h3 className="simpeg-text font-semibold">{entry.employee_name}</h3><p className="simpeg-muted mt-1 font-mono text-xs">{entry.employee_id} · Kantor {entry.office_code}</p></div><span className="simpeg-card-soft rounded-full border px-2.5 py-1 text-xs">{entry.old_data ? 'Perubahan data' : 'Data baru'}</span></div>
                        {approval.type === 'manual_delete' ? <div className="simpeg-card-soft rounded-xl border p-4">
                            <p className="simpeg-text text-sm font-semibold">Master Gaji ini akan dihapus setelah pengajuan disetujui.</p>
                            <p className="simpeg-muted mt-1 text-sm">Total gaji saat ini: {entry.old_data?.total == null ? '—' : formatRupiah(entry.old_data.total)}. Riwayat payroll yang sudah tercatat tetap dipertahankan.</p>
                        </div> : <div className="overflow-x-auto rounded-xl border"><table className="w-full min-w-[560px] text-left text-sm"><thead className="simpeg-card-soft simpeg-muted text-xs uppercase tracking-wide"><tr><th className="px-4 py-3">Komponen</th><th className="px-4 py-3 text-right">Data lama</th><th className="px-4 py-3 text-right">Data baru</th></tr></thead><tbody className="divide-y simpeg-divider">{fields.map(([key, label]) => <tr key={key}><th scope="row" className="simpeg-muted px-4 py-2.5 font-medium">{label}</th><td className="simpeg-muted px-4 py-2.5 text-right tabular-nums">{entry.old_data ? (entry.old_data[key] == null ? '—' : formatRupiah(entry.old_data[key])) : '—'}</td><td className="simpeg-text px-4 py-2.5 text-right font-semibold tabular-nums">{entry.new_data?.[key] == null ? '—' : formatRupiah(entry.new_data[key])}</td></tr>)}</tbody></table></div>}
                    </article>)}
                </div>
                {entries.links?.length > 3 && <nav aria-label="Halaman rincian pengajuan" className="flex flex-wrap justify-end gap-2 border-t p-4">{entries.links.map((link, index) => link.url ? <Link key={`${index}-${link.label}`} href={link.url} preserveScroll className={`simpeg-utility inline-flex min-h-9 items-center rounded-lg border px-3 text-sm ${link.active ? 'font-bold' : ''}`}>{pageLabel(link.label)}</Link> : <span key={`${index}-${link.label}`} className="simpeg-muted inline-flex min-h-9 items-center rounded-lg border px-3 text-sm opacity-60">{pageLabel(link.label)}</span>)}</nav>}
                {canDecide && <form className="border-t p-4 sm:p-5">
                    <label htmlFor="payroll-approval-reason" className="simpeg-muted mb-2 block text-xs font-bold uppercase tracking-wide">Catatan keputusan <span className="normal-case font-normal">(wajib jika menolak)</span></label>
                    <textarea id="payroll-approval-reason" rows="3" value={form.data.reason} onChange={(event) => { form.setData('reason', event.target.value); form.clearErrors('reason'); }} className="simpeg-input w-full rounded-xl border p-3 text-sm outline-none focus:ring-4 focus:ring-[var(--simpeg-focus-ring)]" placeholder="Tulis alasan atau catatan untuk pengaju" />
                    {form.errors.reason && <p role="alert" className="mt-1 text-sm text-red-600">{form.errors.reason}</p>}
                    <p className="simpeg-muted mt-3 flex items-start gap-2 text-xs"><LockKeyhole aria-hidden="true" className="mt-0.5 h-3.5 w-3.5 shrink-0" />{approval.type === 'manual_delete' ? 'Master Gaji hanya dihapus setelah persetujuan; riwayat payroll tetap dipertahankan.' : 'Master Gaji dan snapshot payroll hanya diperbarui setelah persetujuan.'} Pengaju tidak dapat menyetujui permintaannya sendiri.</p>
                    <div className="mt-4 flex flex-col-reverse justify-end gap-2 sm:flex-row">
                        <button type="button" disabled={form.processing} onClick={(event) => decide('reject', event)} className="simpeg-utility inline-flex min-h-11 items-center justify-center gap-2 rounded-xl border px-5 text-sm font-semibold disabled:opacity-60"><X aria-hidden="true" className="h-4 w-4" />Tolak pengajuan</button>
                        <button type="button" disabled={form.processing} onClick={(event) => decide('approve', event)} className="simpeg-primary inline-flex min-h-11 items-center justify-center gap-2 rounded-xl px-5 text-sm font-bold disabled:opacity-60"><Check aria-hidden="true" className="h-4 w-4" />Setujui dan terapkan</button>
                    </div>
                </form>}
                {!canDecide && <div className="simpeg-muted border-t p-4 text-sm">Pengajuan ini sudah diproses dengan status {approval.status}.</div>}
            </section>
        </LegacyPageShell>
    );
}

import { Head, router, useForm } from '@inertiajs/react';
import { Download, Eye, FileText, RefreshCw, Trash2, Upload, X } from 'lucide-react';
import { useMemo, useRef, useState } from 'react';
import { EmployeeCombobox } from '../../../Components/SearchableCombobox';
import SelectCombobox from '../../../Components/SelectCombobox';
import { Button } from '../../../Components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '../../../Components/ui/card';
import LegacyPageShell from '../../../Components/LegacyPageShell';

function formatBytes(value) {
    const bytes = Number(value || 0);
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;

    return `${(bytes / (1024 * 1024)).toFixed(2)} MB`;
}

export default function DataDiriIndex({ documents = [], employees = [], selectedEmployee = null, isAdministrative = false, types = [], flash = {} }) {
    const [preview, setPreview] = useState(null);
    const [inputKey, setInputKey] = useState(0);
    const fileInput = useRef(null);
    const selectedEmployeeId = selectedEmployee?.id_peg || '';
    const uploadForm = useForm({ jenis: types[0]?.value || '', file_pdf: null });
    const documentsByType = useMemo(() => Object.fromEntries(documents.map((document) => [document.jenis, document])), [documents]);
    const selectedFileName = uploadForm.data.file_pdf?.name || 'Belum ada file dipilih';

    function chooseEmployee(value) {
        uploadForm.reset('file_pdf');
        setInputKey((key) => key + 1);
        router.get('/files/data-diri', value ? { pegawai_id: value } : {}, { preserveState: true, preserveScroll: true, replace: true });
    }

    function submitUpload(event) {
        event.preventDefault();
        if (!selectedEmployeeId || !uploadForm.data.file_pdf) return;

        uploadForm.post(`/files/data-diri/${encodeURIComponent(selectedEmployeeId)}`, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                uploadForm.reset('file_pdf');
                setInputKey((key) => key + 1);
            },
        });
    }

    function selectType(type) {
        uploadForm.setData('jenis', type);
        fileInput.current?.click();
    }

    function removeDocument(document) {
        if (!window.confirm(`Hapus dokumen ${document.label}? Dokumen yang dihapus tidak dapat dipulihkan.`)) return;

        router.delete(`/files/data-diri/${encodeURIComponent(selectedEmployeeId)}/document/${document.id}`, { preserveScroll: true });
    }

    return (
        <LegacyPageShell title="Data Diri">
            <Head title="Data Diri" />

            <div className="mb-7">
                <div>
                    <p className="simpeg-muted mb-2 text-xs font-bold uppercase tracking-[0.2em]">FILES · DATA DIRI</p>
                    <h1 className="simpeg-text text-3xl font-extrabold tracking-tight sm:text-4xl">Dokumen Data Diri</h1>
                    <p className="simpeg-muted mt-2 max-w-3xl leading-6">Kelola KTP, KK, NPWP, dan ijazah secara aman dalam satu tempat. <span className="simpeg-text font-semibold">{documents.length}/{types.length} dokumen tersimpan.</span></p>
                </div>
            </div>

            {flash.success && <div role="status" className="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">{flash.success}</div>}
            {uploadForm.errors.file_pdf && <div role="alert" className="mb-5 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-800">{uploadForm.errors.file_pdf}</div>}
            {uploadForm.errors.jenis && <div role="alert" className="mb-5 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-800">{uploadForm.errors.jenis}</div>}

            {isAdministrative && <Card className="mb-5">
                <CardHeader className="pb-4">
                    <CardTitle>Pilih pegawai</CardTitle>
                    <CardDescription>Admin dapat mengelola dokumen satu pegawai pada satu waktu.</CardDescription>
                </CardHeader>
                <CardContent className="!pt-0">
                    <EmployeeCombobox employees={employees} value={selectedEmployeeId} onChange={chooseEmployee} label="Pegawai" placeholder="Cari nama atau ID pegawai" required={false} />
                </CardContent>
            </Card>}

            {!selectedEmployee ? <Card><CardContent className="p-8 text-center"><FileText className="simpeg-subtle mx-auto h-10 w-10" /><p className="simpeg-text mt-4 font-semibold">Pilih pegawai untuk mulai mengelola dokumen.</p></CardContent></Card> : <>
                <Card className="relative z-20 mb-6 overflow-visible">
                    <CardHeader className="border-b simpeg-border pb-5">
                        <CardTitle>Unggah atau ganti dokumen</CardTitle>
                        <CardDescription><span className="simpeg-text font-semibold">{selectedEmployee.nama}</span> · {selectedEmployee.id_peg}. Satu jenis dokumen hanya memiliki satu file aktif.</CardDescription>
                    </CardHeader>
                    <CardContent className="!pt-5 sm:!pt-6">
                        <form onSubmit={submitUpload} className="grid gap-5 lg:grid-cols-[minmax(0,0.8fr)_minmax(0,1.4fr)_auto] lg:items-start">
                            <SelectCombobox
                                id="data-diri-jenis"
                                label="Jenis dokumen"
                                value={uploadForm.data.jenis}
                                options={types}
                                optionValue={(type) => type.value}
                                optionLabel={(type) => `${type.label}${documentsByType[type.value] ? ' · ganti' : ''}`}
                                placeholder="Pilih jenis dokumen"
                                onChange={(value) => uploadForm.setData('jenis', value)}
                            />

                            <div>
                                <span className="simpeg-muted mb-2 block text-xs font-bold uppercase tracking-wide">File PDF</span>
                                <div className="simpeg-input flex h-11 min-w-0 items-center gap-3 rounded-xl border px-2">
                                    <label htmlFor="data-diri-file-pdf" className="simpeg-utility inline-flex h-8 shrink-0 cursor-pointer items-center gap-2 rounded-lg border px-3 text-xs font-bold"><Upload className="h-4 w-4" /> Pilih file</label>
                                    <span className="simpeg-muted min-w-0 truncate text-sm" title={selectedFileName}>{selectedFileName}</span>
                                </div>
                                <input key={inputKey} id="data-diri-file-pdf" ref={fileInput} type="file" accept="application/pdf,.pdf" onChange={(event) => uploadForm.setData('file_pdf', event.target.files?.[0] || null)} className="sr-only" />
                                <p className="simpeg-muted mt-2 text-xs">PDF maksimal 2 MB. Sistem akan mengoptimalkan ukuran file bila memungkinkan.</p>
                            </div>

                            <Button type="submit" disabled={uploadForm.processing || !uploadForm.data.file_pdf} className="w-full lg:mt-[1.65rem] lg:w-auto"><Upload className="h-4 w-4" />{uploadForm.processing ? 'Menyimpan...' : 'Simpan PDF'}</Button>
                        </form>
                    </CardContent>
                </Card>

                <div className="grid items-start gap-4 md:grid-cols-2 xl:grid-cols-4">
                    {types.map((type) => {
                        const document = documentsByType[type.value];

                        return <Card key={type.value} className="overflow-hidden">
                            <CardHeader className="flex-row items-start justify-between gap-3 pb-4">
                                <div className="flex min-w-0 items-start gap-3">
                                    <div className="simpeg-utility flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border"><FileText className="h-5 w-5" /></div>
                                    <div className="min-w-0">
                                        <CardTitle className="text-base font-bold">{type.label}</CardTitle>
                                        <CardDescription className="mt-1 truncate" title={document?.nama_asli}>{document ? document.nama_asli : 'Belum ada dokumen aktif'}</CardDescription>
                                    </div>
                                </div>
                                <span className={`shrink-0 rounded-full px-2.5 py-1 text-xs font-bold ${document ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-600'}`}>{document ? 'Tersedia' : 'Kosong'}</span>
                            </CardHeader>
                            <CardContent className="!pt-0">
                                <div className="simpeg-muted flex items-center gap-2 text-xs">
                                    {document ? <><span>{formatBytes(document.ukuran)}</span><span aria-hidden="true">·</span><span>Diperbarui {document.diunggah_pada || '-'}</span></> : <span>PDF belum diunggah</span>}
                                </div>
                                <div className="mt-4 flex flex-wrap gap-2">
                                    <button type="button" onClick={() => selectType(type.value)} className="simpeg-primary inline-flex min-h-10 items-center gap-2 rounded-xl px-3 text-sm font-semibold"><RefreshCw className="h-4 w-4" />{document ? 'Ganti' : 'Unggah'}</button>
                                    {document && <>
                                        <button type="button" onClick={() => setPreview(document)} className="simpeg-utility inline-flex min-h-10 items-center gap-2 rounded-xl border px-3 text-sm font-semibold"><Eye className="h-4 w-4" />Lihat</button>
                                        <a href={document.download_url} className="simpeg-utility inline-flex min-h-10 items-center gap-2 rounded-xl border px-3 text-sm font-semibold"><Download className="h-4 w-4" />Export</a>
                                        <button type="button" onClick={() => removeDocument(document)} className="simpeg-utility inline-flex min-h-10 items-center gap-2 rounded-xl border border-rose-200 px-3 text-sm font-semibold text-rose-700"><Trash2 className="h-4 w-4" />Hapus</button>
                                    </>}
                                </div>
                            </CardContent>
                        </Card>;
                    })}
                </div>
            </>}

            {preview && <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/70 p-3 sm:p-6" role="presentation"><div className="flex h-[min(90vh,60rem)] w-full max-w-5xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl" role="dialog" aria-modal="true" aria-labelledby="data-diri-preview-title"><div className="flex items-center justify-between gap-3 border-b px-4 py-3"><div className="min-w-0"><h2 id="data-diri-preview-title" className="truncate text-base font-bold text-slate-900">Pratinjau {preview.label}</h2><p className="truncate text-xs text-slate-500">{preview.nama_asli}</p></div><div className="flex shrink-0 items-center gap-2"><a href={preview.download_url} className="inline-flex min-h-10 items-center gap-2 rounded-xl bg-slate-900 px-3 text-sm font-semibold text-white"><Download className="h-4 w-4" />Export</a><button type="button" onClick={() => setPreview(null)} className="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 text-slate-700" aria-label="Tutup pratinjau"><X className="h-5 w-5" /></button></div></div><iframe src={preview.preview_url} title={`Pratinjau ${preview.label}`} className="min-h-0 flex-1 bg-slate-100" /></div></div>}
        </LegacyPageShell>
    );
}

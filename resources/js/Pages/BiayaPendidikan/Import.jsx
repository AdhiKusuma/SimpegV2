import XlsxImport from '../Imports/XlsxImport';

export default function BiayaPendidikanImport() {
    return <XlsxImport
        title="Import kegiatan diklat"
        backUrl="/biaya-pendidikan"
        previewUrl="/biaya-pendidikan/import/preview"
        storeUrl="/biaya-pendidikan/import"
        templateUrl="/biaya-pendidikan/template"
        columns={[
            'Kode Kategori',
            'Kode Pihak',
            'Judul Pengembangan SDM',
            'Waktu Pelaksanaan',
            'Nama Pihak',
            'Jumlah SDM',
            'Total Biaya',
            'Tgl Mulai (YYYY-MM-DD)',
        ]}
        fileField="file_excel"
    />;
}

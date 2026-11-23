import XlsxImport from '../Imports/XlsxImport';

export default function DiklatImport() {
    return <XlsxImport
        title="Import kegiatan diklat"
        backUrl="/diklat"
        storeUrl="/diklat/import"
        templateUrl="/diklat/import/template"
        columns={[
            'ID Pegawai (ID/NIK)',
            'Nama Diklat',
            'Penyelenggara',
            'Tempat',
            'Biaya (Rp)',
            'Angkatan (opsional)',
            'Tahun (tahun)',
            'Tgl Awal Diklat (dd-mm-yyyy)',
        ]}
        fileField="file_xlsx"
        directUpload
    />;
}

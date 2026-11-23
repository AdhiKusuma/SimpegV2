import XlsxImport from '../Imports/XlsxImport';

export default function EmployeesImport() {
    return (
        <XlsxImport
            title="Import pegawai"
            backUrl="/pegawai"
            previewUrl="/pegawai/import/preview"
            storeUrl="/pegawai/import"
            templateUrl="/pegawai/import/template"
            fileField="file_xlsx"
        />
    );
}

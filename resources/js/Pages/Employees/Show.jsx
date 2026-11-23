import Profile from '../Profile/Index';

export default function EmployeeShow({ employee, family, history, canManage }) {
    return (
        <Profile
            employee={employee}
            family={family}
            history={history}
            familyRequests={[]}
            canRequestFamily={false}
            canRequestBiodata={false}
            canManageBiodata={false}
            canManageFamily={false}
            readOnly
            backUrl="/pegawai"
            pageTitle={`Data Pegawai · ${employee.nama}`}
            pageEyebrow="Data Pegawai"
            pageHeading="Ruang profil pegawai"
            pageDescription="Lihat biodata, data keluarga, dan riwayat kepegawaian pegawai secara lengkap."
            canManageEmployee={canManage}
        />
    );
}

<?php

use App\Http\Controllers\AccessMatrixController;
use App\Http\Controllers\ApplicationConfigController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AuthorizationController;
use App\Http\Controllers\BahasaController;
use App\Http\Controllers\BiayaPendidikanController;
use App\Http\Controllers\CutiController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DataDiriController;
use App\Http\Controllers\DataImportExportController;
use App\Http\Controllers\DiklatController;
use App\Http\Controllers\Dp3Controller;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\EselonController;
use App\Http\Controllers\FamilyController;
use App\Http\Controllers\FilesController;
use App\Http\Controllers\GolonganController;
use App\Http\Controllers\JabatanController;
use App\Http\Controllers\LatihanJabatanController;
use App\Http\Controllers\MutationController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OfficeController;
use App\Http\Controllers\PangkatController;
use App\Http\Controllers\PayrollController;
use App\Http\Controllers\PayrollMasterController;
use App\Http\Controllers\PayrollPublicationController;
use App\Http\Controllers\PelanggaranController;
use App\Http\Controllers\PendidikanController;
use App\Http\Controllers\PengangkatanController;
use App\Http\Controllers\PenghargaanController;
use App\Http\Controllers\PenugasanController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\QuotaJabatanController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SeminarController;
use App\Http\Controllers\SertifikasiController;
use App\Http\Controllers\UserManagementController;
use Illuminate\Support\Facades\Route;

Route::get('/', [AuthController::class, 'create'])->name('login');
Route::get('/login', [AuthController::class, 'create']);
Route::post('/login', [AuthController::class, 'store'])
    ->middleware('throttle:login')
    ->name('login.store');

Route::get('/config/aplikasi/logo/{filename}', [ApplicationConfigController::class, 'logo'])
    ->name('config.application.logo');

$familyTypePattern = 'children|spouses|parents|anak|orang-tua|suami-istri';

Route::middleware('legacy.auth')->group(function () use ($familyTypePattern): void {
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/tenure/{range}', [DashboardController::class, 'tenureEmployees'])->name('dashboard.tenure.employees');

    Route::get('/dashboard/cabang', [DashboardController::class, 'branch'])->name('dashboard.cabang');

    Route::get('/payroll', [PayrollController::class, 'index'])->name('payroll.index');
    Route::get('/payroll/edit', [PayrollMasterController::class, 'index'])->name('payroll.master.index');
    Route::get('/payroll/edit/create', [PayrollMasterController::class, 'create'])->name('payroll.master.create');
    Route::post('/payroll/edit', [PayrollMasterController::class, 'store'])->name('payroll.master.store');
    Route::post('/payroll/edit/transfer', [PayrollMasterController::class, 'transfer'])->name('payroll.master.transfer');
    Route::get('/payroll/master/export', [PayrollMasterController::class, 'export'])->name('payroll.master.export');
    Route::get('/payroll/edit/{employeeId}', [PayrollMasterController::class, 'edit'])->name('payroll.master.employee');
    Route::put('/payroll/edit/{employeeId}', [PayrollMasterController::class, 'update'])->name('payroll.master.update');
    Route::delete('/payroll/edit/{employeeId}', [PayrollMasterController::class, 'destroy'])->name('payroll.master.destroy');
    Route::get('/payroll/approvals', [PayrollMasterController::class, 'approvals'])->name('payroll.approvals.index');
    Route::get('/payroll/approvals/{id}', [PayrollMasterController::class, 'showApproval'])->whereNumber('id')->name('payroll.approvals.show');
    Route::post('/payroll/approvals/{id}/decision', [PayrollMasterController::class, 'decide'])->whereNumber('id')->name('payroll.approvals.decision');
    Route::get('/payroll/import', [PayrollController::class, 'importForm'])->name('payroll.import');
    Route::get('/payroll/template', [PayrollController::class, 'downloadTemplate'])->name('payroll.template');
    Route::get('/payroll/export', [PayrollController::class, 'export'])->name('payroll.export');
    Route::get('/payroll/import/preview', [PayrollController::class, 'previewImportGet'])->name('payroll.import.preview.form');
    Route::post('/payroll/import/preview', [PayrollController::class, 'previewImport'])->name('payroll.import.preview');
    Route::post('/payroll/import', [PayrollController::class, 'import'])->name('payroll.import.store');
    Route::delete('/payroll/history', [PayrollController::class, 'clearDevelopmentHistory'])->name('payroll.history.clear');
    Route::get('/payroll/publications', [PayrollPublicationController::class, 'index'])->name('payroll.publications.index');
    Route::post('/payroll/publications/{publication}/schedule', [PayrollPublicationController::class, 'schedule'])->whereNumber('publication')->name('payroll.publications.schedule');
    Route::post('/payroll/publications/{publication}/send', [PayrollPublicationController::class, 'send'])->whereNumber('publication')->name('payroll.publications.send');
    Route::post('/payroll/publications/{publication}/cancel', [PayrollPublicationController::class, 'cancel'])->whereNumber('publication')->name('payroll.publications.cancel');
    Route::delete('/payroll/publications/{publication}', [PayrollPublicationController::class, 'destroy'])->whereNumber('publication')->name('payroll.publications.destroy');
    Route::delete('/payroll/publications/import/{payrollImport}', [PayrollPublicationController::class, 'destroyImport'])->whereNumber('payrollImport')->name('payroll.publications.destroy-import');

    Route::get('/files', [FilesController::class, 'index'])->name('files.index');
    Route::get('/files/data-diri', [DataDiriController::class, 'index'])->name('files.data-diri.index');
    Route::post('/files/data-diri/{employeeId}', [DataDiriController::class, 'store'])
        ->where('employeeId', '[A-Za-z0-9-]+')
        ->name('files.data-diri.store');
    Route::get('/files/data-diri/{employeeId}/document/{document}/preview', [DataDiriController::class, 'preview'])
        ->where('employeeId', '[A-Za-z0-9-]+')
        ->whereNumber('document')
        ->name('files.data-diri.preview');
    Route::get('/files/data-diri/{employeeId}/document/{document}/download', [DataDiriController::class, 'download'])
        ->where('employeeId', '[A-Za-z0-9-]+')
        ->whereNumber('document')
        ->name('files.data-diri.download');
    Route::delete('/files/data-diri/{employeeId}/document/{document}', [DataDiriController::class, 'destroy'])
        ->where('employeeId', '[A-Za-z0-9-]+')
        ->whereNumber('document')
        ->name('files.data-diri.destroy');
    Route::get('/files/{payrollImport}/slip/{employeeId}', [FilesController::class, 'show'])
        ->whereNumber('payrollImport')
        ->where('employeeId', '[A-Za-z0-9-]+')
        ->name('files.show');
    Route::get('/files/{payrollImport}/slip/{employeeId}/download', [FilesController::class, 'download'])
        ->whereNumber('payrollImport')
        ->where('employeeId', '[A-Za-z0-9-]+')
        ->name('files.download');

    Route::get('/pegawai', [EmployeeController::class, 'index'])->name('employees.index');
    Route::get('/pegawai/export', [DataImportExportController::class, 'exportEmployees'])->name('employees.export');
    Route::get('/pegawai/export/detail', [DataImportExportController::class, 'exportEmployeesDetail'])->name('employees.export.detail');
    Route::get('/pegawai/export/pdf', [DataImportExportController::class, 'exportEmployeesPdf'])->name('employees.export.pdf');
    Route::get('/pegawai/filter-options', [EmployeeController::class, 'filterOptions'])->name('employees.filter-options');
    Route::get('/pegawai/create', [EmployeeController::class, 'create'])->name('employees.create');
    Route::post('/pegawai', [EmployeeController::class, 'store'])->name('employees.store');
    Route::get('/pegawai/import', [EmployeeController::class, 'importForm'])->name('employees.import');
    Route::get('/pegawai/import/template', [EmployeeController::class, 'downloadTemplate'])->name('employees.import.template');
    Route::post('/pegawai/import/preview', [EmployeeController::class, 'previewImport'])->name('employees.import.preview');
    Route::post('/pegawai/import', [EmployeeController::class, 'importXlsx'])->name('employees.import.store');
    Route::get('/pegawai/{id}/edit', [EmployeeController::class, 'edit'])->name('employees.edit');
    Route::put('/pegawai/{id}', [EmployeeController::class, 'update'])->name('employees.update');
    Route::get('/pegawai/foto/{filename}', [EmployeeController::class, 'photo'])->name('employees.photo');
    Route::get('/pegawai/{id}', [EmployeeController::class, 'show'])->name('employees.show');

    Route::get('/pendidikan', [PendidikanController::class, 'index'])->name('pendidikan.index');
    Route::get('/pendidikan/export', [DataImportExportController::class, 'exportEducation'])->name('pendidikan.export');
    Route::get('/pendidikan/import', [DataImportExportController::class, 'form'])->defaults('resource', 'education')->name('pendidikan.import');
    Route::get('/pendidikan/import/template', [DataImportExportController::class, 'template'])->defaults('resource', 'education')->name('pendidikan.import.template');
    Route::post('/pendidikan/import/preview', [DataImportExportController::class, 'preview'])->defaults('resource', 'education')->name('pendidikan.import.preview');
    Route::post('/pendidikan/import', [DataImportExportController::class, 'store'])->defaults('resource', 'education')->name('pendidikan.import.store');
    Route::get('/pendidikan/create', [PendidikanController::class, 'create'])->name('pendidikan.create');
    Route::post('/pendidikan', [PendidikanController::class, 'store'])->name('pendidikan.store');
    Route::get('/pendidikan/{id}/edit', [PendidikanController::class, 'edit'])->whereNumber('id')->name('pendidikan.edit');
    Route::put('/pendidikan/{id}', [PendidikanController::class, 'update'])->whereNumber('id')->name('pendidikan.update');
    Route::post('/pendidikan/{id}', [PendidikanController::class, 'update'])->whereNumber('id')->name('pendidikan.update.post');
    Route::delete('/pendidikan/{id}', [PendidikanController::class, 'destroy'])->whereNumber('id')->name('pendidikan.destroy');

    Route::get('/pangkat', [PangkatController::class, 'index'])->name('pangkat.index');
    Route::get('/pangkat/create', [PangkatController::class, 'create'])->name('pangkat.create');
    Route::post('/pangkat', [PangkatController::class, 'store'])->name('pangkat.store');
    Route::get('/pangkat/{id}/edit', [PangkatController::class, 'edit'])->name('pangkat.edit');
    Route::put('/pangkat/{id}', [PangkatController::class, 'update'])->name('pangkat.update');
    Route::post('/pangkat/{id}', [PangkatController::class, 'update'])->name('pangkat.update.post');
    Route::post('/pangkat/{id}/setup', [PangkatController::class, 'setup'])->name('pangkat.setup');
    Route::delete('/pangkat/{id}', [PangkatController::class, 'destroy'])->name('pangkat.destroy');

    Route::get('/golongan', [GolonganController::class, 'index'])->name('golongan.index');
    Route::post('/golongan', [GolonganController::class, 'store'])->name('golongan.store');
    Route::get('/golongan/{id}/edit', [GolonganController::class, 'edit'])->name('golongan.edit');
    Route::put('/golongan/{id}', [GolonganController::class, 'update'])->name('golongan.update');
    Route::post('/golongan/{id}', [GolonganController::class, 'update'])->name('golongan.update.post');
    Route::delete('/golongan/{id}', [GolonganController::class, 'destroy'])->name('golongan.destroy');

    Route::get('/eselon', [EselonController::class, 'index'])->name('eselon.index');
    Route::post('/eselon', [EselonController::class, 'store'])->name('eselon.store');
    Route::get('/eselon/{id}/edit', [EselonController::class, 'edit'])->name('eselon.edit');
    Route::put('/eselon/{id}', [EselonController::class, 'update'])->name('eselon.update');
    Route::post('/eselon/{id}', [EselonController::class, 'update'])->name('eselon.update.post');
    Route::delete('/eselon/{id}', [EselonController::class, 'destroy'])->name('eselon.destroy');

    Route::get('/pengangkatan', [PengangkatanController::class, 'index'])->name('pengangkatan.index');
    Route::get('/pengangkatan/create', [PengangkatanController::class, 'create'])->name('pengangkatan.create');
    Route::post('/pengangkatan', [PengangkatanController::class, 'store'])->name('pengangkatan.store');
    Route::get('/pengangkatan/{id}/edit', [PengangkatanController::class, 'edit'])->whereNumber('id')->name('pengangkatan.edit');
    Route::put('/pengangkatan/{id}', [PengangkatanController::class, 'update'])->whereNumber('id')->name('pengangkatan.update');
    Route::post('/pengangkatan/{id}', [PengangkatanController::class, 'update'])->whereNumber('id')->name('pengangkatan.update.post');
    Route::delete('/pengangkatan/{id}', [PengangkatanController::class, 'destroy'])->whereNumber('id')->name('pengangkatan.destroy');
    Route::get('/pengangkatan/{id}/document', [PengangkatanController::class, 'document'])->whereNumber('id')->name('pengangkatan.document');

    Route::get('/penghargaan', [PenghargaanController::class, 'index'])->name('penghargaan.index');
    Route::get('/penghargaan/create', [PenghargaanController::class, 'create'])->name('penghargaan.create');
    Route::post('/penghargaan', [PenghargaanController::class, 'store'])->name('penghargaan.store');
    Route::get('/penghargaan/{id}/edit', [PenghargaanController::class, 'edit'])->name('penghargaan.edit');
    Route::put('/penghargaan/{id}', [PenghargaanController::class, 'update'])->name('penghargaan.update');
    Route::post('/penghargaan/{id}', [PenghargaanController::class, 'update'])->name('penghargaan.update.post');
    Route::delete('/penghargaan/{id}', [PenghargaanController::class, 'destroy'])->name('penghargaan.destroy');

    Route::get('/penugasan', [PenugasanController::class, 'index'])->name('penugasan.index');
    Route::get('/penugasan/create', [PenugasanController::class, 'create'])->name('penugasan.create');
    Route::post('/penugasan', [PenugasanController::class, 'store'])->name('penugasan.store');
    Route::get('/penugasan/{id}/edit', [PenugasanController::class, 'edit'])->name('penugasan.edit');
    Route::put('/penugasan/{id}', [PenugasanController::class, 'update'])->name('penugasan.update');
    Route::post('/penugasan/{id}', [PenugasanController::class, 'update'])->name('penugasan.update.post');
    Route::delete('/penugasan/{id}', [PenugasanController::class, 'destroy'])->name('penugasan.destroy');

    Route::get('/seminar', [SeminarController::class, 'index'])->name('seminar.index');
    Route::get('/seminar/create', [SeminarController::class, 'create'])->name('seminar.create');
    Route::post('/seminar', [SeminarController::class, 'store'])->name('seminar.store');
    Route::get('/seminar/{id}/edit', [SeminarController::class, 'edit'])->name('seminar.edit');
    Route::put('/seminar/{id}', [SeminarController::class, 'update'])->name('seminar.update');
    Route::post('/seminar/{id}', [SeminarController::class, 'update'])->name('seminar.update.post');
    Route::delete('/seminar/{id}', [SeminarController::class, 'destroy'])->name('seminar.destroy');

    Route::get('/cuti', [CutiController::class, 'index'])->name('cuti.index');
    Route::get('/cuti/create', [CutiController::class, 'create'])->name('cuti.create');
    Route::post('/cuti', [CutiController::class, 'store'])->name('cuti.store');
    Route::get('/cuti/{id}/edit', [CutiController::class, 'edit'])->name('cuti.edit');
    Route::put('/cuti/{id}', [CutiController::class, 'update'])->name('cuti.update');
    Route::post('/cuti/{id}', [CutiController::class, 'update'])->name('cuti.update.post');
    Route::delete('/cuti/{id}', [CutiController::class, 'destroy'])->name('cuti.destroy');

    Route::get('/bahasa', [BahasaController::class, 'index'])->name('bahasa.index');
    Route::get('/bahasa/create', [BahasaController::class, 'create'])->name('bahasa.create');
    Route::post('/bahasa', [BahasaController::class, 'store'])->name('bahasa.store');
    Route::get('/bahasa/{id}/edit', [BahasaController::class, 'edit'])->name('bahasa.edit');
    Route::put('/bahasa/{id}', [BahasaController::class, 'update'])->name('bahasa.update');
    Route::post('/bahasa/{id}', [BahasaController::class, 'update'])->name('bahasa.update.post');
    Route::delete('/bahasa/{id}', [BahasaController::class, 'destroy'])->name('bahasa.destroy');

    Route::get('/dp3', [Dp3Controller::class, 'index'])->name('dp3.index');
    Route::get('/dp3/create', [Dp3Controller::class, 'create'])->name('dp3.create');
    Route::post('/dp3', [Dp3Controller::class, 'store'])->name('dp3.store');
    Route::get('/dp3/{id}/edit', [Dp3Controller::class, 'edit'])->name('dp3.edit');
    Route::put('/dp3/{id}', [Dp3Controller::class, 'update'])->name('dp3.update');
    Route::post('/dp3/{id}', [Dp3Controller::class, 'update'])->name('dp3.update.post');
    Route::delete('/dp3/{id}', [Dp3Controller::class, 'destroy'])->name('dp3.destroy');
    Route::get('/dp3/{id}', [Dp3Controller::class, 'show'])->name('dp3.show');

    Route::get('/latihan-jabatan', [LatihanJabatanController::class, 'index'])->name('latihan-jabatan.index');
    Route::get('/latihan-jabatan/create', [LatihanJabatanController::class, 'create'])->name('latihan-jabatan.create');
    Route::post('/latihan-jabatan', [LatihanJabatanController::class, 'store'])->name('latihan-jabatan.store');
    Route::get('/latihan-jabatan/{id}/edit', [LatihanJabatanController::class, 'edit'])->name('latihan-jabatan.edit');
    Route::put('/latihan-jabatan/{id}', [LatihanJabatanController::class, 'update'])->name('latihan-jabatan.update');
    Route::post('/latihan-jabatan/{id}', [LatihanJabatanController::class, 'update'])->name('latihan-jabatan.update.post');
    Route::delete('/latihan-jabatan/{id}', [LatihanJabatanController::class, 'destroy'])->name('latihan-jabatan.destroy');

    Route::get('/sertifikasi', [SertifikasiController::class, 'index'])->name('sertifikasi.index');
    Route::get('/sertifikasi/export', [DataImportExportController::class, 'exportCertification'])->name('sertifikasi.export');
    Route::get('/sertifikasi/import', [DataImportExportController::class, 'form'])->defaults('resource', 'certification')->name('sertifikasi.import');
    Route::get('/sertifikasi/import/template', [DataImportExportController::class, 'template'])->defaults('resource', 'certification')->name('sertifikasi.import.template');
    Route::post('/sertifikasi/import/preview', [DataImportExportController::class, 'preview'])->defaults('resource', 'certification')->name('sertifikasi.import.preview');
    Route::post('/sertifikasi/import', [DataImportExportController::class, 'store'])->defaults('resource', 'certification')->name('sertifikasi.import.store');
    Route::get('/sertifikasi/create', [SertifikasiController::class, 'create'])->name('sertifikasi.create');
    Route::post('/sertifikasi', [SertifikasiController::class, 'store'])->name('sertifikasi.store');
    Route::get('/sertifikasi/{id}/edit', [SertifikasiController::class, 'edit'])->whereNumber('id')->name('sertifikasi.edit');
    Route::put('/sertifikasi/{id}', [SertifikasiController::class, 'update'])->whereNumber('id')->name('sertifikasi.update');
    Route::post('/sertifikasi/{id}', [SertifikasiController::class, 'update'])->whereNumber('id')->name('sertifikasi.update.post');
    Route::delete('/sertifikasi/{id}', [SertifikasiController::class, 'destroy'])->whereNumber('id')->name('sertifikasi.destroy');

    Route::get('/biaya-pendidikan', [BiayaPendidikanController::class, 'index'])->name('biaya-pendidikan.index');
    Route::get('/biaya-pendidikan/export', [DataImportExportController::class, 'exportBiaya'])->name('biaya-pendidikan.export');
    Route::get('/biaya-pendidikan/template', [BiayaPendidikanController::class, 'downloadTemplate'])->name('biaya-pendidikan.template');
    Route::get('/biaya-pendidikan/import', [BiayaPendidikanController::class, 'importForm'])->name('biaya-pendidikan.import');
    Route::post('/biaya-pendidikan/import/preview', [BiayaPendidikanController::class, 'previewImport'])->name('biaya-pendidikan.import.preview');
    Route::post('/biaya-pendidikan/import', [BiayaPendidikanController::class, 'importRows'])->name('biaya-pendidikan.import.store');
    Route::get('/biaya-pendidikan/create', [BiayaPendidikanController::class, 'create'])->name('biaya-pendidikan.create');
    Route::post('/biaya-pendidikan', [BiayaPendidikanController::class, 'store'])->name('biaya-pendidikan.store');
    Route::get('/biaya-pendidikan/{id}/edit', [BiayaPendidikanController::class, 'edit'])->whereNumber('id')->name('biaya-pendidikan.edit');
    Route::put('/biaya-pendidikan/{id}', [BiayaPendidikanController::class, 'update'])->whereNumber('id')->name('biaya-pendidikan.update');
    Route::delete('/biaya-pendidikan/{id}', [BiayaPendidikanController::class, 'destroy'])->whereNumber('id')->name('biaya-pendidikan.destroy');
    Route::get('/biaya-pendidikan/{id}/participants', [BiayaPendidikanController::class, 'participants'])->whereNumber('id')->name('biaya-pendidikan.participants');

    Route::get('/users', [UserManagementController::class, 'index'])->name('users.index');
    Route::get('/users/create', [UserManagementController::class, 'create'])->name('users.create');
    Route::post('/users', [UserManagementController::class, 'store'])->name('users.store');
    Route::get('/users/access', [AccessMatrixController::class, 'index'])->name('users.access');
    Route::put('/users/access', [AccessMatrixController::class, 'update'])->name('users.access.update');
    Route::get('/users/{id}/edit', [UserManagementController::class, 'edit'])->name('users.edit');
    Route::put('/users/{id}', [UserManagementController::class, 'update'])->name('users.update');
    Route::delete('/users/{id}', [UserManagementController::class, 'destroy'])->name('users.destroy');

    Route::get('/config/aplikasi', [ApplicationConfigController::class, 'edit'])->name('config.application.edit');
    Route::post('/config/aplikasi', [ApplicationConfigController::class, 'update'])->name('config.application.update');
    Route::get('/config/kuota-jabatan', [QuotaJabatanController::class, 'index'])->name('config.quota.index');
    Route::post('/config/kuota-jabatan', [QuotaJabatanController::class, 'store'])->name('config.quota.store');
    Route::put('/config/kuota-jabatan/{id}', [QuotaJabatanController::class, 'update'])->whereNumber('id')->name('config.quota.update');
    Route::delete('/config/kuota-jabatan/{id}', [QuotaJabatanController::class, 'destroy'])->whereNumber('id')->name('config.quota.destroy');

    Route::get('/jabatan', [JabatanController::class, 'index'])->name('jabatan.index');
    Route::get('/jabatan/export', [DataImportExportController::class, 'exportPositions'])->name('jabatan.export');
    Route::get('/jabatan/create', [JabatanController::class, 'create'])->name('jabatan.create');
    Route::post('/jabatan', [JabatanController::class, 'store'])->name('jabatan.store');
    Route::get('/jabatan/import', [JabatanController::class, 'importForm'])->name('jabatan.import');
    Route::get('/jabatan/import/template', [JabatanController::class, 'downloadImportTemplate'])->name('jabatan.import.template');
    Route::post('/jabatan/import/preview', [JabatanController::class, 'previewImport'])->name('jabatan.import.preview');
    Route::post('/jabatan/import', [JabatanController::class, 'importXlsx'])->name('jabatan.import.store');
    Route::get('/jabatan/{id}/edit', [JabatanController::class, 'edit'])->whereNumber('id')->name('jabatan.edit');
    Route::put('/jabatan/{id}', [JabatanController::class, 'update'])->whereNumber('id')->name('jabatan.update');
    Route::post('/jabatan/{id}', [JabatanController::class, 'update'])->whereNumber('id')->name('jabatan.update.post');
    Route::post('/jabatan/{id}/setup', [JabatanController::class, 'setup'])->whereNumber('id')->name('jabatan.setup');

    Route::get('/kantor', [OfficeController::class, 'index'])->name('offices.index');
    Route::get('/kantor/{code}', [OfficeController::class, 'show'])->name('offices.show');

    Route::get('/laporan/nominatif/export', [ReportController::class, 'exportNominative'])->name('reports.nominative.export');
    Route::get('/laporan/nominatif', [ReportController::class, 'nominative'])->name('reports.nominative');
    Route::get('/laporan/formasi/export', [ReportController::class, 'exportFormation'])->name('reports.formation.export');
    Route::get('/laporan/formasi', [ReportController::class, 'formation'])->name('reports.formation');
    Route::get('/laporan/rekap-diklat/export', [ReportController::class, 'exportTrainingCosts'])->name('reports.training-costs.export');
    Route::get('/laporan/rekap-diklat/detail', [ReportController::class, 'trainingCostDetail'])->name('reports.training-costs.detail');
    Route::get('/laporan/rekap-diklat', [ReportController::class, 'trainingCosts'])->name('reports.training-costs');
    Route::get('/laporan/rekap-biaya-pendidikan/export', [ReportController::class, 'exportEducationCosts'])->name('reports.education-costs.export');
    Route::get('/laporan/rekap-biaya-pendidikan', [ReportController::class, 'educationCosts'])->name('reports.education-costs');
    Route::get('/laporan/keadaan/export', [ReportController::class, 'exportCondition'])->name('reports.condition.export');
    Route::get('/laporan/keadaan', [ReportController::class, 'condition'])->name('reports.condition');

    Route::get('/keluarga/{type}', [FamilyController::class, 'index'])->where('type', $familyTypePattern)->name('family.index');
    Route::get('/keluarga/{type}/export', [DataImportExportController::class, 'exportFamily'])->where('type', $familyTypePattern)->name('family.export');
    Route::get('/keluarga/{type}/import', [DataImportExportController::class, 'form'])->where('type', $familyTypePattern)->defaults('resource', 'family')->name('family.import');
    Route::get('/keluarga/{type}/import/template', [DataImportExportController::class, 'template'])->where('type', $familyTypePattern)->defaults('resource', 'family')->name('family.import.template');
    Route::post('/keluarga/{type}/import/preview', [DataImportExportController::class, 'preview'])->where('type', $familyTypePattern)->defaults('resource', 'family')->name('family.import.preview');
    Route::post('/keluarga/{type}/import', [DataImportExportController::class, 'store'])->where('type', $familyTypePattern)->defaults('resource', 'family')->name('family.import.store');
    Route::get('/keluarga/{type}/create', [FamilyController::class, 'create'])->where('type', $familyTypePattern)->name('family.create');
    Route::post('/keluarga/{type}', [FamilyController::class, 'store'])->where('type', $familyTypePattern)->name('family.store');
    Route::get('/keluarga/{type}/{id}/edit', [FamilyController::class, 'edit'])->where('type', $familyTypePattern)->name('family.edit');
    Route::put('/keluarga/{type}/{id}', [FamilyController::class, 'update'])->where('type', $familyTypePattern)->name('family.update');
    Route::delete('/keluarga/{type}/{id}', [FamilyController::class, 'destroy'])->where('type', $familyTypePattern)->name('family.destroy');

    Route::get('/mutasi/export', [MutationController::class, 'export'])->name('mutasi.export');
    Route::get('/mutasi', [MutationController::class, 'index'])->name('mutasi.index');
    Route::get('/mutasi/create', [MutationController::class, 'create'])->name('mutasi.create');
    Route::post('/mutasi', [MutationController::class, 'store'])->name('mutasi.store');
    Route::get('/mutasi/{id}/edit', [MutationController::class, 'edit'])->whereNumber('id')->name('mutasi.edit');
    Route::put('/mutasi/{id}', [MutationController::class, 'update'])->whereNumber('id')->name('mutasi.update');
    Route::post('/mutasi/{id}', [MutationController::class, 'update'])->whereNumber('id')->name('mutasi.update.post');
    Route::delete('/mutasi/{id}', [MutationController::class, 'destroy'])->whereNumber('id')->name('mutasi.destroy');
    Route::get('/mutasi/{id}/document', [MutationController::class, 'document'])->whereNumber('id')->name('mutasi.document');
    Route::get('/pelanggaran/export', [PelanggaranController::class, 'export'])->name('pelanggaran.export');
    Route::get('/pelanggaran/import', [PelanggaranController::class, 'importForm'])->name('pelanggaran.import');
    Route::get('/pelanggaran/import/template', [PelanggaranController::class, 'importTemplate'])->name('pelanggaran.import.template');
    Route::post('/pelanggaran/import/preview', [PelanggaranController::class, 'previewImport'])->name('pelanggaran.import.preview');
    Route::post('/pelanggaran/import', [PelanggaranController::class, 'storeImport'])->name('pelanggaran.import.store');
    Route::get('/pelanggaran', [PelanggaranController::class, 'index'])->name('pelanggaran.index');
    Route::get('/pelanggaran/create', [PelanggaranController::class, 'create'])->name('pelanggaran.create');
    Route::post('/pelanggaran', [PelanggaranController::class, 'store'])->name('pelanggaran.store');
    Route::get('/pelanggaran/{id}/edit', [PelanggaranController::class, 'edit'])->whereNumber('id')->name('pelanggaran.edit');
    Route::put('/pelanggaran/{id}', [PelanggaranController::class, 'update'])->whereNumber('id')->name('pelanggaran.update');
    Route::post('/pelanggaran/{id}', [PelanggaranController::class, 'update'])->whereNumber('id')->name('pelanggaran.update.post');
    Route::delete('/pelanggaran/{id}', [PelanggaranController::class, 'destroy'])->whereNumber('id')->name('pelanggaran.destroy');
    Route::get('/pelanggaran/{id}/document', [PelanggaranController::class, 'document'])->whereNumber('id')->name('pelanggaran.document');
    Route::get('/diklat', [DiklatController::class, 'index'])->name('diklat.index');
    Route::get('/diklat/export', [DataImportExportController::class, 'exportDiklat'])->name('diklat.export');
    Route::get('/diklat/import', [DiklatController::class, 'importForm'])->name('diklat.import');
    Route::get('/diklat/import/template', [DiklatController::class, 'downloadTemplate'])->name('diklat.import.template');
    Route::post('/diklat/import', [DiklatController::class, 'importXlsx'])->name('diklat.import.store');
    Route::get('/diklat/create', [DiklatController::class, 'create'])->name('diklat.create');
    Route::post('/diklat', [DiklatController::class, 'store'])->name('diklat.store');
    Route::get('/diklat/{id}/edit', [DiklatController::class, 'edit'])->whereNumber('id')->name('diklat.edit');
    Route::put('/diklat/{id}', [DiklatController::class, 'update'])->whereNumber('id')->name('diklat.update');
    Route::post('/diklat/{id}', [DiklatController::class, 'update'])->whereNumber('id')->name('diklat.update.post');
    Route::delete('/diklat/{id}', [DiklatController::class, 'destroy'])->whereNumber('id')->name('diklat.destroy');

    Route::get('/profile', [ProfileController::class, 'show'])->name('profile');
    Route::get('/profile/data-diri', [DataDiriController::class, 'mine'])->name('profile.data-diri');
    Route::get('/profile/slip-gaji', [FilesController::class, 'mine'])->name('profile.payslip');
    Route::get('/profile/slip-gaji/{payrollImport}/slip/{employeeId}', [FilesController::class, 'mineShow'])
        ->whereNumber('payrollImport')
        ->where('employeeId', '[A-Za-z0-9-]+')
        ->name('profile.payslip.show');

    Route::get('/notifikasi', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifikasi/{notification}/open', [NotificationController::class, 'open'])
        ->whereNumber('notification')
        ->name('notifications.open');
    Route::post('/notifikasi/read', [NotificationController::class, 'markAllRead'])->name('notifications.read');
    Route::delete('/notifikasi/read', [NotificationController::class, 'destroyRead'])->name('notifications.read.destroy');

    Route::get('/otorisasi', [AuthorizationController::class, 'index'])->name('authorizations.index');
    Route::get('/otorisasi/{id}/detail', [AuthorizationController::class, 'show'])->whereNumber('id')->name('authorizations.show');
    Route::post('/otorisasi/{id}/process', [AuthorizationController::class, 'process'])->whereNumber('id')->name('authorizations.process');
    Route::get('/pengajuan', [AuthorizationController::class, 'history'])->name('authorizations.history');
});

<?php

use App\Http\Controllers\ClaimController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EclaimController;
use App\Http\Controllers\IcdController;
use App\Http\Controllers\IcdReferenceController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VerificationController;
use App\Http\Middleware\EnsureHospitalContext;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check() ? redirect()->route('dashboard') : redirect()->route('login');
});

Route::middleware(['auth', 'verified', EnsureHospitalContext::class])->group(function () {
    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/summary/download', [DashboardController::class, 'downloadSummary'])->name('dashboard.summary.download');

    // Claims Management
    Route::get('/klaim', [ClaimController::class, 'index'])->name('klaim.index');
    Route::post('/klaim', [ClaimController::class, 'store'])->name('klaim.store');
    Route::put('/klaim/{claim}', [ClaimController::class, 'update'])->name('klaim.update');
    Route::patch('/klaim/{claim}/status', [ClaimController::class, 'updateStatus'])->name('klaim.status');
    Route::delete('/klaim/{claim}', [ClaimController::class, 'destroy'])->name('klaim.destroy');
    Route::get('/klaim-export', [ClaimController::class, 'export'])->name('klaim.export');

    // 6-step Verification Workflow
    Route::get('/verifikasi/cari', [VerificationController::class, 'selectPatient'])->name('verifikasi.select');
    Route::get('/verifikasi/{step?}', [VerificationController::class, 'show'])->name('verifikasi.shortcut');
    Route::get('/klaim/{claim?}/verifikasi/{step?}', [VerificationController::class, 'show'])->name('verifikasi.show');
    Route::post('/klaim/{claim}/verifikasi/check-sep', [VerificationController::class, 'checkSep'])->name('verifikasi.check-sep');
    Route::post('/klaim/{claim}/verifikasi/upload-resume', [VerificationController::class, 'uploadResume'])->name('verifikasi.upload-resume');
    Route::get('/klaim/{claim}/verifikasi/dokumen/{document}', [VerificationController::class, 'downloadDocument'])->name('verifikasi.document');
    Route::post('/klaim/{claim}/verifikasi/validate-icd10', [VerificationController::class, 'validateIcd10'])->name('verifikasi.validate-icd10');
    Route::post('/klaim/{claim}/verifikasi/validate-icd9', [VerificationController::class, 'validateIcd9'])->name('verifikasi.validate-icd9');
    Route::post('/klaim/{claim}/verifikasi/evaluate-risks', [VerificationController::class, 'evaluateRisks'])->name('verifikasi.evaluate-risks');
    Route::post('/klaim/{claim}/verifikasi/risks/{risk}/resolve', [VerificationController::class, 'resolveRisk'])->name('verifikasi.resolve-risk');
    Route::post('/klaim/{claim}/verifikasi/mark-ready', [VerificationController::class, 'markReady'])->name('verifikasi.mark-ready');
    Route::post('/klaim/{claim}/verifikasi/return-revision', [VerificationController::class, 'returnForRevision'])->name('verifikasi.return-revision');

    // ICD reference search
    Route::get('/api/icd10/search', [IcdController::class, 'search10'])->name('api.icd10.search');
    Route::get('/api/icd9cm/search', [IcdController::class, 'search9'])->name('api.icd9cm.search');

    // Shortcuts lama diarahkan ke pencarian pasien dengan tahap yang sama
    Route::get('/v-claim', fn () => redirect()->route('verifikasi.select', ['step' => 'v-claim']))->name('nav.vclaim');
    Route::get('/pra-klaim', fn () => redirect()->route('verifikasi.select', ['step' => 'pra-klaim']))->name('nav.praklaim');
    Route::get('/icd-10', fn () => redirect()->route('verifikasi.select', ['step' => 'icd-10']))->name('nav.icd10');
    Route::get('/icd-9', fn () => redirect()->route('verifikasi.select', ['step' => 'icd-9']))->name('nav.icd9');
    Route::get('/risiko-klaim', fn () => redirect()->route('verifikasi.select', ['step' => 'risiko-klaim']))->name('nav.risiko');
    Route::get('/tinjauan-akhir', fn () => redirect()->route('verifikasi.select', ['step' => 'tinjauan-akhir']))->name('nav.tinjauan');

    // E-Claim Sync
    Route::get('/eclaim', [EclaimController::class, 'index'])->name('eclaim.index');
    Route::post('/eclaim/sync-batch', [EclaimController::class, 'syncBatch'])->name('eclaim.sync-batch');
    Route::post('/eclaim/sync/{claim}', [EclaimController::class, 'syncSingle'])->name('eclaim.sync-single');
    Route::post('/eclaim/sync/{sync}/retry', [EclaimController::class, 'retryBatch'])->name('eclaim.retry');

    // Pelaporan / Reports
    Route::get('/laporan', [ReportController::class, 'index'])->name('laporan.index');
    Route::get('/laporan/export/excel', [ReportController::class, 'exportExcel'])->name('laporan.export.excel');
    Route::get('/laporan/export/pdf', [ReportController::class, 'exportPdf'])->name('laporan.export.pdf');

    // Notifications
    Route::get('/notifikasi', [NotificationController::class, 'index'])->name('notifikasi.index');
    Route::post('/notifikasi/mark-read', [NotificationController::class, 'markAllRead'])->name('notifikasi.mark-read');
    Route::post('/notifikasi/{notification}/read', [NotificationController::class, 'markAsRead'])->name('notifikasi.read');

    // Manajemen Pengguna (admin)
    Route::get('/admin/users', [UserController::class, 'index'])->name('admin.users.index');
    Route::get('/admin/users/create', [UserController::class, 'create'])->name('admin.users.create');
    Route::post('/admin/users', [UserController::class, 'store'])->name('admin.users.store');
    Route::get('/admin/users/{user}/edit', [UserController::class, 'edit'])->name('admin.users.edit');
    Route::put('/admin/users/{user}', [UserController::class, 'update'])->name('admin.users.update');
    Route::delete('/admin/users/{user}', [UserController::class, 'destroy'])->name('admin.users.destroy');
    Route::post('/admin/users/{user}/impersonate', [UserController::class, 'impersonate'])->name('admin.users.impersonate');
    Route::post('/impersonation/leave', [UserController::class, 'leaveImpersonation'])->name('impersonation.leave');

    // Manajemen Referensi ICD (admin)
    Route::get('/admin/icd/{type}', [IcdReferenceController::class, 'index'])->name('admin.icd.index');
    Route::get('/admin/icd/{type}/tambah', [IcdReferenceController::class, 'create'])->name('admin.icd.create');
    Route::post('/admin/icd/{type}', [IcdReferenceController::class, 'store'])->name('admin.icd.store');
    Route::patch('/admin/icd/{type}/{icdCode}', [IcdReferenceController::class, 'toggle'])->name('admin.icd.toggle');

});

// Profile does not require a hospital assignment.
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';

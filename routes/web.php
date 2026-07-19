<?php

use App\Http\Controllers\AnimalController;
use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\AuditController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DirectoryController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\EndOfDayController;
use App\Http\Controllers\LeaveController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\MemberReviewController;
use App\Http\Controllers\MonitoringController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PayrollController;
use App\Http\Controllers\PolicyController;
use App\Http\Controllers\RegisterController;
use App\Http\Controllers\RiskAssessmentController;
use App\Http\Controllers\SupervisionController;
use App\Http\Controllers\TimeclockController;
use App\Http\Controllers\TodayController;
use App\Http\Controllers\TransportController;
use App\Http\Controllers\VetRecordController;
use App\Http\Controllers\WelfareCheckController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'show'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
});

Route::middleware(['auth', 'can:access_hub'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/', DashboardController::class)->name('dashboard');
    Route::get('/today', TodayController::class)->name('today');

    Route::get('/members', [MemberController::class, 'index'])
        ->middleware('can:view_members')->name('members.index');
    Route::get('/members/{member}', [MemberController::class, 'show'])
        ->middleware('can:view_members')->name('members.show');
    Route::post('/members', [MemberController::class, 'store'])
        ->middleware('can:create_members')->name('members.store');
    Route::put('/members/{member}', [MemberController::class, 'update'])
        ->middleware('can:edit_members')->name('members.update');

    Route::get('/animals', [AnimalController::class, 'index'])
        ->middleware('can:view_animals')->name('animals.index');
    Route::get('/animals/{animal}', [AnimalController::class, 'show'])
        ->middleware('can:view_animals')->name('animals.show');
    Route::post('/animals', [AnimalController::class, 'store'])
        ->middleware('can:edit_animals')->name('animals.store');
    Route::put('/animals/{animal}', [AnimalController::class, 'update'])
        ->middleware('can:edit_animals')->name('animals.update');

    Route::post('/animals/{animal}/welfare-checks', [WelfareCheckController::class, 'store'])
        ->middleware('can:log_welfare')->name('welfare.store');
    Route::post('/welfare-checks/species', [WelfareCheckController::class, 'storeSpecies'])
        ->middleware('can:log_welfare')->name('welfare.species');
    Route::post('/animals/{animal}/monitoring', [MonitoringController::class, 'store'])
        ->middleware('can:log_welfare')->name('monitoring.store');

    Route::get('/register', [RegisterController::class, 'index'])
        ->middleware('can:log_sessions')->name('register');
    Route::post('/register/{member}/check-in', [RegisterController::class, 'checkIn'])
        ->middleware('can:log_sessions')->name('register.check-in');
    Route::put('/register/{member}', [RegisterController::class, 'update'])
        ->middleware('can:log_sessions')->name('register.update');

    Route::get('/end-of-day', [EndOfDayController::class, 'index'])
        ->middleware('can:log_sessions')->name('end-of-day');
    Route::post('/end-of-day/{member}', [EndOfDayController::class, 'store'])
        ->middleware('can:log_sessions')->name('end-of-day.store');

    Route::post('/animals/{animal}/vet-records', [VetRecordController::class, 'store'])
        ->middleware('can:log_welfare')->name('vet-records.store');

    // ── Phase 2 ──────────────────────────────────────────────────────────

    Route::middleware('can:log_sessions')->group(function () {
        Route::get('/transport', [TransportController::class, 'index'])->name('transport');
        Route::post('/transport/{member}/complete', [TransportController::class, 'complete'])->name('transport.complete');
        Route::post('/transport/{member}/undo', [TransportController::class, 'undo'])->name('transport.undo');
        Route::post('/transport/{member}/pay', [TransportController::class, 'pay'])->name('transport.pay');
    });

    Route::get('/leave', [LeaveController::class, 'index'])->name('leave');
    Route::post('/leave', [LeaveController::class, 'store'])
        ->middleware('can:request_leave')->name('leave.store');
    Route::put('/leave/{leave}/review', [LeaveController::class, 'review'])
        ->middleware('can:approve_leave')->name('leave.review');

    Route::middleware('can:own_timeclock')->group(function () {
        Route::get('/timeclock', [TimeclockController::class, 'index'])->name('timeclock');
        Route::post('/timeclock/in', [TimeclockController::class, 'clockIn'])->name('timeclock.in');
        Route::post('/timeclock/out', [TimeclockController::class, 'clockOut'])->name('timeclock.out');
        Route::put('/timeclock/{entry}', [TimeclockController::class, 'update'])->name('timeclock.update');
    });

    Route::get('/announcements', [AnnouncementController::class, 'index'])->name('announcements');
    Route::post('/announcements', [AnnouncementController::class, 'store'])
        ->middleware('can:post_announcements')->name('announcements.store');
    Route::post('/announcements/{announcement}/read', [AnnouncementController::class, 'markRead'])
        ->name('announcements.read');

    Route::get('/notifications', [NotificationController::class, 'edit'])->name('notifications');
    Route::put('/notifications', [NotificationController::class, 'update'])->name('notifications.update');
    Route::post('/push-subscriptions', [NotificationController::class, 'subscribe'])->name('push.subscribe');
    Route::post('/notifications/test', [NotificationController::class, 'test'])->name('notifications.test');

    Route::get('/more', fn () => Inertia::render('More'))->name('more');

    // ── Phase 3 ──────────────────────────────────────────────────────────

    Route::middleware('can:manage_payroll')->group(function () {
        Route::get('/payroll', [PayrollController::class, 'index'])->name('payroll.index');
        Route::post('/payroll/periods', [PayrollController::class, 'storePeriod'])->name('payroll.periods.store');
        Route::get('/payroll/periods/{period}', [PayrollController::class, 'show'])->name('payroll.show');
        Route::get('/payroll/periods/{period}/print', [PayrollController::class, 'print'])->name('payroll.print');
        Route::put('/payroll/periods/{period}', [PayrollController::class, 'updatePeriod'])->name('payroll.periods.update');
        Route::put('/payroll/periods/{period}/entries', [PayrollController::class, 'saveEntries'])->name('payroll.entries.save');
        Route::delete('/payroll/periods/{period}', [PayrollController::class, 'destroyPeriod'])->name('payroll.periods.destroy');
        Route::post('/payroll/roster', [PayrollController::class, 'storeRosterMember'])->name('payroll.roster.store');
        Route::put('/payroll/roster/{rosterMember}', [PayrollController::class, 'updateRosterMember'])->name('payroll.roster.update');
    });

    Route::get('/supervisions', [SupervisionController::class, 'index'])
        ->middleware('can:manage_supervisions')->name('supervisions');
    Route::post('/supervisions', [SupervisionController::class, 'store'])
        ->middleware('can:manage_supervisions')->name('supervisions.store');

    Route::get('/directory', [DirectoryController::class, 'index'])->name('directory');

    Route::get('/policies', [PolicyController::class, 'index'])->name('policies.index');
    Route::get('/policies/{policy}', [PolicyController::class, 'show'])->name('policies.show');
    Route::post('/policies', [PolicyController::class, 'store'])
        ->middleware('can:manage_policies')->name('policies.store');
    Route::put('/policies/{policy}', [PolicyController::class, 'update'])
        ->middleware('can:manage_policies')->name('policies.update');

    Route::get('/documents', [DocumentController::class, 'index'])->name('documents');
    Route::post('/documents', [DocumentController::class, 'store'])
        ->middleware('can:upload_documents')->name('documents.store');
    Route::get('/documents/{document}/download', [DocumentController::class, 'download'])->name('documents.download');
    Route::post('/documents/{document}/read', [DocumentController::class, 'markRead'])->name('documents.read');

    Route::get('/risk-assessments', [RiskAssessmentController::class, 'index'])->name('risks');
    Route::post('/risk-assessments', [RiskAssessmentController::class, 'store'])
        ->middleware('can:manage_compliance')->name('risks.store');
    Route::put('/risk-assessments/{riskAssessment}', [RiskAssessmentController::class, 'update'])
        ->middleware('can:manage_compliance')->name('risks.update');
    Route::post('/risk-assessments/{riskAssessment}/sign-off', [RiskAssessmentController::class, 'signOff'])
        ->middleware('can:manage_compliance')->name('risks.sign-off');

    Route::get('/reviews', [MemberReviewController::class, 'index'])->name('reviews');
    Route::post('/reviews', [MemberReviewController::class, 'store'])
        ->middleware('can:edit_members')->name('reviews.store');

    Route::get('/audit', [AuditController::class, 'index'])
        ->middleware('can:view_reports')->name('audit');
});

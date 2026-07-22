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
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
});

// Microsoft SSO (Azure OAuth2). Callback path matches the legacy Hub.
Route::get('/auth/microsoft', [App\Http\Controllers\MicrosoftAuthController::class, 'redirect'])->name('microsoft.redirect');
Route::get('/ah-ms-callback', [App\Http\Controllers\MicrosoftAuthController::class, 'callback'])->name('microsoft.callback');

// Public referral form — no auth.
Route::get('/refer', [App\Http\Controllers\ReferralController::class, 'create'])->name('refer');
Route::post('/refer', [App\Http\Controllers\ReferralController::class, 'store'])->middleware('throttle:10,60');

Route::middleware(['auth', 'can:access_hub'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/', DashboardController::class)->name('dashboard');
    Route::get('/today', TodayController::class)->name('today');

    Route::get('/members', [MemberController::class, 'index'])
        ->middleware('can:view_members')->name('members.index');
    Route::get('/members/{member}', [MemberController::class, 'show'])
        ->middleware('can:view_members')->name('members.show');
    Route::get('/members/{member}/history', [App\Http\Controllers\MemberHistoryController::class, 'show'])
        ->middleware('can:view_members')->name('members.history');
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
    Route::get('/notifications/recent', [NotificationController::class, 'recent'])->name('notifications.recent');
    Route::put('/notifications', [NotificationController::class, 'update'])->name('notifications.update');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
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
    Route::get('/calendar', [App\Http\Controllers\CalendarController::class, 'index'])->name('calendar');
    Route::get('/search', [App\Http\Controllers\SearchController::class, 'index'])->name('search');

    Route::get('/policies', [PolicyController::class, 'index'])->name('policies.index');
    Route::get('/policies/{policy}', [PolicyController::class, 'show'])->name('policies.show');
    Route::post('/policies', [PolicyController::class, 'store'])
        ->middleware('can:manage_policies')->name('policies.store');
    Route::put('/policies/{policy}', [PolicyController::class, 'update'])
        ->middleware('can:manage_policies')->name('policies.update');

    Route::get('/documents', [DocumentController::class, 'index'])
        ->middleware('can:view_documents')->name('documents');
    Route::post('/documents', [DocumentController::class, 'store'])
        ->middleware('can:upload_documents')->name('documents.store');
    Route::get('/documents/{document}/download', [DocumentController::class, 'download'])
        ->middleware('can:view_documents')->name('documents.download');
    Route::post('/documents/{document}/read', [DocumentController::class, 'markRead'])
        ->middleware('can:view_documents')->name('documents.read');

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

    // ── Phase 4 ──────────────────────────────────────────────────────────

    Route::get('/confirm-password', [AuthController::class, 'confirmShow'])->name('password.confirm');
    Route::post('/confirm-password', [AuthController::class, 'confirm']);

    Route::get('/account', [AuthController::class, 'account'])->name('account');
    Route::put('/account/password', [AuthController::class, 'updatePassword'])->name('account.password');
    Route::put('/account/profile', [AuthController::class, 'updateProfile'])->name('account.profile');
    Route::post('/account/photo', [AuthController::class, 'updatePhoto'])->name('account.photo');
    Route::delete('/account/photo', [AuthController::class, 'removePhoto'])->name('account.photo.remove');

    Route::middleware('can:manage_finance')->group(function () {
        Route::get('/finance', [App\Http\Controllers\FinanceController::class, 'index'])->name('finance');
        Route::post('/finance/grants', [App\Http\Controllers\FinanceController::class, 'storeGrant'])->name('grants.store');
        Route::put('/finance/grants/{grant}', [App\Http\Controllers\FinanceController::class, 'updateGrant'])->name('grants.update');
        Route::post('/finance/grants/{grant}/expenditures', [App\Http\Controllers\FinanceController::class, 'storeExpenditure'])->name('grants.spend');
        Route::post('/finance/in-kind', [App\Http\Controllers\FinanceController::class, 'storeInKind'])->name('in-kind.store');

        Route::get('/invoices', [App\Http\Controllers\InvoiceController::class, 'index'])->name('invoices');
        Route::post('/invoices', [App\Http\Controllers\InvoiceController::class, 'store'])->name('invoices.store');
        Route::put('/invoices/{invoice}', [App\Http\Controllers\InvoiceController::class, 'update'])->name('invoices.update');
        Route::post('/invoices/{invoice}/paid', [App\Http\Controllers\InvoiceController::class, 'markPaid'])->name('invoices.paid');
    });

    Route::middleware(['can:access_safeguarding', 'password.confirm'])->group(function () {
        Route::get('/safeguarding', [App\Http\Controllers\SafeguardingController::class, 'index'])->name('safeguarding');
        Route::post('/safeguarding', [App\Http\Controllers\SafeguardingController::class, 'store'])->name('safeguarding.store');
        Route::put('/safeguarding/{concern}', [App\Http\Controllers\SafeguardingController::class, 'update'])->name('safeguarding.update');
    });

    Route::get('/compliance', [App\Http\Controllers\ComplianceController::class, 'index'])
        ->middleware('can:view_all_compliance')->name('compliance');
    Route::post('/compliance', [App\Http\Controllers\ComplianceController::class, 'store'])
        ->middleware('can:manage_compliance')->name('compliance.store');
    Route::post('/compliance/{item}/complete', [App\Http\Controllers\ComplianceController::class, 'complete'])
        ->middleware('can:manage_compliance')->name('compliance.complete');

    Route::post('/members/{member}/abc', [App\Http\Controllers\CareController::class, 'storeAbc'])
        ->middleware('can:log_sessions')->name('abc.store');
    Route::post('/members/{member}/body-maps', [App\Http\Controllers\CareController::class, 'storeBodyMap'])
        ->middleware('can:log_sessions')->name('body-maps.store');
    Route::get('/members/{member}/sar', [App\Http\Controllers\SarController::class, 'show'])
        ->middleware('can:edit_members')->name('members.sar');

    Route::get('/vehicles', [App\Http\Controllers\VehicleController::class, 'index'])
        ->middleware('can:view_vehicles')->name('vehicles');
    Route::post('/vehicles', [App\Http\Controllers\VehicleController::class, 'store'])
        ->middleware('can:manage_vehicles')->name('vehicles.store');
    Route::put('/vehicles/{vehicle}', [App\Http\Controllers\VehicleController::class, 'update'])
        ->middleware('can:manage_vehicles')->name('vehicles.update');
    Route::post('/vehicles/{vehicle}/defects', [App\Http\Controllers\VehicleController::class, 'storeDefect'])
        ->middleware('can:view_vehicles')->name('defects.store');
    Route::post('/defects/{defect}/resolve', [App\Http\Controllers\VehicleController::class, 'resolveDefect'])
        ->middleware('can:manage_vehicles')->name('defects.resolve');

    Route::get('/activities', [App\Http\Controllers\ActivityController::class, 'index'])->name('activities');
    Route::post('/activities', [App\Http\Controllers\ActivityController::class, 'store'])
        ->middleware('can:log_sessions')->name('activities.store');

    Route::get('/recognition', [App\Http\Controllers\RecognitionController::class, 'index'])->name('recognition');
    Route::post('/recognition', [App\Http\Controllers\RecognitionController::class, 'store'])->name('recognition.store');
    Route::post('/recognition/{recognition}/like', [App\Http\Controllers\RecognitionController::class, 'toggleLike'])->name('recognition.like');

    Route::get('/referrals', [App\Http\Controllers\ReferralController::class, 'index'])
        ->middleware('can:create_members')->name('referrals');
    Route::put('/referrals/{referral}/review', [App\Http\Controllers\ReferralController::class, 'review'])
        ->middleware('can:create_members')->name('referrals.review');

    Route::get('/settings', [App\Http\Controllers\SettingsController::class, 'edit'])
        ->middleware('can:manage_settings')->name('settings');
    Route::put('/settings', [App\Http\Controllers\SettingsController::class, 'update'])
        ->middleware('can:manage_settings')->name('settings.update');

    // ── Checklist gap-fill ───────────────────────────────────────────────

    // Member profile tabs
    Route::middleware('can:view_member_details')->group(function () {
        Route::post('/members/{member}/photo', [App\Http\Controllers\MemberProfileController::class, 'storePhoto'])
            ->middleware('can:edit_members')->name('members.photo');
        Route::post('/members/{member}/comms', [App\Http\Controllers\MemberProfileController::class, 'storeComms'])->name('comms.store');
        Route::delete('/members/{member}/comms/{comms}', [App\Http\Controllers\MemberProfileController::class, 'destroyComms'])->name('comms.destroy');
        Route::post('/members/{member}/contacts', [App\Http\Controllers\MemberProfileController::class, 'storeContact'])->name('contacts.store');
        Route::delete('/members/{member}/contacts/{contact}', [App\Http\Controllers\MemberProfileController::class, 'destroyContact'])->name('contacts.destroy');
        Route::post('/members/{member}/goals', [App\Http\Controllers\MemberProfileController::class, 'storeGoal'])->name('goals.store');
        Route::put('/members/{member}/goals/{goal}', [App\Http\Controllers\MemberProfileController::class, 'updateGoal'])->name('goals.update');
        Route::post('/members/{member}/outcomes', [App\Http\Controllers\MemberProfileController::class, 'storeOutcome'])->name('outcomes.store');
        Route::post('/members/{member}/alerts', [App\Http\Controllers\MemberProfileController::class, 'storeAlert'])->name('alerts.store');
        Route::delete('/members/{member}/alerts/{alert}', [App\Http\Controllers\MemberProfileController::class, 'destroyAlert'])->name('alerts.destroy');
        Route::post('/members/{member}/consents', [App\Http\Controllers\MemberProfileController::class, 'storeConsent'])->name('consents.store');
    });

    // Email composer
    Route::get('/email', [App\Http\Controllers\EmailComposerController::class, 'index'])
        ->middleware('can:view_member_details')->name('email');
    Route::post('/email/send', [App\Http\Controllers\EmailComposerController::class, 'send'])
        ->middleware('can:view_member_details')->name('email.send');
    Route::delete('/email/templates/{template}', [App\Http\Controllers\EmailComposerController::class, 'destroyTemplate'])
        ->middleware('can:view_member_details')->name('email.templates.destroy');

    // Daily monitoring dashboard
    Route::get('/monitoring', [App\Http\Controllers\MonitoringPageController::class, 'index'])
        ->middleware('can:log_welfare')->name('monitoring');

    // Transport corrections
    Route::delete('/transport/payments/{entry}', [App\Http\Controllers\TransportController::class, 'deletePayment'])
        ->middleware('can:log_sessions')->name('transport.payments.destroy');

    // Invoices
    Route::delete('/invoices/{invoice}', [App\Http\Controllers\InvoiceController::class, 'destroy'])
        ->middleware('can:manage_finance')->name('invoices.destroy');

    // Payroll rates + roster management
    Route::middleware('can:manage_payroll')->group(function () {
        Route::post('/payroll/rates', [PayrollController::class, 'setRate'])->name('payroll.rates.set');
        Route::delete('/payroll/roster/{rosterMember}', [PayrollController::class, 'destroyRosterMember'])->name('payroll.roster.destroy');
    });

    // Policy approval
    Route::post('/policies/{policy}/approve', [PolicyController::class, 'approve'])
        ->middleware('can:manage_policies')->name('policies.approve');

    // Reporting
    Route::middleware('can:view_reports')->group(function () {
        Route::get('/reports', [App\Http\Controllers\ReportsController::class, 'index'])->name('reports');
        Route::middleware('can:export_reports')->group(function () {
            Route::get('/reports/members.csv', [App\Http\Controllers\ReportsController::class, 'membersCsv'])->name('reports.members');
            Route::get('/reports/animals.csv', [App\Http\Controllers\ReportsController::class, 'animalsCsv'])->name('reports.animals');
            Route::get('/reports/activities.csv', [App\Http\Controllers\ReportsController::class, 'activitiesCsv'])->name('reports.activities');
            Route::get('/reports/hours.csv', [App\Http\Controllers\ReportsController::class, 'hoursCsv'])->name('reports.hours');
        });
    });

    // Audit log
    Route::get('/audit-log', [App\Http\Controllers\AuditLogController::class, 'index'])
        ->middleware('can:view_audit_log')->name('audit-log');

    // CSV import
    Route::middleware('can:manage_settings')->group(function () {
        Route::get('/import', [App\Http\Controllers\ImportController::class, 'index'])->name('import');
        Route::post('/import/preview', [App\Http\Controllers\ImportController::class, 'preview'])->name('import.preview');
        Route::post('/import/commit', [App\Http\Controllers\ImportController::class, 'commit'])->name('import.commit');
    });

    // Forms — dynamic form builder.
    Route::get('/forms', [App\Http\Controllers\FormController::class, 'index'])->name('forms');
    Route::get('/forms/new', [App\Http\Controllers\FormController::class, 'create'])->name('forms.create');
    Route::post('/forms', [App\Http\Controllers\FormController::class, 'store'])->name('forms.store');
    Route::get('/forms/{slug}', [App\Http\Controllers\FormController::class, 'show'])->name('forms.show');
    Route::post('/forms/{slug}/submissions', [App\Http\Controllers\FormController::class, 'submit'])->name('forms.submit');
    Route::get('/forms/{slug}/submissions', [App\Http\Controllers\FormController::class, 'submissions'])->name('forms.submissions');
    Route::put('/forms/{form}/toggle', [App\Http\Controllers\FormController::class, 'toggleActive'])->name('forms.toggle');

    // Incidents — any staff can report, managers manage status.
    Route::middleware('can:report_incidents')->group(function () {
        Route::get('/incidents', [App\Http\Controllers\IncidentController::class, 'index'])->name('incidents');
        Route::post('/incidents', [App\Http\Controllers\IncidentController::class, 'store'])->name('incidents.store');
    });
    Route::put('/incidents/{incident}', [App\Http\Controllers\IncidentController::class, 'update'])
        ->middleware('can:manage_incidents')->name('incidents.update');
    Route::middleware('can:manage_operations')->group(function () {
        Route::get('/maintenance', [App\Http\Controllers\MaintenanceTaskController::class, 'index'])->name('maintenance');
        Route::post('/maintenance', [App\Http\Controllers\MaintenanceTaskController::class, 'store'])->name('maintenance.store');
        Route::put('/maintenance/{task}', [App\Http\Controllers\MaintenanceTaskController::class, 'update'])->name('maintenance.update');
        Route::post('/maintenance/{task}/complete', [App\Http\Controllers\MaintenanceTaskController::class, 'complete'])->name('maintenance.complete');
        Route::delete('/maintenance/{task}', [App\Http\Controllers\MaintenanceTaskController::class, 'destroy'])->name('maintenance.destroy');

        Route::get('/projects', [App\Http\Controllers\ProjectController::class, 'index'])->name('projects');
        Route::post('/projects', [App\Http\Controllers\ProjectController::class, 'store'])->name('projects.store');
        Route::put('/projects/{project}', [App\Http\Controllers\ProjectController::class, 'update'])->name('projects.update');
        Route::delete('/projects/{project}', [App\Http\Controllers\ProjectController::class, 'destroy'])->name('projects.destroy');

        Route::get('/funding', [App\Http\Controllers\FundingController::class, 'index'])->name('funding');
        Route::post('/funding', [App\Http\Controllers\FundingController::class, 'store'])->name('funding.store');
        Route::put('/funding/{funding}', [App\Http\Controllers\FundingController::class, 'update'])->name('funding.update');
        Route::delete('/funding/{funding}', [App\Http\Controllers\FundingController::class, 'destroy'])->name('funding.destroy');
    });
    Route::middleware('can:request_products')->group(function () {
        Route::get('/orders', [App\Http\Controllers\ProductOrderController::class, 'index'])->name('orders');
        Route::post('/orders', [App\Http\Controllers\ProductOrderController::class, 'store'])->name('orders.store');
        Route::delete('/orders/{order}', [App\Http\Controllers\ProductOrderController::class, 'cancel'])->name('orders.cancel');
    });
    Route::middleware('can:manage_orders')->group(function () {
        Route::put('/orders/{order}/approve', [App\Http\Controllers\ProductOrderController::class, 'approve'])->name('orders.approve');
        Route::put('/orders/{order}/reject', [App\Http\Controllers\ProductOrderController::class, 'reject'])->name('orders.reject');
        Route::put('/orders/{order}/ordered', [App\Http\Controllers\ProductOrderController::class, 'markOrdered'])->name('orders.ordered');
        Route::put('/orders/{order}/delivered', [App\Http\Controllers\ProductOrderController::class, 'markDelivered'])->name('orders.delivered');
    });
});

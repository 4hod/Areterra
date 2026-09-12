<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Gate::before(fn (User $user) => $user->isAdministrator() ? true : null);

        foreach (config('capabilities.roles') as $capabilities) {
            foreach ($capabilities as $capability) {
                Gate::define($capability, fn (User $user) => $user->hasCapability($capability));
            }
        }

        // CompleteTasksOnEvent is a subscriber (it handles several events with one
        // method), so it needs registering — auto-discovery only finds listeners
        // whose handle() type-hints a single concrete event.
        \Illuminate\Support\Facades\Event::subscribe(\App\Listeners\CompleteTasksOnEvent::class);
        \Illuminate\Support\Facades\Event::subscribe(\App\Listeners\SyncTransportChargeOnOutcome::class);

        // Audit trail on every major model (SPEC checklist: Audit Log).
        $audited = [
            // Money changing without a trace is the thing to avoid.
            \App\Models\TransportLedgerEntry::class, \App\Models\LedgerEntry::class,
            \App\Models\Member::class, \App\Models\MemberSetting::class, \App\Models\Animal::class,
            \App\Models\WelfareCheck::class, \App\Models\DailyMonitoring::class, \App\Models\VetRecord::class,
            \App\Models\Attendance::class, \App\Models\EndOfDayRecord::class, \App\Models\TransportRun::class,
            \App\Models\TransportLedgerEntry::class, \App\Models\LeaveRequest::class, \App\Models\TimeclockEntry::class,
            \App\Models\Announcement::class, \App\Models\PayrollPeriod::class, \App\Models\PayrollEntry::class,
            \App\Models\PayrollRate::class, \App\Models\StaffRosterMember::class, \App\Models\Supervision::class,
            \App\Models\Policy::class, \App\Models\Document::class, \App\Models\RiskAssessment::class,
            \App\Models\MemberReview::class, \App\Models\Grant::class, \App\Models\GrantExpenditure::class,
            \App\Models\InKindDonation::class, \App\Models\MemberInvoice::class, \App\Models\SafeguardingConcern::class,
            \App\Models\ComplianceItem::class, \App\Models\AbcObservation::class, \App\Models\BodyMap::class,
            \App\Models\Vehicle::class, \App\Models\VehicleDefect::class, \App\Models\Activity::class,
            \App\Models\Referral::class, \App\Models\User::class, \App\Models\CommsLog::class,
            \App\Models\MemberContact::class, \App\Models\MemberGoal::class, \App\Models\MemberOutcome::class,
            \App\Models\MemberAlert::class, \App\Models\MemberConsent::class, \App\Models\Setting::class,
            \App\Models\ProductOrder::class, \App\Models\MaintenanceTask::class, \App\Models\Project::class,
            \App\Models\FundingOpportunity::class, \App\Models\Incident::class, \App\Models\FormDefinition::class,
            \App\Models\SarRequest::class, \App\Models\InsurancePolicy::class,
        ];
        foreach ($audited as $model) {
            $model::observe(\App\Observers\AuditLogObserver::class);
        }
    }
}

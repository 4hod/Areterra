<?php

namespace App\Http\Controllers;

use App\Models\Animal;
use App\Models\Document;
use App\Models\Member;
use App\Models\MemberReview;
use App\Models\StaffRosterMember;
use App\Models\Supervision;
use App\Models\User;
use Inertia\Inertia;

// System Audit: automatic health checks with traffic-light severity (SPEC.md §12).
class AuditController extends Controller
{
    public function index()
    {
        $findings = [];

        // 1. Members with no review date or overdue review.
        $latestReviews = MemberReview::orderByDesc('review_date')->get()->unique('member_id')->keyBy('member_id');
        foreach (Member::active()->get() as $member) {
            $next = $latestReviews->get($member->id)?->next_review_date;
            if ($next === null) {
                $findings[] = $this->finding('warning', "{$member->displayName()} has no review scheduled", '/reviews');
            } elseif ($next->lt(today())) {
                $findings[] = $this->finding('critical', "{$member->displayName()}'s review was due ".$next->format('j M Y'), '/reviews');
            }
        }

        // 2. Animals with no vet record in 12 months.
        $noVet = Animal::active()
            ->whereDoesntHave('vetRecords', fn ($q) => $q->where('visit_date', '>=', now()->subMonths(12)))
            ->get();
        foreach ($noVet as $animal) {
            $findings[] = $this->finding('warning', "{$animal->name} ({$animal->species}) has no vet record in the last 12 months", "/animals/{$animal->id}");
        }

        // 2b. Vaccinations/treatments due or overdue.
        foreach (\App\Models\VetRecord::with('animal')->whereNotNull('next_due_date')->get() as $record) {
            if (! $record->animal || $record->animal->status !== 'active') {
                continue;
            }
            if ($record->next_due_date->lt(today())) {
                $findings[] = $this->finding('critical', "{$record->animal->name} — vet/vaccination was due ".$record->next_due_date->format('j M Y'), "/animals/{$record->animal->id}");
            } elseif ($record->next_due_date->lte(today()->addDays(14))) {
                $findings[] = $this->finding('warning', "{$record->animal->name} — vet/vaccination due ".$record->next_due_date->format('j M Y'), "/animals/{$record->animal->id}");
            }
        }

        // 3. Animals with welfare status amber/red.
        foreach (Animal::active()->whereIn('welfare_status', ['amber', 'red'])->get() as $animal) {
            $findings[] = $this->finding(
                $animal->welfare_status === 'red' ? 'critical' : 'warning',
                "{$animal->name} ({$animal->species}) welfare status is {$animal->welfare_status}",
                "/animals/{$animal->id}",
            );
        }

        // 4. Members with no session record in 30 days.
        $inactive = Member::active()
            ->whereDoesntHave('endOfDayRecords', fn ($q) => $q->where('date', '>=', now()->subDays(30)))
            ->get();
        foreach ($inactive as $member) {
            $findings[] = $this->finding('info', "{$member->displayName()} has no session record in the last 30 days", "/members/{$member->id}");
        }

        // 5. Staff with no supervision in 12 weeks.
        $supervisions = Supervision::orderByDesc('date')->get()->groupBy(fn ($s) => $s->subject_type.':'.$s->subject_id);
        $checkStaff = function (string $key, string $name) use ($supervisions, &$findings) {
            $history = $supervisions->get($key, collect());
            $latest = $history->where('type', '!=', 'appraisal')->first();
            if ($latest === null || $latest->date->lt(now()->subWeeks(12))) {
                $findings[] = $this->finding('warning', "{$name} has had no supervision in the last 12 weeks", '/supervisions');
            }

            // Appraisals are annual, not 12-weekly — checked separately, and
            // prefer the explicit next_due_date over a flat interval when set.
            $latestAppraisal = $history->where('type', 'appraisal')->first();
            $dueDate = $latestAppraisal?->next_due_date ?? $latestAppraisal?->date?->addYear();
            if ($latestAppraisal === null) {
                $findings[] = $this->finding('info', "{$name} has never had an appraisal", '/supervisions');
            } elseif ($dueDate && $dueDate->lt(today())) {
                $findings[] = $this->finding('warning', "{$name}'s appraisal was due ".$dueDate->format('j M Y'), '/supervisions');
            }
        };
        User::all()->each(fn ($u) => $checkStaff(User::class.':'.$u->id, $u->name));
        StaffRosterMember::where('active', true)->get()
            ->reject(fn ($s) => User::whereRaw('lower(name) = ?', [mb_strtolower($s->name)])->exists())
            ->each(fn ($s) => $checkStaff(StaffRosterMember::class.':'.$s->id, $s->name));

        // 6. Documents requiring read confirmation that staff haven't read.
        $staffCount = User::count();
        foreach (Document::where('requires_read', true)->withCount('reads')->get() as $doc) {
            $unread = $staffCount - $doc->reads_count;
            if ($unread > 0) {
                $findings[] = $this->finding('info', "\"{$doc->title}\" is unread by {$unread} staff", '/documents');
            }
        }

        // 7. Pending referrals older than 5 days.
        $stale = \App\Models\Referral::where('status', 'pending')
            ->where('created_at', '<', now()->subDays(5))
            ->get();
        foreach ($stale as $referral) {
            $days = (int) $referral->created_at->diffInDays(now());
            $findings[] = $this->finding('warning', "Referral for {$referral->person_name} has been pending for {$days} days", '/referrals');
        }

        // 8. Overdue compliance items.
        foreach (\App\Models\ComplianceItem::whereNull('completed_at')->where('due_date', '<', today())->get() as $item) {
            $findings[] = $this->finding('critical', "Compliance item \"{$item->title}\" was due ".$item->due_date->format('j M Y'), '/compliance');
        }

        // 9. Consents needing annual re-confirmation.
        foreach (\App\Models\MemberConsent::where('granted', true)->with('member')->get() as $consent) {
            if ($consent->member && $consent->member->status === 'active' && $consent->isExpired()) {
                $findings[] = $this->finding('warning', "{$consent->member->displayName()}'s {$consent->consent_type} consent needs re-confirming", "/members/{$consent->member_id}");
            }
        }

        // 10. SAR requests approaching or past the statutory deadline.
        foreach (\App\Models\SarRequest::whereNotIn('status', ['fulfilled', 'declined'])->get() as $sar) {
            if ($sar->deadline_date->lt(today())) {
                $findings[] = $this->finding('critical', "SAR from {$sar->requester_name} is past the statutory deadline (".$sar->deadline_date->format('j M Y').')', '/sar-requests');
            } elseif ($sar->deadline_date->lte(today()->addDays(5))) {
                $findings[] = $this->finding('warning', "SAR from {$sar->requester_name} is due ".$sar->deadline_date->format('j M Y'), '/sar-requests');
            }
        }

        // 11. Insurance policies expiring or expired.
        foreach (\App\Models\InsurancePolicy::whereNotNull('renewal_date')->get() as $policy) {
            if ($policy->renewal_date->lt(today())) {
                $findings[] = $this->finding('critical', "{$policy->policy_type} insurance expired ".$policy->renewal_date->format('j M Y'), '/insurance');
            } elseif ($policy->renewal_date->lte(today()->addDays(30))) {
                $findings[] = $this->finding('warning', "{$policy->policy_type} insurance renews ".$policy->renewal_date->format('j M Y'), '/insurance');
            }
        }

        $order = ['critical' => 0, 'warning' => 1, 'info' => 2];
        usort($findings, fn ($a, $b) => $order[$a['severity']] <=> $order[$b['severity']]);

        return Inertia::render('Audit', [
            'findings' => $findings,
            'counts' => [
                'critical' => count(array_filter($findings, fn ($f) => $f['severity'] === 'critical')),
                'warning' => count(array_filter($findings, fn ($f) => $f['severity'] === 'warning')),
                'info' => count(array_filter($findings, fn ($f) => $f['severity'] === 'info')),
            ],
        ]);
    }

    private function finding(string $severity, string $message, string $link): array
    {
        return compact('severity', 'message', 'link');
    }
}

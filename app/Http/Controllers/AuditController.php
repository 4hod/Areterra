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
            $latest = $supervisions->get($key)?->first();
            if ($latest === null || $latest->date->lt(now()->subWeeks(12))) {
                $findings[] = $this->finding('warning', "{$name} has had no supervision in the last 12 weeks", '/supervisions');
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

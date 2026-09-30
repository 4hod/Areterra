<?php

namespace App\Support;

use App\Models\ComplianceItem;
use App\Models\Grant;
use App\Models\MemberInvoice;
use App\Models\Task;
use App\Models\User;

/**
 * Point 12 — notifications come from data, not from someone remembering to
 * create a reminder. Nothing here is stored as a "reminder"; it is derived
 * every time from the records themselves.
 *
 * `due_notices` exists only to remember what has already been sent, so the
 * same thing isn't announced every morning.
 */
final class DueScanner
{
    /**
     * Everything currently needing attention, newest deadline first.
     *
     * @return array<int, array<string, mixed>>
     */
    /**
     * Q25 — this is the no-cron path. Shared hosting often can't run Laravel's
     * scheduler, and a notification system that silently never fires is worse
     * than none. Scanning on dashboard load, cached for an hour, means alerts
     * appear whenever somebody is actually using the Hub. If you do get cron
     * working later, call scan() from the scheduler and this still behaves.
     */
    public static function scanThrottled(int $horizonDays = 30, ?User $user = null): array
    {
        $scope = $user
            ? $user->id.':'.sha1(implode('|', $user->capabilities()))
            : 'system';

        return \Illuminate\Support\Facades\Cache::remember(
            'due_scan:'.today()->toDateString().':'.$horizonDays.':'.$scope,
            now()->addHour(),
            fn () => self::scan($horizonDays, $user),
        );
    }

    public static function scan(int $horizonDays = 30, ?User $user = null): array
    {
        $horizon = today()->addDays($horizonDays);
        $notices = [];

        foreach (($user && ! $user->hasCapability('view_all_compliance')
            ? collect()
            : ComplianceItem::query()->whereNull('completed_at')->whereNotNull('due_date')->whereDate('due_date', '<=', $horizon)->get()) as $item) {
            $notices[] = self::notice(
                'compliance_due',
                $item,
                $item->due_date,
                "{$item->title} is due ".self::when($item->due_date),
            );
        }

        foreach (($user && ! $user->hasCapability('manage_finance')
            ? collect()
            : MemberInvoice::query()->where('status', '!=', 'paid')->whereNotNull('due_date')->whereDate('due_date', '<', today())->get()) as $invoice) {
            $notices[] = self::notice(
                'invoice_overdue',
                $invoice,
                $invoice->due_date,
                'Invoice '.($invoice->qb_reference ?? "#{$invoice->id}").' is overdue',
            );
        }

        foreach (($user && ! $user->hasCapability('manage_finance')
            ? collect()
            : Grant::query()->whereNotNull('end_date')->whereDate('end_date', '<=', $horizon)->where('status', 'active')->get()) as $grant) {
            $notices[] = self::notice(
                'grant_reporting_due',
                $grant,
                $grant->end_date,
                "{$grant->title} reporting period ends ".self::when($grant->end_date),
            );
        }

        $tasks = Task::query()->overdue()
            ->when($user && ! $user->hasCapability('manage_operations'), fn ($q) => $q->where(function ($owned) use ($user) {
                $owned->where('assigned_to', $user->id)->orWhere('created_by', $user->id);
            }))
            ->get();
        foreach ($tasks as $task) {
            $notices[] = self::notice('task_overdue', $task, $task->due_date, "{$task->title} is overdue");
        }

        usort($notices, fn ($a, $b) => ($a['due_on'] ?? '9999') <=> ($b['due_on'] ?? '9999'));

        return $notices;
    }

    private static function notice(string $kind, object $subject, $dueOn, string $message): array
    {
        return [
            'kind' => $kind,
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'due_on' => $dueOn?->toDateString(),
            'overdue' => $dueOn !== null && $dueOn->isPast(),
            'message' => $message,
        ];
    }

    private static function when($date): string
    {
        if ($date->isToday()) {
            return 'today';
        }

        return $date->isPast()
            ? $date->diffForHumans()
            : 'in '.$date->diffInDays(today()).' days';
    }
}

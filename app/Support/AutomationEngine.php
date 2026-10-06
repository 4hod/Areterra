<?php

namespace App\Support;

use App\Models\Activity;
use App\Models\AutomationRule;
use App\Models\AutomationRun;
use App\Models\Document;
use App\Models\MaintenanceTask;
use App\Models\Referral;
use App\Models\Task;
use App\Models\User;
use App\Notifications\AutomationAlert;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class AutomationEngine
{
    public const RECIPES = [
        'member_absent' => ['name' => 'Stand down transport when a member is absent', 'action' => 'stand_down_transport'],
        'maintenance_open_7_days' => ['name' => 'Escalate maintenance open for seven days', 'action' => 'create_task'],
        'document_expires_30_days' => ['name' => 'Create a task 30 days before a document expires', 'action' => 'create_task'],
        'trial_completed' => ['name' => 'Open a review when a trial day is completed', 'action' => 'create_review_task'],
        'activity_cancelled' => ['name' => 'Identify people affected by a cancelled activity', 'action' => 'notify_affected'],
    ];

    public function ensureSystemRules(?int $userId = null): void
    {
        foreach (self::RECIPES as $trigger => $recipe) {
            AutomationRule::firstOrCreate(
                ['trigger' => $trigger, 'system' => true],
                ['name' => $recipe['name'], 'action' => $recipe['action'], 'active' => true, 'created_by' => $userId],
            );
        }
    }

    public function run(string $trigger, Model $subject, array $context = []): void
    {
        $this->ensureSystemRules();

        AutomationRule::where('trigger', $trigger)->where('active', true)->get()->each(function (AutomationRule $rule) use ($trigger, $subject, $context) {
            $fingerprint = hash('sha256', implode('|', [$rule->id, $subject->getMorphClass(), $subject->getKey(), $context['occurrence'] ?? 'once']));
            if (AutomationRun::where('fingerprint', $fingerprint)->exists()) {
                return;
            }

            try {
                $summary = $this->execute($rule, $subject, $context);
                AutomationRun::create([
                    'automation_rule_id' => $rule->id, 'trigger' => $trigger,
                    'subject_type' => $subject->getMorphClass(), 'subject_id' => $subject->getKey(),
                    'status' => 'completed', 'summary' => $summary, 'fingerprint' => $fingerprint, 'ran_at' => now(),
                ]);
                $rule->update(['last_run_at' => now()]);
            } catch (\Throwable $exception) {
                Log::error('Areterra automation failed', ['rule' => $rule->id, 'error' => $exception->getMessage()]);
                AutomationRun::create([
                    'automation_rule_id' => $rule->id, 'trigger' => $trigger,
                    'subject_type' => $subject->getMorphClass(), 'subject_id' => $subject->getKey(),
                    'status' => 'failed', 'summary' => $exception->getMessage(), 'fingerprint' => $fingerprint, 'ran_at' => now(),
                ]);
            }
        });
    }

    public function scanScheduled(): int
    {
        $this->ensureSystemRules();
        $count = 0;

        MaintenanceTask::whereNull('completed_at')->where('created_at', '<=', now()->subDays(7))->each(function ($task) use (&$count) {
            $this->run('maintenance_open_7_days', $task);
            $count++;
        });
        Document::whereNotNull('expires_at')->whereBetween('expires_at', [today(), today()->addDays(30)])->each(function ($document) use (&$count) {
            $this->run('document_expires_30_days', $document, ['occurrence' => $document->expires_at->toDateString()]);
            $count++;
        });

        return $count;
    }

    private function execute(AutomationRule $rule, Model $subject, array $context): string
    {
        return match ($rule->action) {
            'stand_down_transport' => 'Transport legs were removed from today’s live list by the absence workflow.',
            'create_task' => $this->createEscalationTask($rule->trigger, $subject),
            'create_review_task' => $this->createTrialReview($subject),
            'notify_affected' => $this->notifyAffected($subject, $context),
            default => throw new \InvalidArgumentException("Unsupported automation action: {$rule->action}"),
        };
    }

    private function createEscalationTask(string $trigger, Model $subject): string
    {
        $manager = User::managers()->orderByRaw("case when lower(name) like '%ethan%' then 0 else 1 end")->first();
        if ($trigger === 'maintenance_open_7_days' && $subject instanceof MaintenanceTask) {
            $title = 'Escalated maintenance: '.$subject->title;
            $description = 'This maintenance issue has remained open for at least seven days.';
            $due = today();
        } elseif ($subject instanceof Document) {
            $title = 'Renew document: '.$subject->title;
            $description = 'This document expires on '.$subject->expires_at->format('j M Y').'.';
            $due = $subject->expires_at->copy()->subDays(7);
        } else {
            throw new \InvalidArgumentException('The selected task recipe does not support this record.');
        }

        Task::create([
            'title' => $title, 'description' => $description, 'priority' => 'high', 'due_date' => $due,
            'taskable_type' => $subject->getMorphClass(), 'taskable_id' => $subject->getKey(),
            'assigned_to' => $manager?->id, 'created_by' => $manager?->id,
        ]);

        return $title.' was created and assigned to '.($manager?->name ?? 'the management queue').'.';
    }

    private function createTrialReview(Model $subject): string
    {
        if (! $subject instanceof Referral) {
            throw new \InvalidArgumentException('Trial review recipe requires a referral.');
        }
        $manager = User::managers()->first();
        Task::create([
            'title' => 'Review trial day: '.$subject->person_name,
            'description' => 'Review the completed trial day and agree the next step with the person/referrer.',
            'priority' => 'high', 'due_date' => today()->addDays(2),
            'taskable_type' => $subject->getMorphClass(), 'taskable_id' => $subject->id,
            'assigned_to' => $manager?->id, 'created_by' => $manager?->id,
        ]);
        $subject->update(['trial_review_opened_at' => now()]);

        return 'A trial review task was opened for '.$subject->person_name.'.';
    }

    private function notifyAffected(Model $subject, array $context): string
    {
        if (! $subject instanceof Activity) {
            throw new \InvalidArgumentException('Cancellation recipe requires an activity.');
        }
        $names = $subject->members()->get()->map->displayName()->values();
        $message = $names->isEmpty()
            ? "{$subject->title} was cancelled; no member participants were linked."
            : "{$subject->title} was cancelled. Affected members: ".$names->join(', ').'.';
        User::managers()->get()->each(fn (User $user) => $user->notify(new AutomationAlert('Activity cancelled', $message, '/calendar')));

        return $message;
    }
}

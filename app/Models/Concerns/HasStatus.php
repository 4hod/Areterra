<?php

namespace App\Models\Concerns;

use App\Exceptions\WorkflowException;
use App\Models\StatusTransition;

/**
 * Point 16 — statuses move through declared stages, never sideways.
 *
 * The model declares its own machine:
 *
 *   public static array $statuses = [
 *       'draft'     => ['validated'],
 *       'validated' => ['approved', 'draft'],
 *       'approved'  => ['processed'],
 *       'processed' => [],
 *   ];
 *
 * transitionTo() refuses anything not declared and records who moved it.
 */
trait HasStatus
{
    public function statusHistory()
    {
        return $this->morphMany(StatusTransition::class, 'subject')->latest();
    }

    /** @return array<int, string> */
    public function allowedTransitions(): array
    {
        return static::$statuses[$this->status] ?? [];
    }

    public function canTransitionTo(string $status): bool
    {
        return in_array($status, $this->allowedTransitions(), true);
    }

    public function transitionTo(string $status, ?string $reason = null): static
    {
        if (! $this->canTransitionTo($status)) {
            $allowed = $this->allowedTransitions();

            throw new WorkflowException(
                [sprintf(
                    '%s cannot move from "%s" to "%s". %s',
                    class_basename($this),
                    $this->status,
                    $status,
                    $allowed === []
                        ? 'It is already at its final stage.'
                        : 'Allowed from here: '.implode(', ', $allowed).'.',
                )],
                'That status change is not allowed.',
                ['subject' => static::class, 'id' => $this->getKey()],
            );
        }

        $from = $this->status;
        $this->update(['status' => $status]);

        $this->statusHistory()->create([
            'from' => $from,
            'to' => $status,
            'reason' => $reason,
            'user_id' => auth()->id(),
        ]);

        return $this;
    }
}

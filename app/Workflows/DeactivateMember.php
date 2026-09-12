<?php

namespace App\Workflows;

use App\Models\Attendance;
use App\Models\Member;
use App\Models\TransportRun;
use App\Support\TransportCredit;

/**
 * "If they're set inactive, remove them from all registers and everything.
 *  Only add them back in when they're set to active again."
 *
 * Future scheduling is cleared; history is untouched. The outstanding transport
 * balance is frozen onto their file as a note rather than written off or chased.
 */
final class DeactivateMember extends Workflow
{
    public function __construct(
        private readonly Member $member,
        private readonly ?int $userId = null,
    ) {}

    public function problems(): array
    {
        return $this->member->status === 'inactive'
            ? ["{$this->member->displayName()} is already inactive."]
            : [];
    }

    protected function summary(): string
    {
        return 'This member could not be made inactive.';
    }

    protected function execute(): Member
    {
        // Forward-dated only — today and everything past stays as it happened.
        Attendance::where('member_id', $this->member->id)
            ->whereDate('date', '>', today())
            ->delete();

        TransportRun::where('member_id', $this->member->id)
            ->whereDate('run_date', '>', today())
            ->delete();

        $this->member->update([
            'status' => 'inactive',
            'deactivated_at' => now(),
            // Noted, not chased and not written off.
            'closing_transport_balance' => TransportCredit::balance($this->member->id),
        ]);

        $this->member->settings?->update(['transport_required' => false]);

        return $this->member->fresh();
    }
}

<?php

namespace App\Listeners;

use App\Events\SafeguardingConcernRaised;
use App\Models\User;
use App\Notifications\ConcernRaised;
use Illuminate\Support\Facades\Notification;

class NotifySafeguardingLeads
{
    public function handle(SafeguardingConcernRaised $event): void
    {
        $recipients = User::query()
            ->whereHas('capabilityGrants', fn ($query) => $query->where('capability', 'access_safeguarding'))
            ->get();

        Notification::send(
            $recipients,
            new ConcernRaised(
                'Safeguarding concern recorded',
                'A safeguarding concern requires review in the secure Hub.',
                '/safeguarding',
                'safeguarding',
            ),
        );
    }
}

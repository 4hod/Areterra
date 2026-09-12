<?php

namespace App\Listeners;

use App\Events\IncidentLogged;
use App\Models\Animal;
use App\Models\Member;
use App\Models\Vehicle;

/**
 * The case Ethan described: something logged in one place shows up wherever
 * else it is relevant, without being re-entered.
 *
 * An incident now knows its subject, so it can raise the right follow-up in
 * the right module — a member review, an animal welfare check, or a vehicle
 * defect — instead of sitting in the incidents list alone.
 */
class RouteIncidentToItsSubject
{
    public function handle(IncidentLogged $event): void
    {
        $incident = $event->incident;
        $subject = $incident->subject ?? $incident->vehicle;

        if (! $subject || ! method_exists($subject, 'addTask')) {
            return;
        }

        [$title, $priority] = match (true) {
            $subject instanceof Member => ["Follow up incident: {$incident->title}", 'high'],
            $subject instanceof Animal => ["Welfare follow-up after: {$incident->title}", 'high'],
            $subject instanceof Vehicle => ["Check vehicle after: {$incident->title}", 'medium'],
            default => ["Follow up: {$incident->title}", 'medium'],
        };

        $subject->addTask([
            'title' => $title,
            'description' => "Raised automatically from incident #{$incident->id}.",
            'priority' => $incident->severity === 'high' ? 'high' : $priority,
            'due_date' => today()->addDays($incident->severity === 'high' ? 1 : 7),
            'created_by' => $incident->reported_by,
        ]);
    }
}

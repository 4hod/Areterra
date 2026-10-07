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

        // A linked incident has always raised a task on its subject. Keep that
        // safety behaviour even when the optional follow-up checkbox is not
        // ticked: the checkbox records the reporter's assessment, while the
        // automatic task makes sure the linked module cannot silently miss it.
        if (! $subject || ! method_exists($subject, 'addTask')) {
            return;
        }

        $completionKey = "incident_closed:{$incident->id}";
        if ($subject->openTasks()->where('completes_on_event', $completionKey)->exists()) {
            return;
        }

        $urgent = in_array($incident->severity, ['serious', 'critical'], true);

        [$title, $priority] = match (true) {
            $subject instanceof Member => ["Follow up incident: {$incident->title}", 'high'],
            $subject instanceof Animal => ["Welfare follow-up after: {$incident->title}", 'high'],
            $subject instanceof Vehicle => ["Check vehicle after: {$incident->title}", 'medium'],
            default => ["Follow up: {$incident->title}", 'medium'],
        };

        $subject->addTask([
            'title' => $title,
            'description' => "Raised automatically from incident #{$incident->id}.",
            'priority' => $urgent ? 'high' : $priority,
            'due_date' => today()->addDays($urgent ? 1 : 7),
            'created_by' => $incident->reported_by,
            'completes_on_event' => $completionKey,
        ]);
    }
}

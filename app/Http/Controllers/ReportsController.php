<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Animal;
use App\Models\Attendance;
use App\Models\EndOfDayRecord;
use App\Models\ImpactEntry;
use App\Models\Member;
use App\Models\WelfareCheck;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportsController extends Controller
{
    public function index(Request $request)
    {
        $from = $request->date('from') ?? today()->startOfMonth();
        $to = $request->date('to') ?? today();
        $evidence = ImpactEntry::whereBetween('observed_at', [$from, $to->copy()->endOfDay()])->get();
        $tagSummary = $evidence->flatMap(fn ($entry) => $entry->evidence_tags ?? [])->countBy()->sortDesc()->take(6);

        return Inertia::render('Reports', [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'impact' => [
                'membersServed' => Attendance::whereBetween('date', [$from, $to])->where('checked_in', true)->distinct('member_id')->count('member_id'),
                'attendances' => Attendance::whereBetween('date', [$from, $to])->where('checked_in', true)->count(),
                'sessionsRecorded' => EndOfDayRecord::whereBetween('date', [$from, $to])->count(),
                'welfareChecks' => WelfareCheck::whereBetween('created_at', [$from, $to->copy()->endOfDay()])->count(),
                'activities' => Activity::whereBetween('activity_date', [$from, $to])->count(),
                'impactEvidence' => $evidence->count(),
                'membersWithEvidence' => $evidence->unique('member_id')->count(),
                'averageEngagement' => round((float) ($evidence->whereNotNull('engagement_rating')->avg('engagement_rating') ?? 0), 1),
                'averageIndependence' => round((float) ($evidence->whereNotNull('independence_rating')->avg('independence_rating') ?? 0), 1),
            ],
            'tagSummary' => $tagSummary,
        ]);
    }

    public function membersCsv(): StreamedResponse
    {
        return $this->csv('members.csv',
            ['Name', 'Preferred name', 'Status', 'Attendance days', 'Transport', 'Key worker'],
            Member::with('settings.keyWorker')->orderBy('first_name')->get()->map(fn ($m) => [
                trim("{$m->first_name} {$m->last_name}"),
                $m->preferred_name,
                $m->status,
                collect($m->settings?->attendance_days ?? [])->implode('|'),
                $m->settings?->transport_required ? 'yes' : 'no',
                $m->settings?->keyWorker?->name,
            ]),
        );
    }

    public function animalsCsv(): StreamedResponse
    {
        return $this->csv('animals.csv',
            ['Name', 'Species', 'Breed', 'Sex', 'Status', 'Welfare status', 'Microchip'],
            Animal::orderBy('species')->orderBy('name')->get()->map(fn ($a) => [
                $a->name, $a->species, $a->breed, $a->sex, $a->status, $a->welfare_status, $a->microchip,
            ]),
        );
    }

    public function activitiesCsv(Request $request): StreamedResponse
    {
        $from = $request->date('from') ?? today()->startOfMonth();
        $to = $request->date('to') ?? today();

        return $this->csv('activities.csv',
            ['Date', 'Time', 'Title', 'Description', 'Logged by'],
            Activity::with('user:id,name')->whereBetween('activity_date', [$from, $to])->orderBy('activity_date')->get()
                ->map(fn ($a) => [
                    $a->activity_date->toDateString(),
                    $a->start_time,
                    $a->title,
                    $a->description,
                    $a->user->name,
                ]),
        );
    }

    private function csv(string $filename, array $headers, $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $headers);
            foreach ($rows as $row) {
                fputcsv($out, collect($row)->map(fn ($value) => $this->safeCsvValue($value))->all());
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    private function safeCsvValue(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        return preg_match('/^[=+\-@]/', ltrim($value)) ? "'".$value : $value;
    }
}

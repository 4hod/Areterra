<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Animal;
use App\Models\Attendance;
use App\Models\EndOfDayRecord;
use App\Models\Member;
use App\Models\TimeclockEntry;
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

        return Inertia::render('Reports', [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'impact' => [
                'membersServed' => Attendance::whereBetween('date', [$from, $to])->where('checked_in', true)->distinct('member_id')->count('member_id'),
                'attendances' => Attendance::whereBetween('date', [$from, $to])->where('checked_in', true)->count(),
                'sessionsRecorded' => EndOfDayRecord::whereBetween('date', [$from, $to])->count(),
                'welfareChecks' => WelfareCheck::whereBetween('created_at', [$from, $to->copy()->endOfDay()])->count(),
                'activities' => Activity::whereBetween('activity_date', [$from, $to])->count(),
                'staffHours' => round(
                    TimeclockEntry::whereBetween('clock_in', [$from, $to->copy()->endOfDay()])
                        ->whereNotNull('clock_out')->get()
                        ->sum(fn ($e) => $e->workedMinutes() ?? 0) / 60,
                    1,
                ),
            ],
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

    public function hoursCsv(Request $request): StreamedResponse
    {
        $from = $request->date('from') ?? today()->startOfMonth();
        $to = $request->date('to') ?? today();

        $rows = TimeclockEntry::with('user:id,name')
            ->whereBetween('clock_in', [$from, $to->copy()->endOfDay()])
            ->whereNotNull('clock_out')
            ->get()
            ->groupBy('user_id')
            ->map(fn ($entries) => [
                $entries->first()->user->name,
                $entries->count(),
                round($entries->sum(fn ($e) => $e->workedMinutes() ?? 0) / 60, 2),
            ])
            ->values();

        return $this->csv('hours.csv', ['Staff', 'Shifts', 'Hours'], $rows);
    }

    private function csv(string $filename, array $headers, $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $headers);
            foreach ($rows as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}

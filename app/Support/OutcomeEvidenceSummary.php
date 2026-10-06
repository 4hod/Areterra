<?php

namespace App\Support;

use App\Models\ActivityParticipant;
use App\Models\EndOfDayRecord;
use App\Models\Member;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class OutcomeEvidenceSummary
{
    private const KEYWORDS = [
        'independence' => ['independent', 'independently', 'on their own', 'without support'],
        'communication' => ['communicat', 'conversation', 'spoke', 'talked', 'asked'],
        'practical-skills' => ['cook', 'wood', 'feed', 'clean', 'garden', 'animal', 'project', 'made', 'prepared'],
        'social' => ['group', 'team', 'friend', 'community', 'outing', 'together'],
        'choice' => ['chose', 'choice', 'selected', 'decided', 'preferred'],
        'confidence' => ['confiden', 'tried', 'completed', 'achieved', 'proud'],
        'wellbeing' => ['happy', 'enjoy', 'calm', 'wellbeing', 'relaxed'],
    ];

    public static function for(Member $member, CarbonInterface $from, CarbonInterface $to): array
    {
        $impact = $member->impactEntries()
            ->with(['goal:id,title', 'activity:id,title,activity_date', 'animal:id,name,species'])
            ->whereBetween('observed_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->orderByDesc('observed_at')->get();

        $participation = ActivityParticipant::query()
            ->with('activity:id,title,activity_date,description')
            ->where('member_id', $member->id)
            ->where('attended', true)
            ->whereHas('activity', fn ($q) => $q->whereBetween('activity_date', [$from, $to]))
            ->get();

        $dayRecords = EndOfDayRecord::query()
            ->where('member_id', $member->id)
            ->whereBetween('date', [$from, $to])
            ->orderByDesc('date')->get();

        $themeExamples = collect();
        foreach ($impact as $entry) {
            foreach ($entry->evidence_tags ?? [] as $tag) {
                $themeExamples->push([
                    'theme' => $tag,
                    'date' => $entry->observed_at->toDateString(),
                    'example' => $entry->outcome_note,
                    'source' => 'Connected impact',
                ]);
            }
        }

        foreach ($dayRecords as $record) {
            $text = trim(collect([$record->activities, $record->notes])->filter()->join(' — '));
            if ($text === '') continue;
            foreach (self::themesFor($text) as $theme) {
                $themeExamples->push([
                    'theme' => $theme,
                    'date' => $record->date->toDateString(),
                    'example' => Str::limit($text, 260),
                    'source' => 'Daily record',
                ]);
            }
        }

        $activityTitles = $participation->pluck('activity.title')
            ->merge($impact->pluck('activity.title'))->filter()->unique()->values();

        $photoItems = $dayRecords->flatMap(function ($record) use ($member) {
            return collect(PrivateMedia::endOfDayPhotoUrls($member, $record))->map(fn ($url) => [
                'type' => 'photo',
                'date' => $record->date->toDateString(),
                'title' => $record->activities ?: 'Areterra day',
                'description' => $record->notes,
                'media_url' => $url,
                'source' => 'Daily record',
            ]);
        })->values();

        return [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'participation_count' => $participation->count(),
            'activity_titles' => $activityTitles,
            'headline' => self::headline($member, $participation->count(), $activityTitles),
            'themes' => $themeExamples->groupBy('theme')->map(fn (Collection $rows, string $theme) => [
                'theme' => $theme,
                'count' => $rows->count(),
                'examples' => $rows->take(5)->values(),
            ])->sortByDesc('count')->values(),
            'impact_entries' => $impact->map(fn ($entry) => [
                'date' => $entry->observed_at->toDateString(),
                'note' => $entry->outcome_note,
                'activity' => $entry->activity?->title,
                'animal' => $entry->animal?->name,
                'goal' => $entry->goal?->title,
                'tags' => $entry->evidence_tags ?? [],
            ])->values(),
            'photos' => $photoItems,
        ];
    }

    private static function themesFor(string $text): array
    {
        $lower = Str::lower($text);

        return collect(self::KEYWORDS)
            ->filter(fn ($needles) => collect($needles)->contains(fn ($needle) => str_contains($lower, $needle)))
            ->keys()->values()->all();
    }

    private static function headline(Member $member, int $count, Collection $titles): string
    {
        $name = $member->preferred_name ?: $member->first_name;
        if ($count === 0 && $titles->isEmpty()) {
            return "No recorded activity participation is available for {$name} in this period yet.";
        }

        $list = $titles->take(3)->join(', ', ' and ');
        $more = max(0, $titles->count() - 3);

        return "Over this period, {$name} took part in {$count} recorded session".($count === 1 ? '' : 's')
            .($list ? ", including {$list}" : '')
            .($more ? " and {$more} other activit".($more === 1 ? 'y' : 'ies') : '').'.';
    }
}

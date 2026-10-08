<?php

namespace App\Support;

use App\Models\EndOfDayRecord;
use App\Models\Member;
use App\Models\User;

final class PrivateMedia
{
    public static function disk(): string
    {
        return (string) config('filesystems.default', 'local');
    }

    public static function path(?string $storedPath): ?string
    {
        if (! $storedPath) {
            return null;
        }

        return ltrim(str_starts_with($storedPath, '/storage/')
            ? substr($storedPath, strlen('/storage/'))
            : $storedPath, '/');
    }

    public static function memberPhotoUrl(Member $member): ?string
    {
        return $member->photo_path ? route('media.members.photo', $member) : null;
    }

    public static function staffPhotoUrl(User $user): ?string
    {
        return $user->photo_path ? route('media.staff.photo', $user) : null;
    }

    /** @return array<int, string> */
    public static function endOfDayPhotoUrls(Member $member, EndOfDayRecord $record): array
    {
        return collect($record->photos ?? [])
            ->values()
            ->keys()
            ->map(fn (int $index) => route('media.end-of-day.photo', [$member, $record, $index]))
            ->all();
    }
}

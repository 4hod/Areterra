<?php

namespace App\Http\Controllers;

use App\Models\EndOfDayRecord;
use App\Models\Member;
use App\Models\User;
use App\Support\PrivateMedia;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PrivateMediaController extends Controller
{
    public function memberPhoto(Member $member): StreamedResponse
    {
        return $this->serve($member->photo_path, 'member-photos/');
    }

    public function staffPhoto(User $user): StreamedResponse
    {
        return $this->serve($user->photo_path, 'staff-photos/');
    }

    public function endOfDayPhoto(Member $member, EndOfDayRecord $record, int $index): StreamedResponse
    {
        abort_unless($record->member_id === $member->id, 404);
        $photos = array_values($record->photos ?? []);
        abort_unless(array_key_exists($index, $photos), 404);

        return $this->serve($photos[$index], 'end-of-day-photos/');
    }

    private function serve(?string $storedPath, string $requiredPrefix): StreamedResponse
    {
        $path = PrivateMedia::path($storedPath);
        abort_unless($path && str_starts_with($path, $requiredPrefix), 404);

        $disk = PrivateMedia::disk();

        if (Storage::disk($disk)->exists($path)) {
            return Storage::disk($disk)->response($path, null, [
                'Cache-Control' => 'private, no-store',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        }

        // Temporary compatibility for deployments that have not yet run the
        // private-media migration. Rollout must not complete in this state.
        abort_unless(Storage::disk('public')->exists($path), 404);

        return Storage::disk('public')->response($path, null, [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}

<?php

namespace App\Console\Commands;

use App\Models\EndOfDayRecord;
use App\Models\Member;
use App\Models\User;
use App\Support\PrivateMedia;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class MigratePrivateMedia extends Command
{
    protected $signature = 'hub:migrate-private-media
        {--source=public : Filesystem disk containing the existing files}
        {--target= : Destination disk; defaults to FILESYSTEM_DISK}
        {--commit : Move files and update database paths}';

    protected $description = 'Move client/staff media from public storage to permission-checked private storage';

    public function handle(): int
    {
        $commit = (bool) $this->option('commit');
        $sourceDisk = (string) $this->option('source');
        $targetDisk = (string) ($this->option('target') ?: PrivateMedia::disk());

        if ($sourceDisk === $targetDisk) {
            $this->error('Source and target disks must be different.');

            return self::FAILURE;
        }

        foreach ([$sourceDisk, $targetDisk] as $disk) {
            if (! config("filesystems.disks.{$disk}")) {
                $this->error("Filesystem disk [{$disk}] is not configured.");

                return self::FAILURE;
            }
        }

        $moved = 0;
        $missing = 0;

        $move = function (?string $storedPath, string $requiredPrefix) use ($commit, $sourceDisk, $targetDisk, &$moved, &$missing): ?string {
            $path = PrivateMedia::path($storedPath);
            if (! $path) {
                return null;
            }
            if (! str_starts_with($path, $requiredPrefix)) {
                $this->error("Unexpected media path outside {$requiredPrefix}: {$path}");
                $missing++;

                return $storedPath;
            }

            if (Storage::disk($targetDisk)->exists($path)) {
                if (Storage::disk($sourceDisk)->exists($path)) {
                    $this->line(($commit ? 'Removing verified source duplicate' : 'Would remove source duplicate')." {$path}");
                    if ($commit) {
                        Storage::disk($sourceDisk)->delete($path);
                    }
                    $moved++;
                }

                return $path;
            }

            if (! Storage::disk($sourceDisk)->exists($path)) {
                $this->error("Missing media file: {$path}");
                $missing++;

                return $storedPath;
            }

            $this->line(($commit ? 'Moving' : 'Would move')." {$path}");
            if ($commit) {
                $stream = Storage::disk($sourceDisk)->readStream($path);
                if (! is_resource($stream)) {
                    throw new \RuntimeException("Could not read source media {$path}");
                }
                try {
                    Storage::disk($targetDisk)->writeStream($path, $stream);
                } finally {
                    fclose($stream);
                }
                if (! Storage::disk($targetDisk)->exists($path)) {
                    throw new \RuntimeException("Private copy verification failed for {$path}");
                }
                Storage::disk($sourceDisk)->delete($path);
            }
            $moved++;

            return $path;
        };

        Member::query()->whereNotNull('photo_path')->eachById(function (Member $member) use ($move, $commit) {
            $path = $move($member->getRawOriginal('photo_path'), 'member-photos/');
            if ($commit && $path) {
                $member->forceFill(['photo_path' => $path])->saveQuietly();
            }
        });

        User::withTrashed()->whereNotNull('photo_path')->eachById(function (User $user) use ($move, $commit) {
            $path = $move($user->getRawOriginal('photo_path'), 'staff-photos/');
            if ($commit && $path) {
                $user->forceFill(['photo_path' => $path])->saveQuietly();
            }
        });

        EndOfDayRecord::query()->whereNotNull('photos')->eachById(function (EndOfDayRecord $record) use ($move, $commit) {
            $paths = collect($record->photos ?? [])
                ->map(fn ($path) => $move($path, 'end-of-day-photos/'))
                ->filter()->values()->all();
            if ($commit) {
                $record->forceFill(['photos' => $paths])->saveQuietly();
            }
        });

        $this->newLine();
        $this->info(($commit ? 'Moved' : 'Dry run found')." {$moved} media file(s); {$missing} missing file(s).");

        if (! $commit) {
            $this->warn('No changes made. Take and verify a backup, then rerun with --commit.');
        }

        return $missing === 0 ? self::SUCCESS : self::FAILURE;
    }
}

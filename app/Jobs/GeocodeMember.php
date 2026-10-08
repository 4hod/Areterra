<?php

namespace App\Jobs;

use App\Models\Member;
use App\Support\Geocoder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RuntimeException;

class GeocodeMember implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $uniqueFor = 3600;

    /** @var array<int, int> */
    public array $backoff = [60, 300, 900];

    public function __construct(public int $memberId, public string $address) {}

    public function uniqueId(): string
    {
        return $this->memberId.':'.hash('sha256', $this->address);
    }

    public function handle(): void
    {
        $member = Member::find($this->memberId);
        if (! $member || $member->geocoded_at) {
            return;
        }

        $currentAddress = collect([$member->address_line1, $member->address_line2, $member->town, $member->postcode])
            ->filter()->implode(', ');
        if ($currentAddress !== $this->address) {
            return;
        }

        $coordinates = Geocoder::resolve($this->address);
        if (! $coordinates) {
            throw new RuntimeException('Address could not be geocoded.');
        }

        $member->update([
            'lat' => $coordinates['lat'],
            'lng' => $coordinates['lng'],
            'geocoded_at' => now(),
        ]);
    }
}

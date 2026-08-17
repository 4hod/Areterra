<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

// Free geocoding via OpenStreetMap Nominatim — no API key, no cost.
// Their usage policy requires a real User-Agent and no more than ~1 request/
// second; since we cache every result on the member record (geocoded_at),
// each address is only ever looked up once, never repeated.
class Geocoder
{
    public static function resolve(string $address): ?array
    {
        try {
            $response = Http::withHeaders([
                'User-Agent' => 'AreterraHub/1.0 (team@areterra.co.uk)',
            ])->timeout(5)->get('https://nominatim.openstreetmap.org/search', [
                'q' => $address,
                'countrycodes' => 'gb',
                'format' => 'json',
                'limit' => 1,
            ]);

            $result = $response->json(0);

            if (! $result) {
                return null;
            }

            return [
                'lat' => (float) $result['lat'],
                'lng' => (float) $result['lon'],
            ];
        } catch (\Throwable $e) {
            Log::warning('Geocoding failed', ['address' => $address, 'error' => $e->getMessage()]);

            return null;
        }
    }
}

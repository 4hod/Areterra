<?php

namespace App\Http\Controllers;

use App\Services\WordPressSyncService;
use Throwable;

class WordPressSyncController extends Controller
{
    public function test(WordPressSyncService $sync)
    {
        try {
            $result = $sync->testConnection();

            return back()->with('success', "WordPress bulk sync connected. {$result['members_available']} member records are available.");
        } catch (Throwable $e) {
            report($e);

            return back()->with('error', 'WordPress connection failed: '.$e->getMessage());
        }
    }

    public function sync(WordPressSyncService $sync)
    {
        try {
            $result = $sync->syncMembers();

            return back()->with('success', sprintf(
                'WordPress sync complete: %d members received, %d created, %d updated, %d staff notes added, and %d end-of-day records added.',
                $result['members_received'],
                $result['members_created'],
                $result['members_updated'],
                $result['notes_created'],
                $result['sessions_created'],
            ));
        } catch (Throwable $e) {
            report($e);

            return back()->with('error', 'WordPress sync failed: '.$e->getMessage());
        }
    }
}

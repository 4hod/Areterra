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

            return back()->with('success', "WordPress connected. {$result['members_available']} member records are available.");
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
                'WordPress sync complete: %d members received, %d created, %d updated, %d notes added and %d notes updated.',
                $result['members_received'],
                $result['members_created'],
                $result['members_updated'],
                $result['notes_created'],
                $result['notes_updated'],
            ));
        } catch (Throwable $e) {
            report($e);

            return back()->with('error', 'WordPress sync failed: '.$e->getMessage());
        }
    }
}

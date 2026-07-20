<?php

namespace App\Http\Controllers;

use App\Models\Member;
use Illuminate\Http\Request;

// ABC observations and body maps, recorded against a member's profile tabs.
class CareController extends Controller
{
    public function storeAbc(Request $request, Member $member)
    {
        $member->abcObservations()->create([
            ...$request->validate([
                'observed_at' => ['required', 'date'],
                'antecedent' => ['nullable', 'string'],
                'behaviour' => ['required', 'string'],
                'consequence' => ['nullable', 'string'],
                'wellbeing_score' => ['nullable', 'integer', 'between:1,5'],
                'concern' => ['boolean'],
            ]),
            'user_id' => $request->user()->id,
        ]);

        return back()->with('success', 'ABC observation recorded.');
    }

    public function storeBodyMap(Request $request, Member $member)
    {
        $member->bodyMaps()->create([
            ...$request->validate([
                'markers' => ['required', 'array', 'min:1'],
                'markers.*.view' => ['required', 'in:front,back'],
                'markers.*.x' => ['required', 'numeric', 'between:0,100'],
                'markers.*.y' => ['required', 'numeric', 'between:0,100'],
                'markers.*.note' => ['nullable', 'string', 'max:500'],
                'notes' => ['nullable', 'string'],
            ]),
            'recorded_at' => now(),
            'user_id' => $request->user()->id,
        ]);

        return back()->with('success', 'Body map recorded.');
    }
}

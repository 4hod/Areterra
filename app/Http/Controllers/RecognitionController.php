<?php

namespace App\Http\Controllers;

use App\Models\Recognition;
use Illuminate\Http\Request;
use Inertia\Inertia;

class RecognitionController extends Controller
{
    public function index(Request $request)
    {
        return Inertia::render('Recognition', [
            'recognitions' => Recognition::with(['author:id,name', 'likes'])
                ->orderByDesc('created_at')
                ->limit(30)
                ->get()
                ->map(fn ($r) => [
                    'id' => $r->id,
                    'author' => $r->author->name,
                    'recipient' => $r->recipient,
                    'message' => $r->message,
                    'created_at' => $r->created_at->toDateTimeString(),
                    'likes' => $r->likes->count(),
                    'liked_by_me' => $r->likes->contains('user_id', $request->user()->id),
                ]),
        ]);
    }

    public function store(Request $request)
    {
        Recognition::create([
            ...$request->validate([
                'recipient' => ['required', 'string', 'max:100'],
                'message' => ['required', 'string', 'max:1000'],
            ]),
            'user_id' => $request->user()->id,
        ]);

        return back()->with('success', 'Shoutout posted! 🎉');
    }

    public function toggleLike(Request $request, Recognition $recognition)
    {
        $existing = $recognition->likes()->where('user_id', $request->user()->id)->first();

        $existing ? $existing->delete() : $recognition->likes()->create(['user_id' => $request->user()->id]);

        return back();
    }
}

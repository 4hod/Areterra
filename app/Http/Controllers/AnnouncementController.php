<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\User;
use App\Notifications\AnnouncementPosted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;

class AnnouncementController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        return Inertia::render('Announcements', [
            'announcements' => Announcement::with('author:id,name')
                ->orderByDesc('created_at')
                ->limit(30)
                ->get()
                ->map(fn ($a) => [
                    'id' => $a->id,
                    'title' => $a->title,
                    'body' => $a->body,
                    'author' => $a->author->name,
                    'created_at' => $a->created_at->toDateTimeString(),
                    'read' => $a->readBy($user),
                ]),
            'canPost' => Gate::allows('post_announcements'),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string'],
        ]);

        $announcement = Announcement::create([...$data, 'user_id' => $request->user()->id]);

        Notification::send(
            User::where('id', '!=', $request->user()->id)->get(),
            new AnnouncementPosted($announcement),
        );

        return back()->with('success', 'Announcement posted.');
    }

    public function markRead(Request $request, Announcement $announcement)
    {
        $announcement->reads()->firstOrCreate(
            ['user_id' => $request->user()->id],
            ['read_at' => now()],
        );

        return back();
    }
}

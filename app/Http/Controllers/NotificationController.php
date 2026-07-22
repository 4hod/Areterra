<?php

namespace App\Http\Controllers;

use App\Models\NotificationPref;
use Illuminate\Http\Request;
use Inertia\Inertia;

class NotificationController extends Controller
{
    public function edit(Request $request)
    {
        $pref = $request->user()->pref();

        return Inertia::render('NotificationPrefs', [
            'prefs' => [
                'push_enabled' => $pref->push_enabled,
                'email_enabled' => $pref->email_enabled,
                'categories' => collect(NotificationPref::CATEGORIES)
                    ->mapWithKeys(fn ($c) => [$c => $pref->categoryEnabled($c)]),
            ],
            'vapidPublicKey' => config('webpush.vapid.public_key'),
            'hasSubscription' => $request->user()->pushSubscriptions()->exists(),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'push_enabled' => ['required', 'boolean'],
            'email_enabled' => ['required', 'boolean'],
            'categories' => ['required', 'array'],
            'categories.*' => ['boolean'],
        ]);

        $request->user()->pref()->update([
            'push_enabled' => $data['push_enabled'],
            'email_enabled' => $data['email_enabled'],
            'categories' => collect($data['categories'])
                ->only(NotificationPref::CATEGORIES)
                ->all(),
        ]);

        return back()->with('success', 'Notification preferences saved.');
    }

    public function subscribe(Request $request)
    {
        $data = $request->validate([
            'endpoint' => ['required', 'string', 'max:500'],
            'keys.p256dh' => ['required', 'string'],
            'keys.auth' => ['required', 'string'],
        ]);

        $request->user()->updatePushSubscription(
            $data['endpoint'],
            $data['keys']['p256dh'],
            $data['keys']['auth'],
            'aesgcm',
        );

        return response()->json(['success' => true]);
    }

    public function test(Request $request)
    {
        $request->user()->notify(new \App\Notifications\ConcernRaised(
            'Test notification',
            'If you can read this, notifications are working.',
            '/notifications',
        ));

        return back()->with('success', 'Test notification sent.');
    }

    public function recent(Request $request)
    {
        return response()->json([
            'notifications' => $request->user()->notifications()->latest()->limit(6)->get()
                ->map(fn ($n) => [
                    'id' => $n->id,
                    'title' => $n->data['title'] ?? '',
                    'body' => $n->data['body'] ?? '',
                    'url' => $n->data['url'] ?? '/',
                    'read' => $n->read_at !== null,
                    'created_at' => $n->created_at->diffForHumans(),
                ]),
        ]);
    }

    public function markRead(Request $request, string $notification)
    {
        $request->user()->notifications()->where('id', $notification)->first()?->markAsRead();

        return back();
    }
}

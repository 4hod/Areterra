<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $search = trim($request->string('search')->toString());
        $entries = AuditLog::with('user:id,name')
            ->when($request->filled('subject'), fn ($q) => $q->where('subject_type', $request->string('subject')))
            ->when($request->filled('actor'), fn ($q) => $q->where('user_id', $request->integer('actor')))
            ->when($request->filled('action'), fn ($q) => $q->where('action', $request->string('action')))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('created_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('created_at', '<=', $request->date('to')))
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($nested) use ($search) {
                    $nested->where('subject_type', 'like', "%{$search}%")
                        ->orWhere('action', 'like', "%{$search}%")
                        ->orWhere('changes', 'like', "%{$search}%")
                        ->orWhereHas('user', fn ($user) => $user->where('name', 'like', "%{$search}%"));
                    if (ctype_digit($search)) {
                        $nested->orWhere('subject_id', (int) $search);
                    }
                });
            })
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();

        return Inertia::render('AuditLog', [
            'entries' => $entries->through(fn ($e) => [
                'id' => $e->id,
                'user' => $e->user?->name ?? 'System',
                'action' => $e->action,
                'subject_type' => $e->subject_type,
                'subject_id' => $e->subject_id,
                'changes' => $e->changes,
                'created_at' => $e->created_at->toDateTimeString(),
            ]),
            'subjects' => AuditLog::distinct()->orderBy('subject_type')->pluck('subject_type'),
            'actors' => \App\Models\User::whereIn('id', AuditLog::whereNotNull('user_id')->distinct()->pluck('user_id'))->orderBy('name')->get(['id', 'name']),
            'filters' => $request->only(['search', 'subject', 'actor', 'action', 'from', 'to']),
        ]);
    }
}

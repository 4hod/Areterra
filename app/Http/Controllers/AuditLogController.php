<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $entries = AuditLog::with('user:id,name')
            ->when($request->filled('subject'), fn ($q) => $q->where('subject_type', $request->string('subject')))
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
            'filter' => $request->string('subject')->toString() ?: null,
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class DocumentController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $staffCount = User::count();

        return Inertia::render('Documents', [
            'documents' => Document::with(['uploader:id,name', 'reads'])
                ->orderByDesc('created_at')
                ->get()
                ->map(fn ($d) => [
                    'id' => $d->id,
                    'title' => $d->title,
                    'category' => $d->category,
                    'original_name' => $d->original_name,
                    'requires_read' => $d->requires_read,
                    'uploaded_by' => $d->uploader->name,
                    'created_at' => $d->created_at->toDateString(),
                    'read_by_me' => $d->reads->contains('user_id', $user->id),
                    'read_count' => $d->reads->count(),
                    'staff_count' => $staffCount,
                ]),
            'canUpload' => Gate::allows('upload_documents'),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'category' => ['nullable', 'string', 'max:100'],
            'file' => ['required', 'file', 'max:20480', 'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,jpg,jpeg,png,txt'], // 20 MB
            'requires_read' => ['boolean'],
        ]);

        $path = $request->file('file')->store('documents');

        Document::create([
            'title' => $data['title'],
            'category' => $data['category'] ?? null,
            'file_path' => $path,
            'original_name' => $request->file('file')->getClientOriginalName(),
            'requires_read' => $data['requires_read'] ?? false,
            'uploaded_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Document uploaded.');
    }

    public function download(Document $document)
    {
        return Storage::download($document->file_path, $document->original_name);
    }

    public function markRead(Request $request, Document $document)
    {
        $document->reads()->firstOrCreate(
            ['user_id' => $request->user()->id],
            ['read_at' => now()],
        );

        return back()->with('success', 'Marked as read.');
    }
}

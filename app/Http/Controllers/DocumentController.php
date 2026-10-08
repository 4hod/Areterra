<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\User;
use App\Support\RecordLinks;
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
        $context = RecordLinks::resolve($request->query('about'), $request->query('id'));

        return Inertia::render('Documents', [
            'documents' => Document::with(['uploader:id,name', 'reads', 'attachable'])
                ->when($context, fn ($query) => $query->whereMorphedTo('attachable', $context))
                ->orderByDesc('created_at')
                ->get()
                ->filter(fn ($document) => ! $document->attachable || RecordLinks::isVisible($document->attachable))
                ->map(fn ($d) => [
                    'id' => $d->id,
                    'title' => $d->title,
                    'category' => $d->category,
                    'original_name' => $d->original_name,
                    'requires_read' => $d->requires_read,
                    'expires_at' => $d->expires_at?->toDateString(),
                    'uploaded_by' => $d->uploader->name,
                    'created_at' => $d->created_at->toDateString(),
                    'read_by_me' => $d->reads->contains('user_id', $user->id),
                    'read_count' => $d->reads->count(),
                    'staff_count' => $staffCount,
                    'about' => $d->attachable ? RecordLinks::metadataIfVisible($d->attachable) : null,
                ]),
            'canUpload' => Gate::allows('upload_documents'),
            'relatedOptions' => RecordLinks::options(['member', 'animal', 'vehicle', 'activity', 'grant', 'incident']),
            'context' => $context ? RecordLinks::metadata($context) : null,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'category' => ['nullable', 'string', 'max:100'],
            'file' => ['required', 'file', 'max:20480', 'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,jpg,jpeg,png,txt'], // 20 MB
            'requires_read' => ['boolean'],
            'expires_at' => ['nullable', 'date'],
            'related_type' => ['nullable', 'required_with:related_id', 'in:member,animal,vehicle,activity,grant,incident'],
            'related_id' => ['nullable', 'required_with:related_type', 'integer'],
        ]);

        $related = RecordLinks::resolve($data['related_type'] ?? null, $data['related_id'] ?? null);

        $path = $request->file('file')->store('documents');

        $document = new Document([
            'title' => $data['title'],
            'category' => $data['category'] ?? null,
            'file_path' => $path,
            'original_name' => $request->file('file')->getClientOriginalName(),
            'requires_read' => $data['requires_read'] ?? false,
            'expires_at' => $data['expires_at'] ?? null,
            'uploaded_by' => $request->user()->id,
        ]);
        if ($related) {
            $document->attachable()->associate($related);
        }
        $document->save();

        return back()->with('success', 'Document uploaded.');
    }

    public function download(Document $document)
    {
        $this->authorizeDocument($document);

        return Storage::download($document->file_path, $document->original_name);
    }

    public function markRead(Request $request, Document $document)
    {
        $this->authorizeDocument($document);

        $document->reads()->firstOrCreate(
            ['user_id' => $request->user()->id],
            ['read_at' => now()],
        );

        return back()->with('success', 'Marked as read.');
    }

    public function destroy(Document $document)
    {
        $document->delete();

        return back()->with('success', 'Document removed. The stored file is retained for recovery and audit purposes.');
    }

    private function authorizeDocument(Document $document): void
    {
        $document->loadMissing('attachable');
        abort_if($document->attachable && ! RecordLinks::isVisible($document->attachable), 403);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\Animal;
use App\Models\Document;
use App\Models\Member;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class SearchController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        if (mb_strlen($q) < 2) {
            return response()->json(['results' => []]);
        }

        $results = [];

        if (Gate::allows('view_members')) {
            $results['Members'] = Member::query()
                ->where(fn ($w) => $w->where('first_name', 'like', "%{$q}%")
                    ->orWhere('last_name', 'like', "%{$q}%")
                    ->orWhere('preferred_name', 'like', "%{$q}%"))
                ->limit(5)->get()
                ->map(fn ($m) => ['title' => $m->displayName(), 'url' => "/members/{$m->id}"]);
        }

        if (Gate::allows('view_animals')) {
            $results['Animals'] = Animal::query()
                ->where(fn ($w) => $w->where('name', 'like', "%{$q}%")->orWhere('species', 'like', "%{$q}%"))
                ->limit(5)->get()
                ->map(fn ($a) => ['title' => "{$a->name} ({$a->species})", 'url' => "/animals/{$a->id}"]);
        }

        $results['Announcements'] = Announcement::where('title', 'like', "%{$q}%")->limit(5)->get()
            ->map(fn ($a) => ['title' => $a->title, 'url' => '/announcements']);

        if (Gate::allows('view_documents')) {
            $results['Documents'] = Document::where('title', 'like', "%{$q}%")->limit(5)->get()
                ->map(fn ($d) => ['title' => $d->title, 'url' => '/documents']);
        }

        // Grants, invoices, tasks, incidents and compliance, added on top of the
        // original four. GlobalSearch applies the same capability checks, so a
        // volunteer still never sees finance or safeguarding hits.
        foreach (\App\Support\GlobalSearch::for($q, $request->user()) as $group) {
            if ($group['type'] === 'Members' || $group['type'] === 'Animals' || $group['type'] === 'Documents') {
                continue; // already covered above, with better titles and links
            }

            $results[$group['type']] = collect($group['results'])
                ->map(fn ($hit) => [
                    'title' => $hit['meta'] ? "{$hit['title']} — {$hit['meta']}" : $hit['title'],
                    'url' => $hit['url'] ?? '#',
                ]);
        }

        // Drop empty groups so the frontend doesn't render blank headings.
        $results = collect($results)->filter(fn ($group) => $group->isNotEmpty());

        return response()->json(['results' => $results]);
    }
}

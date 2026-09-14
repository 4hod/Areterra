<?php

namespace App\Http\Controllers;

use App\Models\DirectoryContact;
use App\Models\StaffRosterMember;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class DirectoryController extends Controller
{
    public function index()
    {
        $users = User::orderBy('name')->get()->map(fn ($u) => [
            'id' => $u->id,
            'name' => $u->name,
            'job_title' => $u->job_title ?? ucfirst(str_replace('_', ' ', $u->role)),
            'email' => $u->email,
            'phone' => $u->phone,
            'bio' => $u->bio,
            'photo_path' => $u->photo_path,
            'has_account' => true,
        ]);

        $userNames = $users->pluck('name')->map(fn ($n) => mb_strtolower($n));

        $roster = StaffRosterMember::where('active', true)->orderBy('name')->get()
            ->reject(fn ($s) => $userNames->contains(mb_strtolower($s->name)))
            ->map(fn ($s) => [
                'name' => $s->name,
                'job_title' => $s->job_title,
                'email' => $s->email,
                'phone' => $s->phone,
                'has_account' => false,
            ]);

        return Inertia::render('Directory', [
            'staff' => $users->concat($roster)->sortBy('name')->values(),
            'contacts' => DirectoryContact::orderBy('category')->orderBy('name')->get(),
            'canManage' => Gate::allows('manage_directory'),
        ]);
    }

    public function store(Request $request)
    {
        DirectoryContact::create($request->validate([
            'name' => ['required', 'string', 'max:120'],
            'category' => ['required', 'string', 'max:60'],
            'organisation' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]));

        return back()->with('success', 'Contact added.');
    }

    public function destroy(DirectoryContact $contact)
    {
        $contact->delete();

        return back()->with('success', 'Contact removed.');
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\StaffRosterMember;
use App\Models\User;
use Inertia\Inertia;

class DirectoryController extends Controller
{
    public function index()
    {
        $users = User::orderBy('name')->get()->map(fn ($u) => [
            'name' => $u->name,
            'job_title' => $u->job_title ?? ucfirst(str_replace('_', ' ', $u->role)),
            'email' => $u->email,
            'phone' => null,
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
        ]);
    }
}

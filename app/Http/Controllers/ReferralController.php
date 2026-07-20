<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Models\Referral;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ReferralController extends Controller
{
    // Public-facing referral form — no auth.
    public function create()
    {
        return Inertia::render('Refer');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'referrer_name' => ['required', 'string', 'max:100'],
            'referrer_email' => ['nullable', 'email', 'max:255'],
            'referrer_phone' => ['nullable', 'string', 'max:30'],
            'organisation' => ['nullable', 'string', 'max:200'],
            'person_name' => ['required', 'string', 'max:100'],
            'details' => ['nullable', 'string', 'max:5000'],
            'website' => ['prohibited'], // honeypot
        ]);

        Referral::create(collect($data)->except('website')->all());

        return back()->with('success', 'Thank you — your referral has been received. We\'ll be in touch soon.');
    }

    // Hub-side management.
    public function index()
    {
        return Inertia::render('Referrals', [
            'referrals' => Referral::with('reviewer:id,name')->orderByDesc('created_at')->get()
                ->map(fn ($r) => [
                    'id' => $r->id,
                    'referrer_name' => $r->referrer_name,
                    'referrer_email' => $r->referrer_email,
                    'referrer_phone' => $r->referrer_phone,
                    'organisation' => $r->organisation,
                    'person_name' => $r->person_name,
                    'details' => $r->details,
                    'status' => $r->status,
                    'reviewer' => $r->reviewer?->name,
                    'created_at' => $r->created_at->toDateString(),
                    'pending_days' => $r->status === 'pending' ? (int) $r->created_at->diffInDays(now()) : null,
                ]),
        ]);
    }

    public function review(Request $request, Referral $referral)
    {
        $data = $request->validate([
            'status' => ['required', 'in:accepted,declined'],
        ]);

        $referral->update([
            ...$data,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        // Accepting creates an inactive member profile ready to complete.
        if ($data['status'] === 'accepted') {
            $parts = preg_split('/\s+/', trim($referral->person_name), 2);
            $member = Member::firstOrCreate(
                ['first_name' => $parts[0], 'last_name' => $parts[1] ?? ''],
                ['status' => 'inactive'],
            );
            $member->settings()->firstOrCreate([]);

            return redirect()->route('members.show', $member)
                ->with('success', "Referral accepted — complete {$member->displayName()}'s profile.");
        }

        return back()->with('success', 'Referral declined.');
    }
}

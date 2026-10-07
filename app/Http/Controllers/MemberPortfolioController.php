<?php

namespace App\Http\Controllers;

use App\Models\Certificate;
use App\Models\Member;
use App\Models\PortfolioItem;
use App\Support\OutcomeEvidenceSummary;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;

class MemberPortfolioController extends Controller
{
    public function show(Request $request, Member $member)
    {
        $months = min(12, max(1, (int) $request->integer('months', 3)));
        $to = today();
        $from = $to->copy()->subMonths($months)->startOfDay();

        $member->load([
            'portfolioItems' => fn ($q) => $q->with('certificate')->latest('achieved_on')->latest(),
            'certificates' => fn ($q) => $q->with('issuer:id,name')->latest('issued_on'),
            'goals' => fn ($q) => $q->where('status', 'achieved')->latest('achieved_at'),
        ]);

        return Inertia::render('Members/Portfolio', [
            'member' => [
                'id' => $member->id,
                'name' => $member->displayName(),
                'photo_url' => $member->photo_path ? route('media.members.photo', $member) : null,
                'interests' => $member->interests,
                'support_needs' => $member->support_needs,
            ],
            'months' => $months,
            'evidence' => OutcomeEvidenceSummary::for($member, $from, $to),
            'items' => $member->portfolioItems->map(fn ($item) => [
                'id' => $item->id,
                'type' => $item->type,
                'title' => $item->title,
                'description' => $item->description,
                'date' => $item->achieved_on?->toDateString(),
                'visible_to_member' => $item->visible_to_member,
                'certificate_id' => $item->certificate?->id,
            ]),
            'certificates' => $member->certificates->map(fn ($certificate) => [
                'id' => $certificate->id,
                'title' => $certificate->title,
                'description' => $certificate->description,
                'issued_on' => $certificate->issued_on->toDateString(),
                'number' => $certificate->certificate_number,
                'issuer' => $certificate->issuer->name,
            ]),
            'achievedGoals' => $member->goals->map(fn ($goal) => [
                'title' => $goal->title,
                'description' => $goal->description,
                'date' => $goal->achieved_at?->toDateString(),
            ]),
            'canEdit' => Gate::allows('edit_members') && $member->status !== 'archived',
        ]);
    }

    public function storeItem(Request $request, Member $member)
    {
        abort_if($member->status === 'archived', 422, 'Archived member records are read-only.');
        $member->portfolioItems()->create([
            ...$request->validate([
                'type' => ['required', 'in:achievement,project,choice'],
                'title' => ['required', 'string', 'max:200'],
                'description' => ['nullable', 'string', 'max:5000'],
                'achieved_on' => ['nullable', 'date'],
                'visible_to_member' => ['boolean'],
            ]),
            'created_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Portfolio item added.');
    }

    public function issueCertificate(Request $request, Member $member)
    {
        abort_if($member->status === 'archived', 422, 'Archived member records are read-only.');
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:1000'],
            'issued_on' => ['required', 'date'],
        ]);

        $item = $member->portfolioItems()->create([
            'type' => 'certificate',
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'achieved_on' => $data['issued_on'],
            'visible_to_member' => true,
            'created_by' => $request->user()->id,
        ]);

        $certificate = $member->certificates()->create([
            ...$data,
            'portfolio_item_id' => $item->id,
            'certificate_number' => 'ARE-'.date('Y').'-'.str_pad((string) $member->id, 4, '0', STR_PAD_LEFT).'-'.Str::upper(Str::random(6)),
            'issued_by' => $request->user()->id,
        ]);

        return redirect()->route('certificates.show', $certificate)->with('success', 'Certificate created.');
    }

    public function certificate(Certificate $certificate)
    {
        $certificate->load(['member', 'issuer:id,name']);

        return Inertia::render('Members/Certificate', [
            'certificate' => [
                'title' => $certificate->title,
                'description' => $certificate->description,
                'number' => $certificate->certificate_number,
                'issued_on' => $certificate->issued_on->toDateString(),
                'member' => $certificate->member->displayName(),
                'issuer' => $certificate->issuer->name,
                'member_id' => $certificate->member_id,
                'id' => $certificate->id,
            ],
            'canEdit' => Gate::allows('edit_members') && $certificate->member->status !== 'archived',
        ]);
    }

    public function destroyItem(Member $member, PortfolioItem $portfolioItem)
    {
        abort_if($member->status === 'archived', 422, 'Archived member records are read-only.');
        abort_unless($portfolioItem->member_id === $member->id, 404);
        abort_if($portfolioItem->certificate()->exists(), 422, 'Delete the linked certificate instead.');

        $portfolioItem->delete();

        return back()->with('success', 'Portfolio item removed.');
    }

    public function destroyCertificate(Member $member, Certificate $certificate)
    {
        abort_if($member->status === 'archived', 422, 'Archived member records are read-only.');
        abort_unless($certificate->member_id === $member->id, 404);

        DB::transaction(function () use ($certificate) {
            $item = $certificate->portfolioItem;
            $certificate->delete();
            $item?->delete();
        });

        return redirect()->route('members.portfolio', $member)->with('success', 'Certificate removed.');
    }
}

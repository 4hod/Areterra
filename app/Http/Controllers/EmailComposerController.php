<?php

namespace App\Http\Controllers;

use App\Models\EmailTemplate;
use App\Models\Member;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;

class EmailComposerController extends Controller
{
    public function index(Request $request)
    {
        $member = $request->filled('member') ? Member::findOrFail($request->integer('member')) : null;

        return Inertia::render('EmailComposer', [
            'members' => Member::active()->orderBy('first_name')->get()
                ->map(fn ($m) => ['id' => $m->id, 'name' => $m->displayName()]),
            'member' => $member ? ['id' => $member->id, 'name' => $member->displayName()] : null,
            'contacts' => $member
                ? $member->contacts()->get(['id', 'name', 'role', 'organisation', 'email'])
                : [],
            'templates' => EmailTemplate::orderBy('name')->get(['id', 'name', 'subject', 'body']),
        ]);
    }

    public function send(Request $request)
    {
        $data = $request->validate([
            'member_id' => ['required', 'exists:members,id'],
            'to_email' => ['required_unless:log_only,true', 'nullable', 'email'],
            'to_name' => ['nullable', 'string', 'max:100'],
            'organisation' => ['nullable', 'string', 'max:200'],
            'subject' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string'],
            'log_only' => ['boolean'],
            'save_template' => ['boolean'],
            'template_name' => ['required_if:save_template,true', 'nullable', 'string', 'max:100'],
        ]);

        $member = Member::findOrFail($data['member_id']);

        // Merge tags (SPEC.md §5).
        $subject = strtr($data['subject'], $this->mergeTags($member));
        $body = strtr($data['body'], $this->mergeTags($member));

        if (! ($data['log_only'] ?? false)) {
            Mail::to($data['to_email'], $data['to_name'] ?? null)
                ->send(new \App\Mail\BrandedEmail($subject, $body));
        }

        // Every email — sent or logged-only — lands in the comms timeline.
        $member->commsLog()->create([
            'type' => 'email',
            'direction' => 'outbound',
            'subject' => $subject,
            'summary' => $body,
            'contact_name' => $data['to_name'] ?? $data['to_email'] ?? null,
            'organisation' => $data['organisation'] ?? null,
            'date' => today(),
            'user_id' => $request->user()->id,
        ]);

        if ($data['save_template'] ?? false) {
            EmailTemplate::create([
                'name' => $data['template_name'],
                'subject' => $data['subject'],
                'body' => $data['body'],
                'created_by' => $request->user()->id,
            ]);
        }

        return back()->with('success', ($data['log_only'] ?? false) ? 'Email logged.' : 'Email sent and logged.');
    }

    public function destroyTemplate(EmailTemplate $template)
    {
        $template->delete();

        return back()->with('success', 'Template deleted.');
    }

    private function mergeTags(Member $member): array
    {
        return [
            '{{member_name}}' => $member->displayName(),
            '{{today}}' => today()->format('j F Y'),
        ];
    }
}

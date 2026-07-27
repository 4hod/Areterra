import { Head, Link, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import AppShell from '../../components/AppShell';
import Card from '../../components/Card';
import Modal from '../../components/Modal';
import StatusPill from '../../components/StatusPill';
import ModuleHero from '../../components/ModuleHero';

interface PolicyRow {
    id: number;
    title: string;
    category: string;
    version: string;
    review_date: string | null;
    status: string;
    author: string;
    approved: boolean;
    updated_at: string;
}

const ORG = 'Areterra (registered charity No. 1196211), Little Croft, Fenn Green, WV15 6JA';

// Pre-built starting points, pre-filled with Areterra details (SPEC.md §17).
const TEMPLATES: Record<string, string> = {
    'Medication Administration': `<h2>Purpose</h2><p>This policy sets out how ${ORG} manages the safe administration of medication for members attending our day opportunity services.</p><h2>Scope</h2><p>Applies to all staff and volunteers supporting members with medication.</p><h2>Policy</h2><p>Medication is only administered with written consent, recorded on the member's medication record, by trained staff. All administration is double-checked and logged in the Hub.</p><h2>Review</h2><p>Reviewed annually by the manager.</p>`,
    'Safeguarding Adults': `<h2>Statement</h2><p>${ORG} is committed to safeguarding adults at risk. Every member has the right to live free from abuse and neglect.</p><h2>Responsibilities</h2><p>All staff and volunteers must report concerns immediately to the Safeguarding Lead. Concerns are recorded in the Hub's safeguarding module and referred to the local authority where appropriate.</p><h2>Contact</h2><p>Safeguarding Lead: [name]. Local authority safeguarding team: [contact].</p>`,
    'GDPR / Data Protection': `<h2>Purpose</h2><p>${ORG} processes personal and special-category data about members, staff and volunteers. This policy sets out how we comply with UK GDPR and the Data Protection Act 2018.</p><h2>Principles</h2><p>Data is processed lawfully, minimised, kept accurate, retained only as long as needed, and held securely with encryption at rest for sensitive fields.</p><h2>Rights</h2><p>Subject access requests are handled within one month via the Hub's SAR export.</p>`,
    'Health & Safety': `<h2>Statement</h2><p>${ORG} will provide a safe environment for members, staff, volunteers and visitors across our animal care, horticulture and practical skills activities.</p><h2>Arrangements</h2><p>Risk assessments are maintained in the Hub and reviewed regularly. Incidents are reported and investigated. Animal handling follows species-specific guidance.</p>`,
    'Confidentiality': `<h2>Purpose</h2><p>Staff and volunteers at ${ORG} have access to sensitive information about members. This policy sets out expectations for handling it.</p><h2>Policy</h2><p>Information is shared only on a need-to-know basis, never discussed outside work, and never posted on social media. Breaches are treated as disciplinary matters and, where required, reported to the ICO.</p>`,
};

export default function Index({ policies, canManage }: { policies: PolicyRow[]; canManage: boolean }) {
    const [creating, setCreating] = useState(false);
    const { data, setData, post, processing } = useForm({
        title: '',
        body: '',
        version: '1.0',
        review_date: '',
        status: 'draft',
    });

    function applyTemplate(name: string) {
        setData((d) => ({ ...d, title: name, body: TEMPLATES[name] }));
    }

    function submit(e: FormEvent) {
        e.preventDefault();
        post('/policies');
    }

    return (
        <AppShell title="Policies">
            <Head title="Policies" />
            <ModuleHero eyebrow="Governance library" title="Policies" description="Keep current guidance easy to find, review and acknowledge." icon="📜" tone="amber" />

            {canManage && (
                <button onClick={() => setCreating(true)} className="rounded-full bg-brand text-white font-semibold text-sm px-5 py-2.5 mb-4">
                    + New policy
                </button>
            )}

            {policies.length === 0 && (
                <Card><p className="text-slate-500">No policies yet.</p></Card>
            )}

            <div className="space-y-4">
                {Object.entries(
                    policies.reduce<Record<string, PolicyRow[]>>((acc, p) => {
                        (acc[p.category] ??= []).push(p);
                        return acc;
                    }, {}),
                ).map(([category, group]) => (
                    <div key={category}>
                        <div className="text-xs font-bold text-slate-400 uppercase tracking-wide mb-1.5">{category}</div>
                        <div className="space-y-2">
                            {group.map((p) => (
                                <Link key={p.id} href={`/policies/${p.id}`} className="block">
                                    <Card>
                                        <div className="flex items-center justify-between gap-2">
                                            <div>
                                                <div className="font-bold text-brand-dark">
                                                    {p.title}
                                                    {p.approved && <span className="ml-2 text-xs text-emerald-600 font-bold">✓ approved</span>}
                                                </div>
                                                <div className="text-xs text-slate-400">
                                                    v{p.version} · updated {new Date(p.updated_at).toLocaleDateString('en-GB')}
                                                    {p.review_date && ` · review ${new Date(p.review_date).toLocaleDateString('en-GB')}`}
                                                </div>
                                            </div>
                                            <StatusPill
                                                status={p.status === 'active' ? 'active' : p.status === 'draft' ? 'inactive' : 'archived'}
                                                label={p.status}
                                            />
                                        </div>
                                    </Card>
                                </Link>
                            ))}
                        </div>
                    </div>
                ))}
            </div>

            <Modal open={creating} title="New policy" onClose={() => setCreating(false)}>
                <form onSubmit={submit} className="space-y-3">
                    <div>
                        <div className="text-sm font-medium mb-1">Start from a template</div>
                        <div className="flex flex-wrap gap-1.5">
                            {Object.keys(TEMPLATES).map((name) => (
                                <button
                                    key={name}
                                    type="button"
                                    onClick={() => applyTemplate(name)}
                                    className={`rounded-full text-xs font-semibold px-3 py-1.5 ${
                                        data.title === name ? 'bg-brand text-white' : 'bg-slate-100 text-slate-600'
                                    }`}
                                >
                                    {name}
                                </button>
                            ))}
                        </div>
                    </div>
                    <label className="block text-sm font-medium">
                        Title
                        <input value={data.title} onChange={(e) => setData('title', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-300 px-3" required />
                    </label>
                    <div className="grid grid-cols-2 gap-3">
                        <label className="block text-sm font-medium">
                            Version
                            <input value={data.version} onChange={(e) => setData('version', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-300 px-3" required />
                        </label>
                        <label className="block text-sm font-medium">
                            Review date
                            <input type="date" value={data.review_date} onChange={(e) => setData('review_date', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-300 px-3" />
                        </label>
                    </div>
                    <p className="text-xs text-slate-400">The content opens in the editor after creating.</p>
                    <button type="submit" disabled={processing || !data.body} className="w-full rounded-lg bg-brand text-white font-bold py-3 disabled:opacity-60">
                        {data.body ? 'Create policy' : 'Pick a template first'}
                    </button>
                </form>
            </Modal>
        </AppShell>
    );
}

import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import AppShell from '../components/AppShell';
import Card from '../components/Card';
import ModuleHero from '../components/ModuleHero';

interface Preview {
    kind: string;
    headers: string[];
    rows: Record<string, string | null>[];
    total: number;
    problems: string[];
    csv: string;
}

interface ArchiveReport {
    files: { name: string; rows: number; processed: number }[];
    source_rows: number;
    created: { end_of_day: number; attendance: number; transport: number };
    matched: { end_of_day: number; attendance: number; transport: number };
    merged: { end_of_day: number };
    conflicts: { attendance: number; transport: number };
    source_flags: { transport_issues: number };
    already_imported: number;
}

export default function Import() {
    const flash = usePage().props.flash as { import_preview?: Preview; archive_import_report?: ArchiveReport };
    const preview = flash.import_preview;
    const archiveReport = flash.archive_import_report;
    const [kind, setKind] = useState('members');
    const [file, setFile] = useState<File | null>(null);
    const [archive, setArchive] = useState<File | null>(null);
    const csrfToken = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';

    function upload() {
        if (!file) return;
        router.post('/import/preview', { kind, file }, { forceFormData: true });
    }

    function commit() {
        if (!preview) return;
        router.post('/import/commit', { kind: preview.kind, csv: preview.csv });
    }

    return (
        <AppShell title="Import">
            <Head title="Import" />
            <ModuleHero eyebrow="Data tools" title="Import" description="Bring existing information into the Hub safely and clearly." icon="⬆️" tone="slate" />

            <Card title="Legacy records archive" className="mb-4">
                <form action="/import/archive" method="post" encType="multipart/form-data" className="flex flex-wrap items-end gap-3">
                    <input type="hidden" name="_token" value={csrfToken} />
                    <label className="block text-sm font-medium flex-1 min-w-48">
                        ZIP containing Jotform Excel exports
                        <input type="file" name="file" accept=".zip,application/zip" onChange={(e) => setArchive(e.target.files?.[0] ?? null)} className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2.5 block" />
                    </label>
                    <button type="submit" disabled={!archive} className="rounded-full bg-brand text-white font-bold text-sm px-5 py-3 disabled:opacity-60">
                        Import archive
                    </button>
                </form>
                <p className="text-xs text-slate-500 mt-2">
                    Imports attendance, morning transport and end-of-shift history. Existing member/date records are matched, every source row is retained encrypted, and the whole import rolls back if a workbook or code is unrecognised.
                </p>
            </Card>

            {archiveReport && (
                <Card title={`Archive report — ${archiveReport.source_rows} source rows`} className="mb-4">
                    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 text-sm">
                        <div className="rounded-lg bg-emerald-50 p-3"><strong className="block text-emerald-900">Created</strong>{archiveReport.created.end_of_day} shift · {archiveReport.created.attendance} attendance · {archiveReport.created.transport} transport</div>
                        <div className="rounded-lg bg-sky-50 p-3"><strong className="block text-sky-900">Already matched</strong>{archiveReport.matched.end_of_day} shift · {archiveReport.matched.attendance} attendance · {archiveReport.matched.transport} transport</div>
                        <div className="rounded-lg bg-violet-50 p-3"><strong className="block text-violet-900">Notes merged</strong>{archiveReport.merged.end_of_day} end-of-shift entries</div>
                        <div className={`rounded-lg p-3 ${archiveReport.conflicts.attendance + archiveReport.conflicts.transport > 0 ? 'bg-amber-50' : 'bg-slate-50'}`}><strong className="block">Conflicts</strong>{archiveReport.conflicts.attendance} attendance · {archiveReport.conflicts.transport} transport</div>
                    </div>
                    <p className="text-sm text-slate-600 mt-3">{archiveReport.already_imported} source rows had already been imported and were left unchanged.</p>
                    {archiveReport.source_flags.transport_issues > 0 && (
                        <p className="text-sm text-amber-800 bg-amber-50 rounded-lg p-3 mt-3">
                            {archiveReport.source_flags.transport_issues} transport source row{archiveReport.source_flags.transport_issues === 1 ? '' : 's'} marked that an issue occurred. The original row is retained encrypted for review.
                        </p>
                    )}
                    <div className="overflow-x-auto mt-3">
                        <table className="w-full text-sm">
                            <thead><tr className="text-left text-xs text-slate-400 uppercase"><th className="py-1 pr-3">Workbook</th><th className="py-1">Rows accounted for</th></tr></thead>
                            <tbody>{archiveReport.files.map((item) => <tr key={item.name} className="border-t border-slate-100"><td className="py-1.5 pr-3">{item.name}</td><td className="py-1.5">{item.processed} / {item.rows}</td></tr>)}</tbody>
                        </table>
                    </div>
                </Card>
            )}

            <Card title="CSV upload" className="mb-4">
                <div className="flex flex-wrap items-end gap-3">
                    <label className="block text-sm font-medium">
                        What are you importing?
                        <select value={kind} onChange={(e) => setKind(e.target.value)} className="mt-1 rounded-lg border border-slate-300 px-3 bg-white block">
                            <option value="members">Members</option>
                            <option value="animals">Animals</option>
                        </select>
                    </label>
                    <label className="block text-sm font-medium flex-1 min-w-48">
                        CSV file
                        <input type="file" accept=".csv,.txt" onChange={(e) => setFile(e.target.files?.[0] ?? null)} className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2.5 block" />
                    </label>
                    <button onClick={upload} disabled={!file} className="rounded-full bg-brand text-white font-bold text-sm px-5 py-3 disabled:opacity-60">
                        Preview →
                    </button>
                </div>
                <p className="text-xs text-slate-400 mt-2">
                    Members: <code className="bg-slate-100 px-1 rounded">first_name,last_name,preferred_name,status,dob,phone,postcode</code> ·
                    Animals: <code className="bg-slate-100 px-1 rounded">name,species,breed,sex,status</code>.
                    Existing records are skipped, never overwritten.
                </p>
            </Card>

            {preview && (
                <Card title={`CSV preview — ${preview.total} row${preview.total === 1 ? '' : 's'} of ${preview.kind}`}>
                    {preview.problems.length > 0 && (
                        <div className="rounded-lg bg-amber-50 border border-amber-200 p-3 mb-3 text-sm text-amber-800">
                            {preview.problems.map((p, i) => (
                                <div key={i}>⚠ {p}</div>
                            ))}
                        </div>
                    )}
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="text-left text-xs text-slate-400 uppercase">
                                    {preview.headers.map((h) => (
                                        <th key={h} className="py-1 pr-3">{h}</th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {preview.rows.map((row, i) => (
                                    <tr key={i} className="border-t border-slate-100">
                                        {preview.headers.map((h) => (
                                            <td key={h} className="py-1.5 pr-3">{row[h]}</td>
                                        ))}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    <button onClick={commit} className="mt-4 w-full rounded-lg bg-brand text-white font-bold py-3">
                        ✓ Run import ({preview.total} rows)
                    </button>
                </Card>
            )}
        </AppShell>
    );
}

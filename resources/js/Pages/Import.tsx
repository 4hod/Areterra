import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import AppShell from '../components/AppShell';
import Card from '../components/Card';

interface Preview {
    kind: string;
    headers: string[];
    rows: Record<string, string | null>[];
    total: number;
    problems: string[];
    csv: string;
}

export default function Import() {
    const flash = usePage().props.flash as { import_preview?: Preview };
    const preview = flash.import_preview;
    const [kind, setKind] = useState('members');
    const [file, setFile] = useState<File | null>(null);

    function upload() {
        if (!file) return;
        router.post('/import/preview', { kind, file }, { forceFormData: true });
    }

    function commit() {
        if (!preview) return;
        router.post('/import/commit', { kind: preview.kind, csv: preview.csv });
    }

    return (
        <AppShell title="CSV Import">
            <Head title="Import" />

            <Card title="1 · Upload" className="mb-4">
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
                <Card title={`2 · Preview — ${preview.total} row${preview.total === 1 ? '' : 's'} of ${preview.kind}`}>
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

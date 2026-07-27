import { Head, router, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import AppShell from '../components/AppShell';
import Card from '../components/Card';
import Modal from '../components/Modal';
import { promptDialog } from '../utils/dialogs';
import ModuleHero from '../components/ModuleHero';

interface Defect {
    id: number;
    date: string;
    description: string;
    severity: string;
    reporter: string;
    resolved_at: string | null;
}

interface VehicleRow {
    id: number;
    registration: string;
    make_model: string | null;
    mot_due: string | null;
    service_due: string | null;
    mot_soon: boolean;
    active: boolean;
    open_defects: number;
    defects: Defect[];
}

const SEVERITY_STYLE: Record<string, string> = {
    minor: 'bg-slate-200 text-slate-600',
    serious: 'bg-amber-100 text-amber-800',
    vehicle_off_road: 'bg-red-100 text-red-800',
};

export default function Vehicles({ vehicles, canManage }: { vehicles: VehicleRow[]; canManage: boolean }) {
    const [reporting, setReporting] = useState<VehicleRow | null>(null);
    const { data, setData, post, processing, reset } = useForm({ description: '', severity: 'minor' });

    function submitDefect(e: FormEvent) {
        e.preventDefault();
        if (!reporting) return;
        post(`/vehicles/${reporting.id}/defects`, { onSuccess: () => { setReporting(null); reset(); } });
    }

    return (
        <AppShell title="Vehicles">
            <Head title="Vehicles" />
            <ModuleHero eyebrow="Fleet care" title="Vehicles" description="Keep vehicle checks, servicing and key information organised." icon="🚚" tone="amber" />

            <div className="space-y-3">
                {vehicles.map((v) => (
                    <Card key={v.id} className={v.open_defects > 0 ? 'border-l-4 border-l-status-amber' : ''}>
                        <div className="flex items-center justify-between gap-2">
                            <div>
                                <div className="font-extrabold text-lg text-brand-dark tracking-wide">
                                    🚐 {v.registration}
                                </div>
                                <div className="text-sm text-slate-500">{v.make_model}</div>
                                <div className="text-xs text-slate-400 mt-1">
                                    {v.mot_due && (
                                        <span className={v.mot_soon ? 'text-red-600 font-bold' : ''}>
                                            MOT {new Date(v.mot_due).toLocaleDateString('en-GB')}
                                        </span>
                                    )}
                                    {v.mot_due && v.service_due && ' · '}
                                    {v.service_due && `Service ${new Date(v.service_due).toLocaleDateString('en-GB')}`}
                                    {!v.mot_due && canManage && (
                                        <button
                                            onClick={async () => {
                                                const d = await promptDialog('MOT due date (YYYY-MM-DD):');
                                                if (d) router.put(`/vehicles/${v.id}`, { mot_due: d });
                                            }}
                                            className="text-brand font-bold"
                                        >
                                            Set MOT date
                                        </button>
                                    )}
                                </div>
                            </div>
                            <button
                                onClick={() => setReporting(v)}
                                className="shrink-0 rounded-full bg-brand text-white text-xs font-bold px-4 py-2.5"
                            >
                                ⚠ Report defect
                            </button>
                        </div>

                        {v.defects.length > 0 && (
                            <details className="mt-2" open={v.open_defects > 0}>
                                <summary className="text-xs font-bold text-brand cursor-pointer">
                                    Defects ({v.open_defects} open)
                                </summary>
                                <ul className="mt-2 divide-y divide-slate-100 text-sm">
                                    {v.defects.map((d) => (
                                        <li key={d.id} className="py-2 flex items-center justify-between gap-2">
                                            <div>
                                                <span className={d.resolved_at ? 'line-through text-slate-400' : ''}>{d.description}</span>
                                                <div className="text-xs text-slate-400">
                                                    {new Date(d.date).toLocaleDateString('en-GB')} · {d.reporter}
                                                </div>
                                            </div>
                                            <div className="flex items-center gap-1.5 shrink-0">
                                                <span className={`rounded-full px-2 py-0.5 text-[10px] font-bold ${SEVERITY_STYLE[d.severity]}`}>
                                                    {d.severity.replace(/_/g, ' ')}
                                                </span>
                                                {!d.resolved_at && canManage && (
                                                    <button
                                                        onClick={() => router.post(`/defects/${d.id}/resolve`)}
                                                        className="text-xs font-bold text-brand"
                                                    >
                                                        Resolve
                                                    </button>
                                                )}
                                            </div>
                                        </li>
                                    ))}
                                </ul>
                            </details>
                        )}
                    </Card>
                ))}
            </div>

            <Modal open={reporting !== null} title={`Report defect — ${reporting?.registration ?? ''}`} onClose={() => setReporting(null)}>
                <form onSubmit={submitDefect} className="space-y-3">
                    <label className="block text-sm font-medium">
                        What's wrong?
                        <textarea value={data.description} onChange={(e) => setData('description', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-300 p-3" rows={3} required />
                    </label>
                    <div className="flex gap-2">
                        {(['minor', 'serious', 'vehicle_off_road'] as const).map((s) => (
                            <button
                                key={s}
                                type="button"
                                onClick={() => setData('severity', s)}
                                className={`flex-1 rounded-lg py-2.5 text-xs font-bold capitalize ${
                                    data.severity === s ? 'bg-brand text-white' : 'bg-slate-100 text-slate-500'
                                }`}
                            >
                                {s.replace(/_/g, ' ')}
                            </button>
                        ))}
                    </div>
                    <button type="submit" disabled={processing} className="w-full rounded-lg bg-brand text-white font-bold py-3 disabled:opacity-60">
                        Report defect
                    </button>
                </form>
            </Modal>
        </AppShell>
    );
}

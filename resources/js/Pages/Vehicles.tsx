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
    last_mileage: number | null;
    last_check: { checked_at: string; checker: string; safe_to_drive: boolean } | null;
    checks: Array<{
        id: number; checked_at: string; checker: string; phase: 'morning' | 'afternoon' | null; odometer_miles: number; fuel_level: string;
        tyres_ok: boolean; lights_ok: boolean; warning_lights_ok: boolean; damage_ok: boolean;
        safe_to_drive: boolean; notes: string | null;
    }>;
}

const SEVERITY_STYLE: Record<string, string> = {
    minor: 'bg-slate-200 text-slate-600',
    serious: 'bg-amber-100 text-amber-800',
    vehicle_off_road: 'bg-red-100 text-red-800',
};

export default function Vehicles({ vehicles, canManage }: { vehicles: VehicleRow[]; canManage: boolean }) {
    const [reporting, setReporting] = useState<VehicleRow | null>(null);
    const { data, setData, post, processing, reset } = useForm({ description: '', severity: 'minor' });
    const [checking, setChecking] = useState<VehicleRow | null>(null);
    const checkForm = useForm({
        phase: 'morning', odometer_miles: '', fuel_level: 'half', tyres_ok: true, lights_ok: true,
        warning_lights_ok: true, damage_ok: true, notes: '',
    });

    function submitDefect(e: FormEvent) {
        e.preventDefault();
        if (!reporting) return;
        post(`/vehicles/${reporting.id}/defects`, { onSuccess: () => { setReporting(null); reset(); } });
    }

    function openCheck(vehicle: VehicleRow) {
        setChecking(vehicle);
        checkForm.setData({
            phase: 'morning', odometer_miles: vehicle.last_mileage?.toString() ?? '', fuel_level: 'half',
            tyres_ok: true, lights_ok: true, warning_lights_ok: true, damage_ok: true, notes: '',
        });
    }

    function submitCheck(e: FormEvent) {
        e.preventDefault();
        if (!checking) return;
        checkForm.post(`/vehicles/${checking.id}/checks`, {
            preserveScroll: true,
            onSuccess: () => { setChecking(null); checkForm.reset(); },
        });
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
                            <div className="flex flex-col sm:flex-row gap-2 shrink-0">
                                <button onClick={() => openCheck(v)} className="rounded-full bg-yellow-400 text-brand-dark text-xs font-extrabold px-4 py-2.5">
                                    ✓ Pre-drive check
                                </button>
                                <button onClick={() => setReporting(v)} className="rounded-full bg-brand text-white text-xs font-bold px-4 py-2.5">
                                    ⚠ Report defect
                                </button>
                            </div>
                        </div>

                        <div className="mt-3 grid grid-cols-2 gap-2 text-xs">
                            <div className="rounded-xl bg-slate-50 p-3">
                                <span className="text-slate-400 block">Last mileage</span>
                                <b>{v.last_mileage !== null ? `${v.last_mileage.toLocaleString()} miles` : 'No check recorded'}</b>
                            </div>
                            <div className={`rounded-xl p-3 ${v.last_check?.safe_to_drive ? 'bg-emerald-50 text-emerald-800' : v.last_check ? 'bg-red-50 text-red-800' : 'bg-slate-50'}`}>
                                <span className="opacity-70 block">Latest check</span>
                                <b>{v.last_check ? (v.last_check.safe_to_drive ? 'Safe to drive' : 'Off road') : 'Not completed'}</b>
                            </div>
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

                        {v.checks.length > 0 && (
                            <details className="mt-2">
                                <summary className="text-xs font-bold text-brand cursor-pointer">Vehicle history ({v.checks.length})</summary>
                                <div className="mt-2 overflow-x-auto">
                                    <table className="w-full text-xs">
                                        <thead><tr className="text-left text-slate-400"><th className="py-2">Date / run</th><th>Mileage</th><th>Fuel</th><th>Result</th><th>Checked by</th></tr></thead>
                                        <tbody>{v.checks.map((c) => <tr key={c.id} className="border-t border-slate-100">
                                            <td className="py-2">{new Date(c.checked_at).toLocaleString('en-GB')}{c.phase && <span className="block capitalize text-slate-400">{c.phase}</span>}</td>
                                            <td>{c.odometer_miles.toLocaleString()}</td><td>{c.fuel_level.replace('_', ' ')}</td>
                                            <td className={c.safe_to_drive ? 'text-emerald-700 font-bold' : 'text-red-700 font-bold'}>{c.safe_to_drive ? 'Safe' : 'Off road'}</td>
                                            <td>{c.checker}</td>
                                        </tr>)}</tbody>
                                    </table>
                                </div>
                            </details>
                        )}
                    </Card>
                ))}
            </div>

            <Modal open={checking !== null} title={`Pre-drive check — ${checking?.registration ?? ''}`} onClose={() => setChecking(null)}>
                <form onSubmit={submitCheck} className="space-y-4">
                    <label className="block text-sm font-medium">Transport run
                        <select value={checkForm.data.phase} onChange={(e) => checkForm.setData('phase', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-300 px-3 bg-white">
                            <option value="morning">Morning collection</option>
                            <option value="afternoon">Afternoon return</option>
                        </select>
                    </label>
                    <label className="block text-sm font-medium">Current mileage
                        <input type="number" min={checking?.last_mileage ?? 0} step="0.1" value={checkForm.data.odometer_miles} onChange={(e) => checkForm.setData('odometer_miles', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-300 px-3" required />
                        {checkForm.errors.odometer_miles && <span className="text-xs text-red-600">{checkForm.errors.odometer_miles}</span>}
                    </label>
                    <label className="block text-sm font-medium">Fuel level
                        <select value={checkForm.data.fuel_level} onChange={(e) => checkForm.setData('fuel_level', e.target.value)} className="mt-1 w-full rounded-lg border border-slate-300 px-3 bg-white">
                            {['empty','quarter','half','three_quarters','full'].map((level) => <option key={level} value={level}>{level.replace('_',' ')}</option>)}
                        </select>
                    </label>
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-2">
                        {([
                            ['tyres_ok','Tyres visually okay'], ['lights_ok','Lights working'],
                            ['warning_lights_ok','No warning lights'], ['damage_ok','No new damage'],
                        ] as const).map(([field,label]) => <label key={field} className={`flex items-center justify-between gap-3 rounded-xl border p-3 font-semibold ${checkForm.data[field] ? 'border-emerald-200 bg-emerald-50' : 'border-red-300 bg-red-50'}`}>
                            <span>{label}</span><input type="checkbox" checked={checkForm.data[field]} onChange={(e) => checkForm.setData(field, e.target.checked)} className="h-6 w-6" />
                        </label>)}
                    </div>
                    <label className="block text-sm font-medium">Notes or damage details
                        <textarea value={checkForm.data.notes} onChange={(e) => checkForm.setData('notes', e.target.value)} rows={3} className="mt-1 w-full rounded-lg border border-slate-300 p-3" />
                    </label>
                    <button disabled={checkForm.processing} className="w-full rounded-xl bg-yellow-400 py-3 font-extrabold text-brand-dark disabled:opacity-50">Save pre-drive check</button>
                    <p className="text-xs text-slate-500">Any failed safety item automatically marks the vehicle off road and opens a serious defect.</p>
                </form>
            </Modal>

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

import { Head, Link, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import AppShell from '../../components/AppShell';
import Card from '../../components/Card';
import Breadcrumbs from '../../components/Breadcrumbs';
import { confirmDialog } from '../../utils/dialogs';
import ModuleHero from '../../components/ModuleHero';

export interface Entry {
    id: number | null;
    staff_name: string;
    ni_number: string | null;
    hourly_rate: number;
    total_hours: number;
    basic_pay: number;
    holiday_pay: number;
    total_ssp: number;
    mileage: number;
    mileage_pay: number;
    total: number;
}

export interface Period {
    id: number;
    label: string;
    start_date: string;
    end_date: string;
    pay_date: string | null;
    pay_date_display: string | null;
    authorised_by: string | null;
    status: string;
    entries: Entry[];
}

// Live recalculation mirrors PayrollEntry::computeTotals server-side.
export function recompute(e: Entry): Entry {
    const basic = Math.round(e.hourly_rate * e.total_hours * 100) / 100;
    const total = Math.round((basic + e.holiday_pay + e.total_ssp + e.mileage_pay) * 100) / 100;
    return { ...e, basic_pay: basic, total };
}

const NUMERIC: [keyof Entry, string, number][] = [
    ['hourly_rate', 'Hourly Rate (£)', 0.01],
    ['total_hours', 'Total Hrs', 0.25],
    ['holiday_pay', 'Holiday Pay (£)', 0.01],
    ['total_ssp', 'Total SSP', 0.01],
    ['mileage', 'Mileage', 0.1],
    ['mileage_pay', 'Mileage Pay (£)', 0.01],
];

export default function Show({ period }: { period: Period }) {
    const [entries, setEntries] = useState<Entry[]>(period.entries);
    const [deleted, setDeleted] = useState<number[]>([]);
    const locked = period.status !== 'draft';

    const grandTotal = useMemo(() => entries.reduce((sum, e) => sum + e.total, 0), [entries]);

    function updateEntry(i: number, field: keyof Entry, value: string) {
        setEntries((prev) =>
            prev.map((e, j) => {
                if (j !== i) return e;
                const next = { ...e, [field]: field === 'staff_name' || field === 'ni_number' ? value : Number(value) || 0 };
                return recompute(next as Entry);
            }),
        );
    }

    function addRow() {
        setEntries((prev) => [
            ...prev,
            recompute({
                id: null, staff_name: '', ni_number: '', hourly_rate: 0, total_hours: 0,
                basic_pay: 0, holiday_pay: 0, total_ssp: 0, mileage: 0, mileage_pay: 0, total: 0,
            }),
        ]);
    }

    function removeRow(i: number) {
        const entry = entries[i];
        if (entry.id) setDeleted((d) => [...d, entry.id as number]);
        setEntries((prev) => prev.filter((_, j) => j !== i));
    }

    function save() {
        router.put(`/payroll/periods/${period.id}/entries`, { entries: entries.map((e) => ({ ...e })), deleted });
    }

    function setStatus(status: string) {
        router.put(`/payroll/periods/${period.id}`, { status });
    }

    return (
        <AppShell title={period.label}>
            <Head title={`Payroll — ${period.label}`} />
            <ModuleHero eyebrow="Payroll detail" title="Pay period" description="Check individual calculations and resolve anything unusual." icon="🧮" tone="green" />

            <Breadcrumbs items={[
                { label: 'Payroll', href: '/payroll' },
                { label: period.label },
            ]} />

            <div className="flex flex-wrap items-center gap-2 mb-4 text-sm">
                <span className="text-slate-500">
                    {new Date(period.start_date).toLocaleDateString('en-GB')} – {new Date(period.end_date).toLocaleDateString('en-GB')}
                    {period.pay_date_display && <> · Pay date <b>{period.pay_date_display}</b></>}
                </span>
                <span className="ml-auto flex gap-2">
                    <Link href={`/payroll/periods/${period.id}/print`} className="rounded-full bg-brand-dark text-white font-semibold text-xs px-4 py-2">
                        🖨 Print PDF
                    </Link>
                    {period.status === 'draft' && (
                        <button onClick={() => setStatus('finalised')} className="rounded-full bg-status-amber text-white font-semibold text-xs px-4 py-2">
                            Finalise
                        </button>
                    )}
                    {period.status === 'finalised' && (
                        <>
                            <button onClick={() => setStatus('paid')} className="rounded-full bg-status-green text-white font-semibold text-xs px-4 py-2">
                                Mark paid
                            </button>
                            <button onClick={() => setStatus('draft')} className="rounded-full bg-slate-200 text-slate-600 font-semibold text-xs px-4 py-2">
                                Back to draft
                            </button>
                        </>
                    )}
                    <button
                        onClick={async () => (await confirmDialog('Delete this pay period?')) && router.delete(`/payroll/periods/${period.id}`)}
                        className="rounded-full bg-red-100 text-red-700 font-semibold text-xs px-4 py-2"
                    >
                        Delete
                    </button>
                </span>
            </div>

            {/* Live total banner — dark blue with yellow total, matching the paper sheet */}
            <div className="rounded-card bg-brand-dark p-4 mb-4 flex items-center justify-between">
                <span className="font-semibold text-white">Period total</span>
                <span className="text-2xl font-extrabold text-accent">£{grandTotal.toFixed(2)}</span>
            </div>

            <Card>
                <div className="overflow-x-auto">
                    <table className="w-full text-sm min-w-[820px]">
                        <thead>
                            <tr className="text-left text-[11px] text-slate-400 uppercase">
                                <th className="py-1 pr-2">Staff Name</th>
                                <th className="py-1 pr-2">N.I.C No.</th>
                                {NUMERIC.map(([k, label]) => (
                                    <th key={k} className="py-1 pr-2">{label}</th>
                                ))}
                                <th className="py-1 pr-2 text-right">Basic Pay</th>
                                <th className="py-1 text-right">Total (£)</th>
                                <th />
                            </tr>
                        </thead>
                        <tbody>
                            {entries.map((e, i) => (
                                <tr key={e.id ?? `new-${i}`} className="border-t border-slate-100">
                                    <td className="py-1 pr-2">
                                        <input
                                            value={e.staff_name}
                                            disabled={locked}
                                            onInput={(ev) => updateEntry(i, 'staff_name', ev.currentTarget.value)}
                                            className="w-32 rounded border border-slate-200 px-2 !min-h-9 text-sm font-semibold"
                                        />
                                    </td>
                                    <td className="py-1 pr-2">
                                        <input
                                            value={e.ni_number ?? ''}
                                            disabled={locked}
                                            onInput={(ev) => updateEntry(i, 'ni_number', ev.currentTarget.value)}
                                            className="w-24 rounded border border-slate-200 px-2 !min-h-9 text-sm"
                                        />
                                    </td>
                                    {NUMERIC.map(([k, , step]) => (
                                        <td key={k} className="py-1 pr-2">
                                            <input
                                                type="number"
                                                step={step}
                                                min={0}
                                                value={e[k] as number}
                                                disabled={locked}
                                                onInput={(ev) => updateEntry(i, k, ev.currentTarget.value)}
                                                className="w-20 rounded border border-slate-200 px-2 !min-h-9 text-sm text-right"
                                            />
                                        </td>
                                    ))}
                                    <td className="py-1 pr-2 text-right font-semibold">£{e.basic_pay.toFixed(2)}</td>
                                    <td className="py-1 text-right font-extrabold text-brand-dark">£{e.total.toFixed(2)}</td>
                                    <td className="py-1 pl-2">
                                        {!locked && (
                                            <button onClick={() => removeRow(i)} aria-label="Remove row" className="text-red-400 hover:text-red-600">
                                                ✕
                                            </button>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                        <tfoot>
                            <tr className="bg-brand-dark">
                                <td colSpan={2} className="py-2 pl-2 text-white font-bold text-sm rounded-l-lg">Total</td>
                                {NUMERIC.map(([k]) => (
                                    <td key={k} className="py-2 text-right text-accent font-semibold text-sm">
                                        {k === 'hourly_rate' ? '' : entries.reduce((sum, e) => sum + (Number(e[k]) || 0), 0).toFixed(k === 'total_hours' || k === 'mileage' ? 1 : 2)}
                                    </td>
                                ))}
                                <td className="py-2 text-right text-accent font-semibold text-sm">
                                    £{entries.reduce((sum, e) => sum + e.basic_pay, 0).toFixed(2)}
                                </td>
                                <td className="py-2 pr-2 text-right text-accent font-extrabold text-sm rounded-r-lg" colSpan={2}>
                                    £{grandTotal.toFixed(2)}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                {!locked && (
                    <div className="flex gap-2 mt-3">
                        <button onClick={addRow} className="rounded-full bg-slate-100 text-slate-600 font-semibold text-sm px-4 py-2">
                            + Add row
                        </button>
                        <button onClick={save} className="rounded-full bg-brand text-white font-bold text-sm px-6 py-2">
                            Save payroll
                        </button>
                    </div>
                )}
                {locked && <p className="mt-3 text-xs text-slate-400">This period is {period.status} — set it back to draft to edit.</p>}
            </Card>
        </AppShell>
    );
}

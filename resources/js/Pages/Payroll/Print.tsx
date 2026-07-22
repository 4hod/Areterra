import { Head, usePage } from '@inertiajs/react';
import { useEffect } from 'react';
import type { Period } from './Show';
import { SharedProps } from '../../types';

// Landscape A4 payroll sheet reproduced from the legacy PDF layout (SPEC.md).
export default function Print({ period }: { period: Period }) {
    const { branding } = usePage<SharedProps>().props;

    useEffect(() => {
        const t = setTimeout(() => window.print(), 400);
        return () => clearTimeout(t);
    }, []);

    const total = period.entries.reduce((s, e) => s + e.total, 0);

    return (
        <div className="p-8 bg-white min-h-screen text-black">
            <Head title={`Print — ${period.label}`}>
                <style>{`@page { size: A4 landscape; margin: 12mm; } @media print { .no-print { display: none } }`}</style>
            </Head>

            <div className="flex items-start justify-between mb-2">
                {branding.logoUrl ? (
                    <img src={branding.logoUrl} alt={branding.orgName} className="h-10 object-contain object-left" />
                ) : (
                    <div className="text-xl font-extrabold underline">{branding.orgName}</div>
                )}
                <button onClick={() => window.print()} className="no-print rounded bg-slate-800 text-white text-sm font-semibold px-4 py-2">
                    🖨 Print
                </button>
            </div>

            {period.pay_date_display && (
                <h1 className="text-center text-lg font-extrabold underline mb-4">
                    Pay Date - {period.pay_date_display}
                </h1>
            )}

            <table className="w-full border-collapse text-[11px]">
                <thead>
                    <tr>
                        {['Staff Name', 'N.I.C No.', 'Hourly Rate (£)', 'Total No. Hours', 'Basic Pay', 'Holiday Pay (£)', 'Total SSP', 'Mileage', 'Mileage Pay (£)', 'Total (£)'].map((h) => (
                            <th key={h} className="border border-black px-2 py-1.5 text-left font-bold bg-slate-100">
                                {h}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody>
                    {period.entries.map((e, i) => (
                        <tr key={i}>
                            <td className="border border-black px-2 py-1.5 font-semibold">{e.staff_name}</td>
                            <td className="border border-black px-2 py-1.5">{e.ni_number}</td>
                            <td className="border border-black px-2 py-1.5">{e.hourly_rate.toFixed(2)}</td>
                            <td className="border border-black px-2 py-1.5">{e.total_hours.toFixed(2)}</td>
                            <td className="border border-black px-2 py-1.5">{e.basic_pay.toFixed(2)}</td>
                            <td className="border border-black px-2 py-1.5">{e.holiday_pay.toFixed(2)}</td>
                            <td className="border border-black px-2 py-1.5">{e.total_ssp.toFixed(2)}</td>
                            <td className="border border-black px-2 py-1.5">{e.mileage.toFixed(1)}</td>
                            <td className="border border-black px-2 py-1.5">{e.mileage_pay.toFixed(2)}</td>
                            <td className="border border-black px-2 py-1.5 font-bold">{e.total.toFixed(2)}</td>
                        </tr>
                    ))}
                    <tr>
                        <td colSpan={9} className="border border-black px-2 py-1.5 text-right font-extrabold text-white" style={{ background: '#00345C' }}>
                            TOTAL
                        </td>
                        <td className="border border-black px-2 py-1.5 font-extrabold text-white" style={{ background: '#00345C' }}>
                            {total.toFixed(2)}
                        </td>
                    </tr>
                </tbody>
            </table>

            <div className="flex gap-16 mt-12 text-sm">
                <div>
                    <div className="border-b border-black w-56 h-6 font-semibold">{period.authorised_by ?? ''}</div>
                    <div className="mt-1">Authorised by</div>
                </div>
                <div>
                    <div className="border-b border-black w-40 h-6" />
                    <div className="mt-1">Date</div>
                </div>
                <div>
                    <div className="border-b border-black w-56 h-6" />
                    <div className="mt-1">Received by</div>
                </div>
            </div>
        </div>
    );
}

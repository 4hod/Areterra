import { Head, router, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import AppShell from '../components/AppShell';
import Card from '../components/Card';
import Modal from '../components/Modal';
import { confirmDialog } from '../utils/dialogs';

interface OrderRow {
    id: number;
    item_name: string;
    quantity: number;
    unit: string | null;
    category: string | null;
    notes: string | null;
    status: 'pending' | 'approved' | 'rejected' | 'ordered' | 'delivered';
    requested_by: string;
    is_mine: boolean;
    approved_by: string | null;
    rejection_reason: string | null;
    supplier: string | null;
    expected_delivery_date: string | null;
    cost: number | null;
    delivered_at: string | null;
    delivery_notes: string | null;
    created_at: string;
}

const STATUS_STYLE: Record<string, string> = {
    pending: 'bg-amber-100 text-amber-800',
    approved: 'bg-sky-100 text-sky-800',
    rejected: 'bg-red-100 text-red-800',
    ordered: 'bg-violet-100 text-violet-800',
    delivered: 'bg-emerald-100 text-emerald-800',
};

const fmt = (d: string) => new Date(d).toLocaleDateString('en-GB', { day: 'numeric', month: 'short' });

export default function Orders({ orders, canManage }: { orders: OrderRow[]; canManage: boolean }) {
    const [requesting, setRequesting] = useState(false);
    const [rejecting, setRejecting] = useState<OrderRow | null>(null);
    const [placing, setPlacing] = useState<OrderRow | null>(null);
    const [delivering, setDelivering] = useState<OrderRow | null>(null);
    const [rejectReason, setRejectReason] = useState('');

    const { data, setData, post, processing, errors, reset } = useForm({
        item_name: '',
        quantity: '1',
        unit: '',
        category: '',
        notes: '',
    });

    const placeForm = useForm({ supplier: '', expected_delivery_date: '', cost: '' });
    const deliverForm = useForm({ delivery_notes: '' });

    function submit(e: FormEvent) {
        e.preventDefault();
        post('/orders', { onSuccess: () => { setRequesting(false); reset(); } });
    }

    function approve(row: OrderRow) {
        router.put(`/orders/${row.id}/approve`);
    }

    function submitReject(e: FormEvent) {
        e.preventDefault();
        if (!rejecting) return;
        router.put(`/orders/${rejecting.id}/reject`, { rejection_reason: rejectReason }, {
            onSuccess: () => { setRejecting(null); setRejectReason(''); },
        });
    }

    function submitPlace(e: FormEvent) {
        e.preventDefault();
        if (!placing) return;
        placeForm.put(`/orders/${placing.id}/ordered`, { onSuccess: () => setPlacing(null) });
    }

    function submitDeliver(e: FormEvent) {
        e.preventDefault();
        if (!delivering) return;
        deliverForm.put(`/orders/${delivering.id}/delivered`, { onSuccess: () => setDelivering(null) });
    }

    async function cancel(row: OrderRow) {
        if (await confirmDialog(`Cancel the request for ${row.item_name}?`)) {
            router.delete(`/orders/${row.id}`);
        }
    }

    const pending = orders.filter((o) => o.status === 'pending');
    const inProgress = orders.filter((o) => o.status === 'approved' || o.status === 'ordered');
    const closed = orders.filter((o) => o.status === 'rejected' || o.status === 'delivered');

    function Row({ row }: { row: OrderRow }) {
        return (
            <li className="py-3">
                <div className="flex items-start justify-between gap-2">
                    <div className="min-w-0">
                        <div className="font-bold text-sm">
                            {row.quantity} {row.unit ? `${row.unit} ` : ''}× {row.item_name}
                        </div>
                        <div className="text-xs text-slate-500">
                            {row.category && <>{row.category} · </>}
                            Requested by {row.requested_by} · {fmt(row.created_at)}
                        </div>
                        {row.notes && <div className="text-sm text-slate-500 mt-1">{row.notes}</div>}
                        {row.status === 'rejected' && row.rejection_reason && (
                            <div className="text-sm text-red-600 mt-1">Declined: {row.rejection_reason}</div>
                        )}
                        {(row.status === 'ordered' || row.status === 'delivered') && (
                            <div className="text-xs text-slate-500 mt-1">
                                {row.supplier && <>Supplier: {row.supplier} · </>}
                                {row.expected_delivery_date && <>Expected {fmt(row.expected_delivery_date)} · </>}
                                {row.cost !== null && <>£{row.cost.toFixed(2)}</>}
                            </div>
                        )}
                        {row.status === 'delivered' && row.delivered_at && (
                            <div className="text-xs text-emerald-700 mt-1">
                                Delivered {fmt(row.delivered_at)}{row.delivery_notes && ` — ${row.delivery_notes}`}
                            </div>
                        )}
                    </div>
                    <span className={`shrink-0 rounded-full px-2.5 py-0.5 text-xs font-semibold capitalize ${STATUS_STYLE[row.status]}`}>
                        {row.status}
                    </span>
                </div>

                <div className="flex gap-1.5 mt-2 flex-wrap">
                    {row.is_mine && row.status === 'pending' && (
                        <button onClick={() => cancel(row)} className="rounded-full bg-slate-100 text-slate-600 font-semibold text-xs px-3 py-1.5">
                            Cancel
                        </button>
                    )}
                    {canManage && row.status === 'pending' && (
                        <>
                            <button onClick={() => approve(row)} className="rounded-full bg-emerald-100 text-emerald-800 font-bold text-xs px-3 py-1.5">
                                ✓ Approve
                            </button>
                            <button onClick={() => setRejecting(row)} className="rounded-full bg-red-100 text-red-700 font-bold text-xs px-3 py-1.5">
                                ✕ Decline
                            </button>
                        </>
                    )}
                    {canManage && row.status === 'approved' && (
                        <button onClick={() => setPlacing(row)} className="rounded-full bg-violet-100 text-violet-800 font-bold text-xs px-3 py-1.5">
                            📦 Mark as ordered
                        </button>
                    )}
                    {canManage && row.status === 'ordered' && (
                        <button onClick={() => setDelivering(row)} className="rounded-full bg-brand text-white font-bold text-xs px-3 py-1.5">
                            ✓ Mark delivered
                        </button>
                    )}
                </div>
            </li>
        );
    }

    return (
        <AppShell title="Orders">
            <Head title="Orders" />

            <button
                onClick={() => setRequesting(true)}
                className="rounded-full bg-brand text-white font-semibold text-sm px-5 py-2.5 mb-4"
            >
                + Request product
            </button>

            {pending.length > 0 && (
                <Card title={`Awaiting approval (${pending.length})`} className="mb-4 border-l-4 border-l-status-amber">
                    <ul className="divide-y divide-slate-100">
                        {pending.map((r) => <Row key={r.id} row={r} />)}
                    </ul>
                </Card>
            )}

            {inProgress.length > 0 && (
                <Card title="In progress" className="mb-4">
                    <ul className="divide-y divide-slate-100">
                        {inProgress.map((r) => <Row key={r.id} row={r} />)}
                    </ul>
                </Card>
            )}

            <Card title="Delivered & declined">
                {closed.length === 0 && <p className="text-sm text-slate-400">Nothing here yet.</p>}
                <ul className="divide-y divide-slate-100">
                    {closed.map((r) => <Row key={r.id} row={r} />)}
                </ul>
            </Card>

            <Modal open={requesting} title="Request a product" onClose={() => setRequesting(false)}>
                <form onSubmit={submit} className="space-y-3">
                    <label className="block text-sm font-medium">
                        Item
                        <input
                            value={data.item_name}
                            onChange={(e) => setData('item_name', e.target.value)}
                            placeholder="e.g. Rabbit feed, nitrile gloves"
                            className="mt-1 w-full rounded-lg border border-slate-300 px-3"
                            required
                        />
                    </label>
                    <div className="grid grid-cols-2 gap-3">
                        <label className="block text-sm font-medium">
                            Quantity
                            <input
                                type="number"
                                min="0.01"
                                step="0.01"
                                value={data.quantity}
                                onChange={(e) => setData('quantity', e.target.value)}
                                className="mt-1 w-full rounded-lg border border-slate-300 px-3"
                                required
                            />
                        </label>
                        <label className="block text-sm font-medium">
                            Unit (optional)
                            <input
                                value={data.unit}
                                onChange={(e) => setData('unit', e.target.value)}
                                placeholder="boxes, kg..."
                                className="mt-1 w-full rounded-lg border border-slate-300 px-3"
                            />
                        </label>
                    </div>
                    {errors.item_name && <p className="text-red-600 text-sm">{errors.item_name}</p>}
                    <label className="block text-sm font-medium">
                        Category (optional)
                        <input
                            value={data.category}
                            onChange={(e) => setData('category', e.target.value)}
                            placeholder="Animal feed, PPE, Activities..."
                            className="mt-1 w-full rounded-lg border border-slate-300 px-3"
                        />
                    </label>
                    <label className="block text-sm font-medium">
                        Notes (optional)
                        <textarea
                            value={data.notes}
                            onChange={(e) => setData('notes', e.target.value)}
                            className="mt-1 w-full rounded-lg border border-slate-300 p-3"
                            rows={2}
                        />
                    </label>
                    <button type="submit" disabled={processing} className="w-full rounded-lg bg-brand text-white font-bold py-3 disabled:opacity-60">
                        Submit request
                    </button>
                </form>
            </Modal>

            <Modal open={rejecting !== null} title={`Decline — ${rejecting?.item_name ?? ''}`} onClose={() => setRejecting(null)}>
                <form onSubmit={submitReject} className="space-y-3">
                    <label className="block text-sm font-medium">
                        Reason (optional)
                        <textarea
                            value={rejectReason}
                            onChange={(e) => setRejectReason(e.target.value)}
                            className="mt-1 w-full rounded-lg border border-slate-300 p-3"
                            rows={2}
                        />
                    </label>
                    <button type="submit" className="w-full rounded-lg bg-red-600 text-white font-bold py-3">
                        Decline request
                    </button>
                </form>
            </Modal>

            <Modal open={placing !== null} title={`Mark as ordered — ${placing?.item_name ?? ''}`} onClose={() => setPlacing(null)}>
                <form onSubmit={submitPlace} className="space-y-3">
                    <label className="block text-sm font-medium">
                        Supplier (optional)
                        <input
                            value={placeForm.data.supplier}
                            onChange={(e) => placeForm.setData('supplier', e.target.value)}
                            className="mt-1 w-full rounded-lg border border-slate-300 px-3"
                        />
                    </label>
                    <div className="grid grid-cols-2 gap-3">
                        <label className="block text-sm font-medium">
                            Expected delivery
                            <input
                                type="date"
                                value={placeForm.data.expected_delivery_date}
                                onChange={(e) => placeForm.setData('expected_delivery_date', e.target.value)}
                                className="mt-1 w-full rounded-lg border border-slate-300 px-3"
                            />
                        </label>
                        <label className="block text-sm font-medium">
                            Cost £ (optional)
                            <input
                                type="number"
                                min="0"
                                step="0.01"
                                value={placeForm.data.cost}
                                onChange={(e) => placeForm.setData('cost', e.target.value)}
                                className="mt-1 w-full rounded-lg border border-slate-300 px-3"
                            />
                        </label>
                    </div>
                    <button type="submit" disabled={placeForm.processing} className="w-full rounded-lg bg-brand text-white font-bold py-3 disabled:opacity-60">
                        Save
                    </button>
                </form>
            </Modal>

            <Modal open={delivering !== null} title={`Mark delivered — ${delivering?.item_name ?? ''}`} onClose={() => setDelivering(null)}>
                <form onSubmit={submitDeliver} className="space-y-3">
                    <label className="block text-sm font-medium">
                        Delivery notes (optional)
                        <textarea
                            value={deliverForm.data.delivery_notes}
                            onChange={(e) => deliverForm.setData('delivery_notes', e.target.value)}
                            className="mt-1 w-full rounded-lg border border-slate-300 p-3"
                            rows={2}
                        />
                    </label>
                    <button type="submit" disabled={deliverForm.processing} className="w-full rounded-lg bg-brand text-white font-bold py-3 disabled:opacity-60">
                        Confirm delivered
                    </button>
                </form>
            </Modal>
        </AppShell>
    );
}

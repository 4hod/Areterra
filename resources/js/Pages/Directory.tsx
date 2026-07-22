import { Head } from '@inertiajs/react';
import AppShell from '../components/AppShell';
import Card from '../components/Card';

interface Staff {
    name: string;
    job_title: string | null;
    email: string | null;
    phone: string | null;
    photo_path?: string | null;
    has_account: boolean;
}

export default function Directory({ staff }: { staff: Staff[] }) {
    return (
        <AppShell title="Staff Directory">
            <Head title="Directory" />
            <div className="space-y-2">
                {staff.map((s, i) => (
                    <Card key={i}>
                        <div className="flex items-center gap-3">
                            {s.photo_path ? (
                                <img src={s.photo_path} alt={s.name} className="h-11 w-11 shrink-0 rounded-full object-cover" />
                            ) : (
                                <div className="h-11 w-11 shrink-0 rounded-full bg-brand/10 text-brand font-bold flex items-center justify-center text-lg">
                                    {s.name.charAt(0)}
                                </div>
                            )}
                            <div className="flex-1 min-w-0">
                                <div className="font-bold text-brand-dark">{s.name}</div>
                                <div className="text-xs text-slate-400 font-medium capitalize">{s.job_title}</div>
                            </div>
                            <div className="flex gap-2 text-sm">
                                {s.phone && (
                                    <a href={`tel:${s.phone}`} className="text-brand font-semibold">
                                        📞
                                    </a>
                                )}
                                {s.email && (
                                    <a href={`mailto:${s.email}`} className="text-brand font-semibold">
                                        ✉️
                                    </a>
                                )}
                            </div>
                        </div>
                    </Card>
                ))}
            </div>
        </AppShell>
    );
}

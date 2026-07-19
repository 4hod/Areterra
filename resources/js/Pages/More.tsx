import { Head, Link, usePage } from '@inertiajs/react';
import AppShell, { NAV_SECTIONS, allowed } from '../components/AppShell';
import Card from '../components/Card';
import { SharedProps } from '../types';

export default function More() {
    const { auth } = usePage<SharedProps>().props;
    const caps = auth.user?.capabilities ?? [];

    return (
        <AppShell title="More">
            <Head title="More" />
            <div className="space-y-4">
                {NAV_SECTIONS.map((section, i) => {
                    const items = section.items.filter((item) => allowed(item, caps));
                    if (items.length === 0) return null;
                    return (
                        <Card key={i} title={section.title ?? 'Daily'}>
                            <div className="grid grid-cols-2 gap-2">
                                {items.map((item) => (
                                    <Link
                                        key={item.href}
                                        href={item.href}
                                        className="flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-3 font-semibold text-sm text-brand-dark hover:bg-slate-50"
                                    >
                                        <span className="text-xl" aria-hidden>
                                            {item.icon}
                                        </span>
                                        {item.label}
                                    </Link>
                                ))}
                            </div>
                        </Card>
                    );
                })}
            </div>
        </AppShell>
    );
}

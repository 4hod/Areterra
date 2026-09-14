import { Head, Link, usePage } from '@inertiajs/react';
import AppShell, { allowed } from '../components/AppShell';
import DailyFlowNav from '../components/DailyFlowNav';
import ModuleHero from '../components/ModuleHero';
import { SharedProps } from '../types';
import { WORKSPACES } from '../workspaceConfig';

export default function Workspace({ workspace }: { workspace: string }) {
    const definition = WORKSPACES[workspace];
    const { auth } = usePage<SharedProps>().props;
    const capabilities = auth.user?.capabilities ?? [];

    if (!definition) return null;

    const visibleItems = definition.items.filter((item) => allowed(item, capabilities));
    const groups = [...new Set(visibleItems.map((item) => item.group))];

    return (
        <AppShell title={definition.title}>
            <Head title={definition.title} />
            <ModuleHero
                eyebrow={definition.eyebrow}
                title={definition.title}
                description={definition.description}
                icon={definition.icon}
                tone={definition.tone}
            />

            {workspace === 'operations' && <DailyFlowNav />}

            <div className="workspace-layout">
                {groups.map((group) => (
                    <section key={group} className="workspace-group" aria-labelledby={`workspace-${group}`}>
                        <div className="workspace-group-heading">
                            <h2 id={`workspace-${group}`}>{group}</h2>
                            <span>{visibleItems.filter((item) => item.group === group).length} tools</span>
                        </div>
                        <div className="workspace-card-grid">
                            {visibleItems.filter((item) => item.group === group).map((item) => (
                                <Link key={`${group}-${item.href}-${item.label}`} href={item.href} className="workspace-card">
                                    <span className="workspace-card-icon" aria-hidden>{item.icon}</span>
                                    <span className="workspace-card-copy">
                                        <strong>{item.label}</strong>
                                        <small>{item.description}</small>
                                    </span>
                                    <span className="workspace-card-arrow" aria-hidden>→</span>
                                </Link>
                            ))}
                        </div>
                    </section>
                ))}
            </div>
        </AppShell>
    );
}

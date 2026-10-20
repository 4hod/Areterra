import { Head, Link, usePage } from '@inertiajs/react';
import AppShell from '../components/AppShell';
import AppIcon from '../components/AppIcon';
import ModuleHero from '../components/ModuleHero';
import { SharedProps } from '../types';

const GUIDES = [
    {
        title: 'Start and finish the day',
        description: 'Use Today to work through transport, the morning register, animal care and end-of-day records in the right order.',
        href: '/today',
        action: 'Open Today',
        icon: 'today',
    },
    {
        title: 'Take the morning register',
        description: 'Record who is present or absent, add the reason when somebody is away and keep the live fire register accurate.',
        href: '/register',
        action: 'Open register',
        icon: 'register',
        capabilities: ['view_member_details', 'log_sessions'],
    },
    {
        title: 'Record animal care',
        description: 'Confirm food, fresh water, hay and the quick welfare check for each animal group.',
        href: '/animals',
        action: 'Open animal care',
        icon: 'paw',
        capabilities: ['view_animals'],
    },
    {
        title: 'Record a concern',
        description: 'Use Incidents for accidents and near misses. Safeguarding records remain restricted to authorised staff.',
        href: '/incidents',
        action: 'Open incidents',
        icon: 'alert',
        capabilities: ['report_incidents'],
    },
    {
        title: 'Find a member record',
        description: 'Open a member profile for contact details, their day passport, outcomes, care information and connected records.',
        href: '/members',
        action: 'Open members',
        icon: 'users',
        capabilities: ['view_members'],
    },
    {
        title: 'Find a policy or document',
        description: 'Use Documents for shared files and Policies for the current approved guidance.',
        href: '/documents',
        action: 'Open documents',
        icon: 'folder',
        capabilities: ['view_documents'],
    },
];

export default function Help() {
    const capabilities = usePage<SharedProps>().props.auth.user?.capabilities ?? [];
    const guides = GUIDES.filter((guide) => !guide.capabilities || guide.capabilities.every((capability) => capabilities.includes(capability)));

    return (
        <AppShell title="Help & guides">
            <Head title="Help & guides" />
            <ModuleHero
                eyebrow="Staff support"
                title="Help & guides"
                description="Short, practical guidance for the jobs staff complete most often."
                icon="?"
                tone="blue"
            />

            <section className="help-start-panel" aria-labelledby="help-start-title">
                <div>
                    <span>NOT SURE WHERE TO START?</span>
                    <h2 id="help-start-title">Open Today</h2>
                    <p>Today shows the next job, what is already complete and anything still needing attention.</p>
                </div>
                <Link href="/today"><AppIcon name="today" />Open Today<AppIcon name="arrow" /></Link>
            </section>

            <div className="help-guide-grid">
                {guides.map((guide) => (
                    <article className="help-guide-card" key={guide.title}>
                        <i><AppIcon name={guide.icon} /></i>
                        <div>
                            <h2>{guide.title}</h2>
                            <p>{guide.description}</p>
                            <Link href={guide.href}>{guide.action}<AppIcon name="arrow" /></Link>
                        </div>
                    </article>
                ))}
            </div>

            <section className="help-safety-note">
                <AppIcon name="shield" />
                <div><h2>Something urgent?</h2><p>Follow Areterra’s emergency procedure first. The Hub records what happened; it does not replace calling emergency services or speaking to the manager.</p></div>
            </section>
        </AppShell>
    );
}

import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import AppShell from '../components/AppShell';
import Card from '../components/Card';
import Modal from '../components/Modal';
import ModuleHero from '../components/ModuleHero';

type Priority = 'low' | 'medium' | 'high';

interface Task {
    id: number;
    title: string;
    description: string | null;
    priority: Priority;
    due_date: string | null;
    overdue: boolean;
    completed_at: string | null;
    assignee: string | null;
    about: { type: string; name: string } | null;
    automatic: boolean;
    notes: string | null;
}

interface Props {
    tasks: Task[];
    show: 'open' | 'done';
    mine: boolean;
    staff: { id: number; name: string }[];
    counts: { open: number; overdue: number };
    canManage: boolean;
}

const PRIORITY_STYLE: Record<Priority, string> = {
    high: 'bg-red-100 text-red-700',
    medium: 'bg-amber-100 text-amber-700',
    low: 'bg-slate-100 text-slate-600',
};

const ABOUT_ICON: Record<string, string> = {
    Member: '🧑',
    Animal: '🦜',
    Vehicle: '🚐',
    Grant: '🎁',
    Incident: '⚠️',
    ComplianceItem: '📋',
    MemberInvoice: '💷',
};

function dueLabel(task: Task) {
    if (!task.due_date) return 'No date';
    const date = new Date(task.due_date);
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    if (date.getTime() === today.getTime()) return 'Today';
    return date.toLocaleDateString('en-GB', { day: 'numeric', month: 'short' });
}

export default function Tasks({ tasks, show, mine, staff, counts, canManage }: Props) {
    const [adding, setAdding] = useState(false);
    const [title, setTitle] = useState('');
    const [description, setDescription] = useState('');
    const [priority, setPriority] = useState<Priority>('medium');
    const [dueDate, setDueDate] = useState('');
    const [assignedTo, setAssignedTo] = useState<string>('');
    const dueThisWeek = tasks.filter((task) => {
        if (!task.due_date || task.completed_at) return false;
        const due = new Date(task.due_date);
        const now = new Date();
        const week = new Date();
        week.setDate(now.getDate() + 7);
        return due >= now && due <= week;
    }).length;
    const completedVisible = tasks.filter((task) => task.completed_at).length;

    function filter(next: Partial<{ show: string; mine: string }>) {
        router.get('/tasks', { show, mine: mine ? 1 : undefined, ...next }, { preserveState: true });
    }

    function add() {
        router.post(
            '/tasks',
            {
                title,
                description: description || null,
                priority,
                due_date: dueDate || null,
                ...(canManage ? { assigned_to: assignedTo || null } : {}),
            },
            {
                onSuccess: () => {
                    setAdding(false);
                    setTitle('');
                    setDescription('');
                    setDueDate('');
                    setAssignedTo('');
                },
            },
        );
    }

    return (
        <AppShell title="Tasks">
            <Head title="Tasks" />
            <ModuleHero
                eyebrow="Across every record"
                title="Tasks"
                description="Follow-ups attached to the member, animal, vehicle or grant they belong to."
                icon="☑️"
                tone="blue"
            />

            <div className="tasks-page-4a">
            <section className="tasks-metrics-4a">
                <article className="is-red"><span>!</span><div><strong>{counts.overdue}</strong><b>Overdue</b><small>Need attention</small></div></article>
                <article className="is-amber"><span>◷</span><div><strong>{dueThisWeek}</strong><b>Due this week</b><small>Next 7 days</small></div></article>
                <article className="is-blue"><span>✓</span><div><strong>{counts.open}</strong><b>Open tasks</b><small>Total active</small></div></article>
                <article className="is-green"><span>✓</span><div><strong>{completedVisible}</strong><b>Completed</b><small>Current view</small></div></article>
            </section>

            <section className="tasks-toolbar-4a">
            <div className="tasks-tabs-4a">
                <button
                    onClick={() => filter({ show: 'open' })}
                    className={show === 'open' ? 'is-active' : ''}
                >
                    Open ({counts.open})
                </button>
                <button
                    onClick={() => filter({ show: 'done' })}
                    className={show === 'done' ? 'is-active' : ''}
                >
                    Completed
                </button>
                <button
                    onClick={() => filter({ mine: mine ? undefined : '1' })}
                    className={mine ? 'is-active' : ''}
                >
                    Assigned to me
                </button>
                {counts.overdue > 0 && show === 'open' && (
                    <span className="tasks-overdue-4a">{counts.overdue} overdue</span>
                )}
                <button
                    onClick={() => setAdding(true)}
                    className="tasks-add-4a"
                >
                    + Add task
                </button>
            </div>
            </section>

            {tasks.length === 0 && (
                <Card>
                    <p className="text-slate-500">
                        {show === 'done'
                            ? 'Nothing completed yet.'
                            : 'No open tasks. Follow-ups raised from welfare checks, incidents and reviews will appear here automatically.'}
                    </p>
                </Card>
            )}

            <div className="tasks-list-4a">
                {tasks.map((task) => (
                    <Card key={task.id} className={`task-row-4a ${task.overdue ? 'is-overdue' : ''}`}>
                        <div className="task-row-inner-4a">
                            <div className={`task-type-icon-4a is-${task.priority}`}>{task.about ? (ABOUT_ICON[task.about.type] ?? '📎') : '✓'}</div>
                            <div className="flex-1 min-w-0">
                                <div className="flex flex-wrap items-center gap-2">
                                    <span className={`font-semibold ${task.completed_at ? 'line-through text-slate-400' : ''}`}>
                                        {task.title}
                                    </span>
                                    <span className={`rounded-full text-[11px] font-bold px-2 py-0.5 ${PRIORITY_STYLE[task.priority]}`}>
                                        {task.priority}
                                    </span>
                                    {task.automatic && (
                                        <span
                                            className="rounded-full bg-blue-50 text-blue-700 text-[11px] font-bold px-2 py-0.5"
                                            title="Closes by itself once the underlying action is recorded"
                                        >
                                            auto
                                        </span>
                                    )}
                                </div>

                                {task.about && (
                                    <div className="text-xs text-slate-500 mt-1">
                                        {ABOUT_ICON[task.about.type] ?? '📎'} {task.about.name}
                                    </div>
                                )}
                                {task.description && <p className="text-sm text-slate-600 mt-1">{task.description}</p>}

                                <div className="flex flex-wrap gap-3 mt-2 text-xs">
                                    <span className={task.overdue ? 'text-red-600 font-bold' : 'text-slate-500'}>
                                        {task.completed_at ? `Done ${new Date(task.completed_at).toLocaleDateString('en-GB')}` : dueLabel(task)}
                                    </span>
                                    {canManage ? <select
                                        value=""
                                        onChange={(e) => router.put(`/tasks/${task.id}`, { assigned_to: e.target.value || null })}
                                        className="bg-transparent text-slate-500 border-none p-0 text-xs"
                                    >
                                        <option value="">{task.assignee ?? 'Unassigned'}</option>
                                        {staff.map((person) => (
                                            <option key={person.id} value={person.id}>
                                                {person.name}
                                            </option>
                                        ))}
                                    </select> : <span className="text-slate-500">{task.assignee ?? 'Assigned to you'}</span>}
                                </div>
                            </div>

                            <button
                                onClick={() =>
                                    router.post(`/tasks/${task.id}/${task.completed_at ? 'reopen' : 'complete'}`)
                                }
                                className={`shrink-0 rounded-full font-semibold text-xs px-3 py-2 ${
                                    task.completed_at ? 'bg-slate-100 text-slate-600' : 'bg-status-green text-white'
                                }`}
                            >
                                {task.completed_at ? 'Reopen' : 'Done ✓'}
                            </button>
                        </div>
                    </Card>
                ))}
            </div>
            </div>

            <Modal open={adding} title="Add a task" onClose={() => setAdding(false)}>
                <div className="space-y-4">
                    {canManage && <label className="block text-sm font-medium">
                        What needs doing
                        <input
                            value={title}
                            onChange={(e) => setTitle(e.target.value)}
                            className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"
                        />
                    </label>}
                    <label className="block text-sm font-medium">
                        Detail
                        <textarea
                            value={description}
                            onChange={(e) => setDescription(e.target.value)}
                            rows={2}
                            className="mt-1 w-full rounded-lg border border-slate-300 p-3"
                        />
                    </label>
                    <div className="grid grid-cols-2 gap-3">
                        <label className="block text-sm font-medium">
                            Priority
                            <select
                                value={priority}
                                onChange={(e) => setPriority(e.target.value as Priority)}
                                className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"
                            >
                                <option value="low">Low</option>
                                <option value="medium">Medium</option>
                                <option value="high">High</option>
                            </select>
                        </label>
                        <label className="block text-sm font-medium">
                            Due
                            <input
                                type="date"
                                value={dueDate}
                                onChange={(e) => setDueDate(e.target.value)}
                                className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"
                            />
                        </label>
                    </div>
                    <label className="block text-sm font-medium">
                        Assign to
                        <select
                            value={assignedTo}
                            onChange={(e) => setAssignedTo(e.target.value)}
                            className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"
                        >
                            <option value="">Nobody yet</option>
                            {staff.map((person) => (
                                <option key={person.id} value={person.id}>
                                    {person.name}
                                </option>
                            ))}
                        </select>
                    </label>
                    <button
                        onClick={add}
                        disabled={!title.trim()}
                        className="w-full rounded-lg bg-brand text-white font-bold py-3 disabled:bg-slate-300"
                    >
                        Add task
                    </button>
                </div>
            </Modal>
        </AppShell>
    );
}

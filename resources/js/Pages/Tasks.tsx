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

export default function Tasks({ tasks, show, mine, staff, counts }: Props) {
    const [adding, setAdding] = useState(false);
    const [title, setTitle] = useState('');
    const [description, setDescription] = useState('');
    const [priority, setPriority] = useState<Priority>('medium');
    const [dueDate, setDueDate] = useState('');
    const [assignedTo, setAssignedTo] = useState<string>('');

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
                assigned_to: assignedTo || null,
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

            <div className="flex flex-wrap items-center gap-2 mb-4">
                <button
                    onClick={() => filter({ show: 'open' })}
                    className={`rounded-full font-semibold text-xs px-4 py-2 ${show === 'open' ? 'bg-brand text-white' : 'bg-slate-100 text-slate-600'}`}
                >
                    Open ({counts.open})
                </button>
                <button
                    onClick={() => filter({ show: 'done' })}
                    className={`rounded-full font-semibold text-xs px-4 py-2 ${show === 'done' ? 'bg-brand text-white' : 'bg-slate-100 text-slate-600'}`}
                >
                    Completed
                </button>
                <button
                    onClick={() => filter({ mine: mine ? undefined : '1' })}
                    className={`rounded-full font-semibold text-xs px-4 py-2 ${mine ? 'bg-brand text-white' : 'bg-slate-100 text-slate-600'}`}
                >
                    Assigned to me
                </button>
                {counts.overdue > 0 && show === 'open' && (
                    <span className="text-xs font-bold text-red-600">{counts.overdue} overdue</span>
                )}
                <button
                    onClick={() => setAdding(true)}
                    className="ml-auto rounded-full bg-brand text-white font-bold text-xs px-4 py-2"
                >
                    Add task
                </button>
            </div>

            {tasks.length === 0 && (
                <Card>
                    <p className="text-slate-500">
                        {show === 'done'
                            ? 'Nothing completed yet.'
                            : 'No open tasks. Follow-ups raised from welfare checks, incidents and reviews will appear here automatically.'}
                    </p>
                </Card>
            )}

            <div className="space-y-2">
                {tasks.map((task) => (
                    <Card key={task.id} className={task.overdue ? 'border-l-4 border-l-red-500' : ''}>
                        <div className="flex items-start gap-3">
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
                                    <select
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
                                    </select>
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

            <Modal open={adding} title="Add a task" onClose={() => setAdding(false)}>
                <div className="space-y-4">
                    <label className="block text-sm font-medium">
                        What needs doing
                        <input
                            value={title}
                            onChange={(e) => setTitle(e.target.value)}
                            className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"
                        />
                    </label>
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

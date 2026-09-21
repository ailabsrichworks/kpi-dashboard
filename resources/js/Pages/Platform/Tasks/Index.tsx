import { router, useForm } from '@inertiajs/react';
import { FormEventHandler, useEffect, useMemo, useRef, useState } from 'react';
import PlatformLayout from '@/Components/Platform/PlatformLayout';
import { Badge, Card, EmptyState, InfoTooltip, PrimaryButton, StatCard } from '@/Components/Platform/ui';
import { CalendarIcon, ChecklistIcon, ChevronRightIcon, ClockIcon, PlusIcon, ViewColumnsIcon } from '@/Components/Platform/Icons';

interface Company {
    id: string;
    name: string;
    code: string;
}

interface Person {
    name: string;
    email: string;
}

interface Task {
    id: string;
    title: string;
    description: string | null;
    status: 'open' | 'in_progress' | 'done' | 'cancelled';
    priority: 'low' | 'medium' | 'high';
    due_date: string | null;
    // A meeting is a task with a specific time-of-day rather than just a
    // due date — kept as its own nullable field (not a separate
    // "is_meeting" flag) so "has a time" is the one thing that decides
    // whether a card reads as a scheduled meeting or a plain to-do.
    meeting_time: string | null;
    assignee_user_id: string | null;
    created_by: string;
    assignee: Person | null;
    creator: Person | null;
}

interface TaskKpiLink {
    id: string;
    task_id: string;
    kpi_id: string;
    kpis: { name: string } | null;
}

interface Kpi {
    id: string;
    name: string;
}

interface Member {
    user_id: string;
    users: Person;
}

interface TasksPageProps {
    company: Company;
    tasks: Task[];
    links: TaskKpiLink[];
    kpis: Kpi[];
    members: Member[];
    [key: string]: unknown;
}

const STATUS_COLUMNS: { key: Task['status']; label: string; dot: string }[] = [
    { key: 'open', label: 'To Do', dot: 'bg-slate-400' },
    { key: 'in_progress', label: 'In Progress', dot: 'bg-sky-500' },
    { key: 'done', label: 'Done', dot: 'bg-emerald-500' },
    { key: 'cancelled', label: 'Cancelled', dot: 'bg-red-400' },
];

const PRIORITY_BORDER: Record<Task['priority'], string> = {
    low: 'border-l-slate-300',
    medium: 'border-l-amber-400',
    high: 'border-l-red-400',
};

function initialsOf(name: string): string {
    const parts = name.trim().split(/\s+/);
    return ((parts[0]?.[0] ?? '') + (parts[1]?.[0] ?? '')).toUpperCase() || '?';
}

const AVATAR_COLORS = ['#0b1f49', '#0c7a52', '#92650b', '#0a6ea8', '#b3261e', '#6d28d9'];
function avatarColorFor(seed: string): string {
    let hash = 0;
    for (let i = 0; i < seed.length; i++) hash = (hash * 31 + seed.charCodeAt(i)) >>> 0;
    return AVATAR_COLORS[hash % AVATAR_COLORS.length];
}

function fmtTime12(hhmm: string | null): string | null {
    if (!hhmm) return null;
    const [h, m] = hhmm.split(':').map(Number);
    const period = h >= 12 ? 'PM' : 'AM';
    const h12 = h % 12 || 12;
    return `${h12}:${String(m).padStart(2, '0')} ${period}`;
}

function isoToday(): string {
    return new Date().toISOString().slice(0, 10);
}

function dueTone(dueDate: string): 'neutral' | 'info' | 'danger' {
    if (dueDate < isoToday()) return 'danger';
    if (dueDate === isoToday()) return 'info';
    return 'neutral';
}

function fmtDateShort(iso: string): string {
    return new Date(iso + 'T00:00:00').toLocaleDateString('en-GB', { day: 'numeric', month: 'short' });
}

function KpiCheckboxList({ kpis, selected, onToggle }: { kpis: Kpi[]; selected: string[]; onToggle: (kpiId: string) => void }) {
    if (kpis.length === 0) {
        return <p className="text-xs text-slate-400">No KPIs exist for this company yet.</p>;
    }

    return (
        <div className="max-h-40 overflow-y-auto rounded-lg border border-slate-200 p-2 space-y-1">
            {kpis.map((kpi) => (
                <label key={kpi.id} className="flex items-center gap-2 text-xs text-slate-700 px-1 py-0.5 rounded hover:bg-slate-50">
                    <input type="checkbox" checked={selected.includes(kpi.id)} onChange={() => onToggle(kpi.id)} />
                    {kpi.name}
                </label>
            ))}
        </div>
    );
}

function CreateTaskPanel({ companyId, kpis, members }: { companyId: string; kpis: Kpi[]; members: Member[] }) {
    const [open, setOpen] = useState(false);
    const [isMeeting, setIsMeeting] = useState(false);
    const { data, setData, post, processing, reset } = useForm({
        title: '',
        description: '',
        priority: 'medium',
        due_date: '',
        meeting_time: '',
        assignee_user_id: '',
        kpi_ids: [] as string[],
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(`/platform/companies/${companyId}/tasks`, {
            onSuccess: () => {
                reset();
                setIsMeeting(false);
                setOpen(false);
            },
        });
    };

    const toggleKpi = (kpiId: string) => {
        setData('kpi_ids', data.kpi_ids.includes(kpiId) ? data.kpi_ids.filter((id) => id !== kpiId) : [...data.kpi_ids, kpiId]);
    };

    if (!open) {
        return (
            <PrimaryButton onClick={() => setOpen(true)} className="inline-flex items-center gap-1.5">
                <PlusIcon className="w-4 h-4" /> New task
            </PrimaryButton>
        );
    }

    return (
        <form onSubmit={submit} className="grid grid-cols-2 gap-3 mb-5 bg-slate-50 rounded-xl p-4">
            <div className="col-span-2">
                <label className="block text-xs font-medium text-slate-600 mb-1">Task title</label>
                <input
                    value={data.title}
                    onChange={(e) => setData('title', e.target.value)}
                    className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                    placeholder="Follow up with client on renewal"
                    required
                />
            </div>
            <div className="col-span-2">
                <label className="block text-xs font-medium text-slate-600 mb-1">Description</label>
                <textarea
                    value={data.description}
                    onChange={(e) => setData('description', e.target.value)}
                    className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                    rows={2}
                />
            </div>
            <div>
                <label className="block text-xs font-medium text-slate-600 mb-1">Priority</label>
                <select value={data.priority} onChange={(e) => setData('priority', e.target.value)} className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="low">Low</option>
                    <option value="medium">Medium</option>
                    <option value="high">High</option>
                </select>
            </div>
            <div>
                <label className="block text-xs font-medium text-slate-600 mb-1">Due date</label>
                <input
                    type="date"
                    value={data.due_date}
                    onChange={(e) => setData('due_date', e.target.value)}
                    className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                />
            </div>
            <div className="col-span-2">
                <label className="flex items-center gap-2 text-xs font-semibold text-slate-700">
                    <input
                        type="checkbox"
                        checked={isMeeting}
                        onChange={(e) => {
                            setIsMeeting(e.target.checked);
                            if (!e.target.checked) setData('meeting_time', '');
                            else if (!data.meeting_time) setData('meeting_time', '10:00');
                        }}
                    />
                    This is a scheduled meeting — set a time
                </label>
                {isMeeting && (
                    <div className="mt-2">
                        <label className="block text-xs font-medium text-slate-600 mb-1">Meeting time</label>
                        <input
                            type="time"
                            value={data.meeting_time}
                            onChange={(e) => setData('meeting_time', e.target.value)}
                            className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                        />
                        <p className="text-[11px] text-slate-400 mt-1">Shown on the card and calendar as a time, so it reads apart from a plain due-date task.</p>
                    </div>
                )}
            </div>
            <div className="col-span-2">
                <label className="flex items-center gap-1.5 text-xs font-medium text-slate-600 mb-1">
                    Assign to
                </label>
                <select
                    value={data.assignee_user_id}
                    onChange={(e) => setData('assignee_user_id', e.target.value)}
                    className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                >
                    <option value="">Unassigned</option>
                    {members.map((m) => (
                        <option key={m.user_id} value={m.user_id}>
                            {m.users.name} ({m.users.email})
                        </option>
                    ))}
                </select>
            </div>
            <div className="col-span-2">
                <label className="flex items-center gap-1.5 text-xs font-medium text-slate-600 mb-1">
                    Link to KPI(s) — optional
                    <InfoTooltip text="Linking is for visibility only — it never changes a KPI's value." />
                </label>
                <KpiCheckboxList kpis={kpis} selected={data.kpi_ids} onToggle={toggleKpi} />
            </div>
            <div className="col-span-2 flex items-center gap-2">
                <PrimaryButton type="submit" disabled={processing}>
                    Create task
                </PrimaryButton>
                <button type="button" onClick={() => setOpen(false)} className="text-sm text-slate-400">
                    Cancel
                </button>
            </div>
        </form>
    );
}

function EditTaskForm({ companyId, task, onDone }: { companyId: string; task: Task; onDone: () => void }) {
    const [isMeeting, setIsMeeting] = useState(!!task.meeting_time);
    const { data, setData, patch, processing } = useForm({
        title: task.title,
        description: task.description ?? '',
        status: task.status,
        priority: task.priority,
        due_date: task.due_date ?? '',
        meeting_time: task.meeting_time ?? '',
        assignee_user_id: task.assignee_user_id ?? '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        patch(`/platform/companies/${companyId}/tasks/${task.id}`, { onSuccess: onDone });
    };

    return (
        <form onSubmit={submit} className="grid grid-cols-2 gap-3 mt-3 mb-2 bg-slate-50 rounded-xl p-4">
            <div className="col-span-2">
                <label className="block text-xs font-medium text-slate-600 mb-1">Task title</label>
                <input value={data.title} onChange={(e) => setData('title', e.target.value)} className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" required />
            </div>
            <div className="col-span-2">
                <label className="block text-xs font-medium text-slate-600 mb-1">Description</label>
                <textarea value={data.description} onChange={(e) => setData('description', e.target.value)} className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" rows={2} />
            </div>
            <div>
                <label className="block text-xs font-medium text-slate-600 mb-1">Status</label>
                <select value={data.status} onChange={(e) => setData('status', e.target.value as Task['status'])} className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="open">To Do</option>
                    <option value="in_progress">In progress</option>
                    <option value="done">Done</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            </div>
            <div>
                <label className="block text-xs font-medium text-slate-600 mb-1">Priority</label>
                <select value={data.priority} onChange={(e) => setData('priority', e.target.value as Task['priority'])} className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="low">Low</option>
                    <option value="medium">Medium</option>
                    <option value="high">High</option>
                </select>
            </div>
            <div>
                <label className="block text-xs font-medium text-slate-600 mb-1">Due date</label>
                <input type="date" value={data.due_date} onChange={(e) => setData('due_date', e.target.value)} className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
            </div>
            <div>
                <label className="flex items-center gap-1.5 text-xs font-medium text-slate-600 mb-1">
                    <input
                        type="checkbox"
                        checked={isMeeting}
                        onChange={(e) => {
                            setIsMeeting(e.target.checked);
                            if (!e.target.checked) setData('meeting_time', '');
                            else if (!data.meeting_time) setData('meeting_time', '10:00');
                        }}
                    />
                    Scheduled meeting
                </label>
                {isMeeting && (
                    <input
                        type="time"
                        value={data.meeting_time}
                        onChange={(e) => setData('meeting_time', e.target.value)}
                        className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                    />
                )}
            </div>
            <div className="col-span-2 flex items-center gap-2">
                <PrimaryButton type="submit" disabled={processing}>
                    Save changes
                </PrimaryButton>
                <button type="button" onClick={onDone} className="text-sm text-slate-400">
                    Cancel
                </button>
            </div>
        </form>
    );
}

function EditKpiLinksForm({ companyId, task, kpis, linkedKpiIds, onDone }: { companyId: string; task: Task; kpis: Kpi[]; linkedKpiIds: string[]; onDone: () => void }) {
    const [selected, setSelected] = useState<string[]>(linkedKpiIds);
    const [processing, setProcessing] = useState(false);

    const toggleKpi = (kpiId: string) => {
        setSelected((prev) => (prev.includes(kpiId) ? prev.filter((id) => id !== kpiId) : [...prev, kpiId]));
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        setProcessing(true);
        router.put(
            `/platform/companies/${companyId}/tasks/${task.id}/kpi-links`,
            { kpi_ids: selected },
            { onFinish: () => setProcessing(false), onSuccess: onDone },
        );
    };

    return (
        <form onSubmit={submit} className="mt-3 bg-slate-50 rounded-xl p-4">
            <KpiCheckboxList kpis={kpis} selected={selected} onToggle={toggleKpi} />
            <div className="flex items-center gap-2 mt-3">
                <PrimaryButton type="submit" disabled={processing}>
                    Save links
                </PrimaryButton>
                <button type="button" onClick={onDone} className="text-sm text-slate-400">
                    Cancel
                </button>
            </div>
        </form>
    );
}

function TaskCard({
    task,
    company,
    kpis,
    links,
    expanded,
    onToggleEdit,
    expandedLinks,
    onToggleLinks,
    draggable,
}: {
    task: Task;
    company: Company;
    kpis: Kpi[];
    links: TaskKpiLink[];
    expanded: boolean;
    onToggleEdit: () => void;
    expandedLinks: boolean;
    onToggleLinks: () => void;
    draggable: boolean;
}) {
    const taskLinks = links.filter((l) => l.task_id === task.id);

    const destroy = () => {
        if (confirm(`Delete "${task.title}"? This cannot be undone.`)) {
            router.delete(`/platform/companies/${company.id}/tasks/${task.id}`);
        }
    };

    return (
        <div
            id={`task-${task.id}`}
            draggable={draggable}
            onDragStart={(e) => e.dataTransfer.setData('text/plain', task.id)}
            className={`bg-white rounded-xl border border-slate-200 shadow-sm px-3.5 py-3 border-l-4 ${PRIORITY_BORDER[task.priority]} ${draggable ? 'cursor-grab active:cursor-grabbing' : ''} ${expanded ? 'ring-2 ring-brand-100' : ''}`}
        >
            <p className="text-[13px] font-bold text-slate-800 leading-snug">{task.title}</p>
            {task.description && <p className="text-xs text-slate-500 mt-1">{task.description}</p>}

            <div className="flex items-center justify-between gap-2 mt-2.5 flex-wrap">
                <div className="flex items-center gap-1.5 flex-wrap">
                    <span
                        className="w-5 h-5 rounded-full flex items-center justify-center text-[9px] font-extrabold text-white flex-none"
                        style={{ background: task.assignee ? avatarColorFor(task.assignee.email) : '#94a3b8' }}
                        title={task.assignee ? task.assignee.name : 'Unassigned'}
                    >
                        {task.assignee ? initialsOf(task.assignee.name) : '—'}
                    </span>
                    {task.meeting_time ? (
                        <span className="inline-flex items-center gap-1 text-[10px] font-bold px-1.5 py-0.5 rounded-full bg-brand-100 text-brand-900">
                            <ClockIcon className="w-2.5 h-2.5" /> {fmtTime12(task.meeting_time)}
                        </span>
                    ) : task.due_date ? (
                        <Badge tone={dueTone(task.due_date) === 'danger' ? 'danger' : dueTone(task.due_date) === 'info' ? 'info' : 'neutral'}>
                            {fmtDateShort(task.due_date)}
                        </Badge>
                    ) : null}
                </div>
                {task.meeting_time && task.due_date && (
                    <span className="text-[10px] font-semibold text-slate-400">{fmtDateShort(task.due_date)}</span>
                )}
            </div>

            {taskLinks.length > 0 && (
                <div className="flex flex-wrap items-center gap-1.5 mt-2">
                    {taskLinks.map((l) => (
                        <Badge key={l.id} tone="brand">
                            {l.kpis?.name ?? 'KPI'}
                        </Badge>
                    ))}
                </div>
            )}

            <div className="flex items-center gap-3 mt-2.5 pt-2 border-t border-dashed border-slate-100">
                <button onClick={onToggleLinks} className="text-[11px] font-semibold text-brand-800 hover:underline">
                    {expandedLinks ? 'Close links' : 'KPI links'}
                </button>
                <button onClick={onToggleEdit} className="text-[11px] font-semibold text-brand-800 hover:underline">
                    {expanded ? 'Close' : 'Edit'}
                </button>
                <button onClick={destroy} className="text-[11px] font-semibold text-red-600 hover:underline">
                    Delete
                </button>
            </div>

            {expanded && <EditTaskForm companyId={company.id} task={task} onDone={onToggleEdit} />}
            {expandedLinks && (
                <EditKpiLinksForm
                    companyId={company.id}
                    task={task}
                    kpis={kpis}
                    linkedKpiIds={taskLinks.map((l) => l.kpi_id)}
                    onDone={onToggleLinks}
                />
            )}
        </div>
    );
}

function Board({
    tasks,
    company,
    kpis,
    links,
    expandedTaskId,
    setExpandedTaskId,
    expandedLinksId,
    setExpandedLinksId,
}: {
    tasks: Task[];
    company: Company;
    kpis: Kpi[];
    links: TaskKpiLink[];
    expandedTaskId: string | null;
    setExpandedTaskId: (id: string | null) => void;
    expandedLinksId: string | null;
    setExpandedLinksId: (id: string | null) => void;
}) {
    const [dragOverCol, setDragOverCol] = useState<Task['status'] | null>(null);

    const moveTask = (task: Task, newStatus: Task['status']) => {
        if (task.status === newStatus) return;
        router.patch(
            `/platform/companies/${company.id}/tasks/${task.id}`,
            {
                title: task.title,
                description: task.description ?? '',
                status: newStatus,
                priority: task.priority,
                due_date: task.due_date ?? '',
                meeting_time: task.meeting_time ?? '',
                assignee_user_id: task.assignee_user_id ?? '',
            },
            { preserveScroll: true },
        );
    };

    return (
        <div className="grid grid-flow-col auto-cols-[minmax(260px,1fr)] gap-3.5 overflow-x-auto pb-2">
            {STATUS_COLUMNS.map((col) => {
                const items = tasks.filter((t) => t.status === col.key);
                return (
                    <div
                        key={col.key}
                        onDragOver={(e) => {
                            e.preventDefault();
                            setDragOverCol(col.key);
                        }}
                        onDragLeave={() => setDragOverCol((c) => (c === col.key ? null : c))}
                        onDrop={(e) => {
                            e.preventDefault();
                            setDragOverCol(null);
                            const id = e.dataTransfer.getData('text/plain');
                            const task = tasks.find((t) => t.id === id);
                            if (task) moveTask(task, col.key);
                        }}
                        className={`rounded-2xl border p-3 flex flex-col gap-2.5 min-h-40 transition-colors ${dragOverCol === col.key ? 'border-brand-800 bg-brand-50' : 'border-slate-200 bg-slate-50'}`}
                    >
                        <div className="flex items-center justify-between px-1">
                            <span className="flex items-center gap-2 text-[11px] font-extrabold uppercase tracking-wide text-slate-600">
                                <span className={`w-2 h-2 rounded-full ${col.dot}`} />
                                {col.label}
                            </span>
                            <span className="text-[10px] font-bold text-slate-400 bg-white border border-slate-200 rounded-full px-2 py-0.5 tabular-nums">
                                {items.length}
                            </span>
                        </div>
                        <div className="flex flex-col gap-2.5">
                            {items.length === 0 ? (
                                <div className="text-[11px] text-slate-400 text-center border border-dashed border-slate-300 rounded-lg py-5">
                                    Nothing here
                                </div>
                            ) : (
                                items.map((t) => (
                                    <TaskCard
                                        key={t.id}
                                        task={t}
                                        company={company}
                                        kpis={kpis}
                                        links={links}
                                        draggable
                                        expanded={expandedTaskId === t.id}
                                        onToggleEdit={() => setExpandedTaskId(expandedTaskId === t.id ? null : t.id)}
                                        expandedLinks={expandedLinksId === t.id}
                                        onToggleLinks={() => setExpandedLinksId(expandedLinksId === t.id ? null : t.id)}
                                    />
                                ))
                            )}
                        </div>
                    </div>
                );
            })}
        </div>
    );
}

function CalendarView({
    tasks,
    onPick,
}: {
    tasks: Task[];
    onPick: (taskId: string) => void;
}) {
    const [ref, setRef] = useState(() => {
        const d = new Date();
        return new Date(d.getFullYear(), d.getMonth(), 1);
    });

    const cells = useMemo(() => {
        const year = ref.getFullYear();
        const month = ref.getMonth();
        const firstDow = new Date(year, month, 1).getDay();
        const daysInMonth = new Date(year, month + 1, 0).getDate();
        const daysInPrev = new Date(year, month, 0).getDate();
        const out: { n: number; pad: boolean; iso?: string }[] = [];
        for (let i = firstDow - 1; i >= 0; i--) out.push({ n: daysInPrev - i, pad: true });
        for (let d = 1; d <= daysInMonth; d++) {
            const iso = new Date(year, month, d).toISOString().slice(0, 10);
            out.push({ n: d, pad: false, iso });
        }
        while (out.length % 7 !== 0) out.push({ n: out.length, pad: true });
        return out;
    }, [ref]);

    const monthLabel = ref.toLocaleDateString('en-GB', { month: 'long', year: 'numeric' });
    const today = isoToday();

    return (
        <div className="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
            <div className="flex items-center justify-between px-4 py-3.5 border-b border-slate-200">
                <span className="text-sm font-extrabold text-slate-800">{monthLabel}</span>
                <div className="flex items-center gap-1">
                    <button
                        onClick={() => setRef(new Date(ref.getFullYear(), ref.getMonth() - 1, 1))}
                        className="w-7 h-7 rounded-lg border border-slate-300 flex items-center justify-center text-slate-600 hover:bg-slate-50"
                        aria-label="Previous month"
                    >
                        <ChevronRightIcon className="w-3.5 h-3.5 rotate-180" />
                    </button>
                    <button
                        onClick={() => setRef(new Date(ref.getFullYear(), ref.getMonth() + 1, 1))}
                        className="w-7 h-7 rounded-lg border border-slate-300 flex items-center justify-center text-slate-600 hover:bg-slate-50"
                        aria-label="Next month"
                    >
                        <ChevronRightIcon className="w-3.5 h-3.5" />
                    </button>
                </div>
            </div>
            <div className="grid grid-cols-7 border-b border-slate-200">
                {['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'].map((d) => (
                    <span key={d} className="text-center text-[10px] font-extrabold uppercase tracking-wide text-slate-400 py-2">
                        {d}
                    </span>
                ))}
            </div>
            <div className="grid grid-cols-7">
                {cells.map((c, i) => {
                    if (c.pad) return <div key={i} className="min-h-26 border-r border-b border-slate-100 bg-slate-50/60 p-1.5" />;
                    const items = tasks
                        .filter((t) => t.due_date === c.iso && t.status !== 'cancelled')
                        .sort((a, b) => (a.meeting_time ?? '99:99').localeCompare(b.meeting_time ?? '99:99'));
                    const shown = items.slice(0, 3);
                    const more = items.length - shown.length;
                    const isToday = c.iso === today;
                    return (
                        <div key={i} className="min-h-26 border-r border-b border-slate-100 p-1.5 flex flex-col gap-1 last:border-r-0">
                            <span
                                className={`text-[11px] font-bold tabular-nums w-5 h-5 flex items-center justify-center rounded-full ${isToday ? 'bg-brand-900 text-white' : 'text-slate-400'}`}
                            >
                                {c.n}
                            </span>
                            {shown.map((t) => (
                                <button
                                    key={t.id}
                                    onClick={() => onPick(t.id)}
                                    className={`text-left text-[10px] font-bold px-1.5 py-0.5 rounded-md truncate flex items-center gap-1 ${
                                        t.status === 'done' ? 'bg-emerald-100 text-emerald-700' : t.status === 'in_progress' ? 'bg-sky-100 text-sky-700' : 'bg-slate-100 text-slate-600'
                                    }`}
                                >
                                    {t.meeting_time && <span className="tabular-nums opacity-80">{fmtTime12(t.meeting_time)?.replace(' ', '')}</span>}
                                    <span className="truncate">{t.title}</span>
                                </button>
                            ))}
                            {more > 0 && <span className="text-[9px] font-bold text-slate-400 pl-1">+{more} more</span>}
                        </div>
                    );
                })}
            </div>
        </div>
    );
}

export default function TasksIndex({ company, tasks, links, kpis, members }: TasksPageProps) {
    const [view, setView] = useState<'board' | 'calendar'>('board');
    const [expandedTaskId, setExpandedTaskId] = useState<string | null>(null);
    const [expandedLinksId, setExpandedLinksId] = useState<string | null>(null);
    const focusRef = useRef<string | null>(null);

    useEffect(() => {
        if (view === 'board' && focusRef.current) {
            const id = focusRef.current;
            focusRef.current = null;
            requestAnimationFrame(() => {
                document.getElementById(`task-${id}`)?.scrollIntoView({ behavior: 'smooth', block: 'center' });
            });
        }
    }, [view]);

    const pickFromCalendar = (taskId: string) => {
        focusRef.current = taskId;
        setExpandedTaskId(taskId);
        setView('board');
    };

    const today = isoToday();
    const weekEnd = useMemo(() => {
        const d = new Date();
        d.setDate(d.getDate() + 7);
        return d.toISOString().slice(0, 10);
    }, []);

    const stats = useMemo(() => {
        const openCount = tasks.filter((t) => t.status !== 'done' && t.status !== 'cancelled').length;
        const dueToday = tasks.filter((t) => t.due_date === today && t.status !== 'done' && t.status !== 'cancelled').length;
        const overdue = tasks.filter((t) => !!t.due_date && t.due_date < today && t.status !== 'done' && t.status !== 'cancelled').length;
        const meetings = tasks.filter((t) => t.meeting_time && !!t.due_date && t.due_date >= today && t.due_date! <= weekEnd && t.status !== 'cancelled').length;
        return { openCount, dueToday, overdue, meetings };
    }, [tasks, today, weekEnd]);

    return (
        <PlatformLayout
            title="Things To Do"
            description="Day-to-day work and meetings for this company, optionally linked to a KPI for visibility — linking never changes a KPI's value."
            company={company}
        >
            <div className="grid grid-cols-2 md:grid-cols-4 gap-2.5 mb-5">
                <StatCard label="Open items" value={stats.openCount} />
                <StatCard label="Due today" value={stats.dueToday} tone={stats.dueToday > 0 ? 'warning' : 'default'} />
                <StatCard label="Overdue" value={stats.overdue} tone={stats.overdue > 0 ? 'danger' : 'default'} />
                <StatCard label="Meetings this week" value={stats.meetings} />
            </div>

            <Card>
                <div className="flex flex-wrap items-center justify-between gap-3 mb-2">
                    <CreateTaskPanel companyId={company.id} kpis={kpis} members={members} />
                    <div className="inline-flex p-1 bg-slate-100 rounded-xl border border-slate-200 gap-0.5">
                        <button
                            onClick={() => setView('board')}
                            className={`inline-flex items-center gap-1.5 text-xs font-bold px-3 py-1.5 rounded-lg transition-colors ${view === 'board' ? 'bg-white text-brand-900 shadow-sm' : 'text-slate-500'}`}
                        >
                            <ViewColumnsIcon className="w-3.5 h-3.5" /> Board
                        </button>
                        <button
                            onClick={() => setView('calendar')}
                            className={`inline-flex items-center gap-1.5 text-xs font-bold px-3 py-1.5 rounded-lg transition-colors ${view === 'calendar' ? 'bg-white text-brand-900 shadow-sm' : 'text-slate-500'}`}
                        >
                            <CalendarIcon className="w-3.5 h-3.5" /> Calendar
                        </button>
                    </div>
                </div>

                {tasks.length === 0 ? (
                    <EmptyState
                        icon={<ChecklistIcon className="w-10 h-10" />}
                        title="No tasks yet"
                        description="Create one above to start tracking day-to-day work, optionally aligned to a KPI."
                    />
                ) : view === 'board' ? (
                    <Board
                        tasks={tasks}
                        company={company}
                        kpis={kpis}
                        links={links}
                        expandedTaskId={expandedTaskId}
                        setExpandedTaskId={setExpandedTaskId}
                        expandedLinksId={expandedLinksId}
                        setExpandedLinksId={setExpandedLinksId}
                    />
                ) : (
                    <CalendarView tasks={tasks} onPick={pickFromCalendar} />
                )}
            </Card>
        </PlatformLayout>
    );
}

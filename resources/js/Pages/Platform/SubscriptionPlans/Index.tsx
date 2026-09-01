import { router, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';
import PlatformLayout from '@/Components/Platform/PlatformLayout';
import { Badge, Card, EmptyState, InfoTooltip, PrimaryButton, SecondaryButton } from '@/Components/Platform/ui';
import { CreditCardIcon } from '@/Components/Platform/Icons';

interface Plan {
    id: string;
    name: string;
    price_cents: number;
    billing_period: 'monthly' | 'yearly';
    max_users: number | null;
    max_departments: number | null;
    is_active: boolean;
}

interface SubscriptionPlansPageProps {
    plans: Plan[];
    companyCounts: Record<string, number>;
    [key: string]: unknown;
}

function formatPrice(cents: number, period: string) {
    return `RM${(cents / 100).toFixed(2)} / ${period === 'yearly' ? 'year' : 'month'}`;
}

function CreatePlanForm() {
    const { data, setData, post, processing, reset } = useForm({
        name: '',
        price_cents: '',
        billing_period: 'monthly',
        max_users: '',
        max_departments: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post('/platform/subscription-plans', { onSuccess: () => reset() });
    };

    return (
        <form onSubmit={submit} className="flex items-end gap-3 flex-wrap">
            <div>
                <label className="block text-xs font-medium text-slate-600 mb-1">Plan name</label>
                <input
                    value={data.name}
                    onChange={(e) => setData('name', e.target.value)}
                    className="w-40 rounded-lg border border-slate-300 px-3 py-2 text-sm"
                    placeholder="Pro"
                    required
                />
            </div>
            <div>
                <label className="block text-xs font-medium text-slate-600 mb-1">Price (RM cents)</label>
                <input
                    value={data.price_cents}
                    onChange={(e) => setData('price_cents', e.target.value)}
                    type="number"
                    min={0}
                    className="w-32 rounded-lg border border-slate-300 px-3 py-2 text-sm"
                    placeholder="9900"
                    required
                />
            </div>
            <div>
                <label className="block text-xs font-medium text-slate-600 mb-1">Billing period</label>
                <select
                    value={data.billing_period}
                    onChange={(e) => setData('billing_period', e.target.value)}
                    className="rounded-lg border border-slate-300 px-3 py-2 text-sm"
                >
                    <option value="monthly">Monthly</option>
                    <option value="yearly">Yearly</option>
                </select>
            </div>
            <div>
                <label className="block text-xs font-medium text-slate-600 mb-1">Max users</label>
                <input
                    value={data.max_users}
                    onChange={(e) => setData('max_users', e.target.value)}
                    type="number"
                    min={1}
                    className="w-24 rounded-lg border border-slate-300 px-3 py-2 text-sm"
                    placeholder="Unlimited"
                />
            </div>
            <div>
                <label className="block text-xs font-medium text-slate-600 mb-1">Max departments</label>
                <input
                    value={data.max_departments}
                    onChange={(e) => setData('max_departments', e.target.value)}
                    type="number"
                    min={1}
                    className="w-28 rounded-lg border border-slate-300 px-3 py-2 text-sm"
                    placeholder="Unlimited"
                />
            </div>
            <PrimaryButton type="submit" disabled={processing}>
                Create plan
            </PrimaryButton>
        </form>
    );
}

function PlanRow({ plan, companyCount }: { plan: Plan; companyCount: number }) {
    const [editing, setEditing] = useState(false);
    const { data, setData, patch, processing } = useForm({
        name: plan.name,
        price_cents: String(plan.price_cents),
        billing_period: plan.billing_period,
        max_users: plan.max_users !== null ? String(plan.max_users) : '',
        max_departments: plan.max_departments !== null ? String(plan.max_departments) : '',
        is_active: plan.is_active,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        patch(`/platform/subscription-plans/${plan.id}`, { onSuccess: () => setEditing(false) });
    };

    const deletePlan = () => {
        if (confirm(`Delete the "${plan.name}" plan? This only works if no company is currently on it.`)) {
            router.delete(`/platform/subscription-plans/${plan.id}`);
        }
    };

    if (editing) {
        return (
            <li className="py-4">
                <form onSubmit={submit} className="flex items-end gap-3 flex-wrap">
                    <input
                        value={data.name}
                        onChange={(e) => setData('name', e.target.value)}
                        className="w-36 rounded-lg border border-slate-300 px-3 py-1.5 text-sm"
                        required
                    />
                    <input
                        value={data.price_cents}
                        onChange={(e) => setData('price_cents', e.target.value)}
                        type="number"
                        min={0}
                        className="w-28 rounded-lg border border-slate-300 px-3 py-1.5 text-sm"
                        required
                    />
                    <select
                        value={data.billing_period}
                        onChange={(e) => setData('billing_period', e.target.value as 'monthly' | 'yearly')}
                        className="rounded-lg border border-slate-300 px-2 py-1.5 text-sm"
                    >
                        <option value="monthly">Monthly</option>
                        <option value="yearly">Yearly</option>
                    </select>
                    <input
                        value={data.max_users}
                        onChange={(e) => setData('max_users', e.target.value)}
                        type="number"
                        min={1}
                        placeholder="Max users"
                        className="w-24 rounded-lg border border-slate-300 px-3 py-1.5 text-sm"
                    />
                    <input
                        value={data.max_departments}
                        onChange={(e) => setData('max_departments', e.target.value)}
                        type="number"
                        min={1}
                        placeholder="Max depts"
                        className="w-24 rounded-lg border border-slate-300 px-3 py-1.5 text-sm"
                    />
                    <label className="flex items-center gap-1.5 text-xs font-semibold text-slate-600">
                        <input type="checkbox" checked={data.is_active} onChange={(e) => setData('is_active', e.target.checked)} />
                        Active
                    </label>
                    <PrimaryButton type="submit" disabled={processing} className="text-xs px-3 py-1.5">
                        Save
                    </PrimaryButton>
                    <SecondaryButton type="button" onClick={() => setEditing(false)} className="text-xs px-3 py-1.5">
                        Cancel
                    </SecondaryButton>
                </form>
            </li>
        );
    }

    return (
        <li className="py-4 flex items-center justify-between">
            <div>
                <div className="flex items-center gap-2">
                    <p className="text-sm font-semibold text-slate-800">{plan.name}</p>
                    <Badge tone={plan.is_active ? 'success' : 'neutral'}>{plan.is_active ? 'Active' : 'Inactive'}</Badge>
                </div>
                <p className="text-xs text-slate-400 mt-0.5">
                    {formatPrice(plan.price_cents, plan.billing_period)}
                    {plan.max_users ? ` · up to ${plan.max_users} users` : ''}
                    {plan.max_departments ? ` · up to ${plan.max_departments} departments` : ''}
                    {' · '}
                    {companyCount} {companyCount === 1 ? 'company' : 'companies'} on this plan
                </p>
            </div>
            <div className="flex items-center gap-3">
                <button onClick={() => setEditing(true)} className="text-xs font-semibold text-brand-900 hover:underline">
                    Edit
                </button>
                <button onClick={deletePlan} className="text-xs font-semibold text-red-600 hover:underline">
                    Delete
                </button>
            </div>
        </li>
    );
}

export default function SubscriptionPlansIndex({ plans, companyCounts }: SubscriptionPlansPageProps) {
    return (
        <PlatformLayout
            title="Subscription Plans"
            description={
                <span className="inline-flex items-center gap-1.5">
                    {'The catalog every subscribed company is assigned from'}
                    <InfoTooltip text="Manage plan names, pricing, and limits here. Assign a plan to a specific company from the Companies page." />
                </span>
            }
        >
            <Card title="New plan" className="mb-6">
                <CreatePlanForm />
            </Card>

            {plans.length === 0 ? (
                <Card>
                    <EmptyState icon={<CreditCardIcon className="w-10 h-10" />} title="No plans yet" description="Create one above before assigning it to a company." />
                </Card>
            ) : (
                <Card>
                    <ul className="divide-y divide-slate-100">
                        {plans.map((plan) => (
                            <PlanRow key={plan.id} plan={plan} companyCount={companyCounts[plan.id] ?? 0} />
                        ))}
                    </ul>
                </Card>
            )}
        </PlatformLayout>
    );
}

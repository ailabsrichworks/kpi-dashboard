import { Bar } from 'react-chartjs-2';
import '@/lib/chartSetup';
import { Card, EmptyState } from './ui';

export interface TrendPoint {
    label: string;
    value: number | null;
}

/**
 * The chart half of `CompanyDashboard`'s `achievement_trend` widget,
 * generalized to a plain `{label, value}[]` shape so the CEO performance
 * view and department drill-downs can reuse it without duplicating the
 * Chart.js setup.
 */
export default function PerformanceTrend({
    title = 'Achievement Trend',
    description,
    points,
}: {
    title?: string;
    description?: string;
    points: TrendPoint[];
}) {
    const usable = points.filter((p) => p.value !== null);

    if (usable.length === 0) {
        return (
            <Card title={title}>
                <EmptyState title="No approved submissions in recent periods yet" />
            </Card>
        );
    }

    return (
        <Card title={title} description={description}>
            <div style={{ height: 180, position: 'relative' }}>
                <Bar
                    data={{
                        labels: usable.map((p) => p.label),
                        datasets: [
                            {
                                label: 'Avg. achievement',
                                data: usable.map((p) => p.value as number),
                                backgroundColor: '#0b1f49bb',
                                borderColor: '#0b1f49',
                                borderWidth: 1.5,
                                borderRadius: 4,
                            },
                        ],
                    }}
                    options={{
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false },
                            tooltip: { callbacks: { label: (c) => ` ${(c.parsed as { y: number }).y.toFixed(1)}%` } },
                        },
                        scales: {
                            x: { ticks: { font: { size: 11, weight: 'bold' } }, grid: { display: false } },
                            y: { min: 0, ticks: { callback: (v) => v + '%', font: { size: 10 } }, grid: { color: '#f1f5f9' } },
                        },
                    }}
                />
            </div>
        </Card>
    );
}

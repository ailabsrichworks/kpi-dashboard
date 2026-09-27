import { Bar } from 'react-chartjs-2';
import '../../lib/chartSetup';

interface DeptChartRow {
    code: string;
    q1: number;
    q2: number;
    q3: number;
    q4: number;
}

interface QuarterlyTrendChartProps {
    currentFinancialYear: string;
    deptChartData: DeptChartRow[];
}

const PALETTE = ['#3b82f6', '#8b5cf6', '#f59e0b', '#10b981', '#ef4444', '#06b6d4', '#f97316', '#ec4899', '#14b8a6', '#a855f7'];

export default function QuarterlyTrendChart({ currentFinancialYear, deptChartData }: QuarterlyTrendChartProps) {
    if (deptChartData.length === 0) return null;

    const datasets = deptChartData.map((d, i) => ({
        label: d.code,
        data: [d.q1, d.q2, d.q3, d.q4],
        backgroundColor: PALETTE[i % PALETTE.length] + 'bb',
        borderColor: PALETTE[i % PALETTE.length],
        borderWidth: 1.5,
        borderRadius: 4,
    }));

    return (
        <div className="bg-white rounded-2xl p-4 shadow-sm border border-[#E5E7EB] border-t-[3px] border-t-[#D4AF37]">
            <h3 className="text-xs font-black text-slate-900">Quarterly Performance — All Departments</h3>
            <p className="text-[10px] text-slate-400 mt-0.5 mb-3">Q1 → Q4 avg score per dept · {currentFinancialYear}</p>
            <div style={{ height: 130, position: 'relative' }}>
                <Bar
                    data={{ labels: ['Q1', 'Q2', 'Q3', 'Q4'], datasets }}
                    options={{
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { position: 'bottom' as const, labels: { font: { size: 10 }, boxWidth: 12 } },
                            tooltip: { callbacks: { label: (c) => ` ${c.dataset.label}: ${(c.parsed as { y: number }).y.toFixed(1)}%` } },
                        },
                        scales: {
                            x: { ticks: { font: { size: 11, weight: 'bold' } }, grid: { display: false } },
                            y: { min: 0, max: 100, ticks: { callback: (v) => v + '%', font: { size: 10 } }, grid: { color: '#f1f5f9' } },
                        },
                    }}
                />
            </div>
        </div>
    );
}

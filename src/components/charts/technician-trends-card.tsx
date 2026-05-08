'use client';

import { useMemo, useState } from 'react';
import {
  LineChart, Line, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer,
} from 'recharts';
import { differenceInDays } from 'date-fns';
import { DEFAULT_KPI_DEFINITIONS } from '@/lib/constants/kpi-defaults';
import {
  useTechnicianTrend, defaultGranularity, type TrendGranularity,
} from '@/lib/hooks/use-technician-trend';
import { useDashboardStore } from '@/lib/store/dashboard-store';
import { formatKpiValue } from '@/lib/utils/format';

const GRAN_OPTIONS: { value: TrendGranularity; label: string }[] = [
  { value: 'day', label: 'Daily' },
  { value: 'week', label: 'Weekly' },
  { value: 'month', label: 'Monthly' },
];

interface Props {
  technicianId: string;
}

export function TechnicianTrendsCard({ technicianId }: Props) {
  const { dateRange } = useDashboardStore();
  const spanDays = useMemo(
    () => differenceInDays(dateRange.to, dateRange.from) + 1,
    [dateRange],
  );

  const activeKpis = DEFAULT_KPI_DEFINITIONS.filter(d => d.isActive);
  const [kpiId, setKpiId] = useState(activeKpis[0].id);
  const [override, setOverride] = useState<TrendGranularity | null>(null);
  const granularity: TrendGranularity = override ?? defaultGranularity(spanDays);

  const def = activeKpis.find(d => d.id === kpiId) ?? activeKpis[0];
  const data = useTechnicianTrend(technicianId, def.formula, granularity);

  const summary = useMemo(() => {
    if (data.length === 0) return { total: 0, avg: 0, max: 0 };
    const values = data.map(d => d.value);
    const total = values.reduce((s, v) => s + v, 0);
    const max = Math.max(...values);
    return { total, avg: total / values.length, max };
  }, [data]);

  const yTickFormatter = (v: number) => {
    if (def.displayFormat === 'currency') {
      return v >= 1000 ? `$${(v / 1000).toFixed(1)}k` : `$${v.toFixed(0)}`;
    }
    if (def.displayFormat === 'percentage') return `${v.toFixed(0)}%`;
    return v.toLocaleString();
  };

  const granLabel = granularity === 'day' ? 'day' : granularity === 'week' ? 'week' : 'month';

  return (
    <section className="bg-surface-container-lowest p-6 md:p-8 rounded-2xl card-shadow">
      <div className="flex flex-col md:flex-row md:items-end md:justify-between gap-4 mb-6">
        <div>
          <p className="text-secondary font-semibold tracking-wider text-xs uppercase mb-1">
            Performance Trend
          </p>
          <h3 className="font-headline font-bold text-2xl md:text-3xl text-primary">
            {def.name}
          </h3>
          <p className="text-on-surface-variant text-sm mt-1 max-w-xl">
            {def.description}
          </p>
        </div>
        <div className="flex flex-wrap items-center gap-3">
          <select
            value={kpiId}
            onChange={(e) => setKpiId(e.target.value)}
            aria-label="Select KPI"
            className="bg-surface-container px-4 py-2 rounded-lg border border-outline-variant/30 text-sm font-semibold text-on-surface focus:outline-none focus:ring-2 focus:ring-primary cursor-pointer"
          >
            {activeKpis.map(d => (
              <option key={d.id} value={d.id}>{d.name}</option>
            ))}
          </select>
          <div
            role="group"
            aria-label="Bucket granularity"
            className="inline-flex rounded-lg overflow-hidden border border-outline-variant/30"
          >
            {GRAN_OPTIONS.map(opt => (
              <button
                key={opt.value}
                onClick={() => setOverride(opt.value)}
                className={`px-3 py-2 text-sm font-semibold transition-colors ${
                  granularity === opt.value
                    ? 'bg-primary text-white'
                    : 'bg-surface-container text-on-surface hover:bg-surface-container-high'
                }`}
              >
                {opt.label}
              </button>
            ))}
          </div>
        </div>
      </div>

      {/* Summary stats */}
      <div className="grid grid-cols-2 md:grid-cols-3 gap-3 md:gap-4 mb-6">
        <div className="p-4 rounded-xl bg-surface-container">
          <p className="text-[10px] md:text-xs uppercase font-bold tracking-wider text-on-surface-variant">
            Period total
          </p>
          <p className="text-xl md:text-2xl font-bold text-primary mt-1">
            {formatKpiValue(summary.total, def.displayFormat)}
          </p>
        </div>
        <div className="p-4 rounded-xl bg-surface-container">
          <p className="text-[10px] md:text-xs uppercase font-bold tracking-wider text-on-surface-variant">
            Avg per {granLabel}
          </p>
          <p className="text-xl md:text-2xl font-bold text-primary mt-1">
            {formatKpiValue(summary.avg, def.displayFormat)}
          </p>
        </div>
        <div className="p-4 rounded-xl bg-surface-container col-span-2 md:col-span-1">
          <p className="text-[10px] md:text-xs uppercase font-bold tracking-wider text-on-surface-variant">
            Best {granLabel}
          </p>
          <p className="text-xl md:text-2xl font-bold text-primary mt-1">
            {formatKpiValue(summary.max, def.displayFormat)}
          </p>
        </div>
      </div>

      {/* Chart */}
      <div className="h-72">
        {data.length === 0 ? (
          <div className="h-full flex items-center justify-center text-on-surface-variant text-sm">
            No data in selected range.
          </div>
        ) : (
          <ResponsiveContainer width="100%" height="100%">
            <LineChart data={data} margin={{ top: 10, right: 16, left: 0, bottom: 0 }}>
              <CartesianGrid strokeDasharray="3 3" stroke="#F5F6FA" />
              <XAxis
                dataKey="name"
                tick={{ fontSize: 12, fill: '#3B445C' }}
                tickMargin={8}
                interval="preserveStartEnd"
              />
              <YAxis
                tick={{ fontSize: 12, fill: '#3B445C' }}
                tickFormatter={yTickFormatter}
                width={70}
              />
              <Tooltip
                contentStyle={{ borderRadius: 8, border: 'none', boxShadow: '0 4px 12px rgba(0,0,0,0.1)' }}
                formatter={(v) => [formatKpiValue(Number(v), def.displayFormat), def.name]}
              />
              <Line
                type="monotone"
                dataKey="value"
                stroke="#012650"
                strokeWidth={2.5}
                dot={{ r: 3, fill: '#012650' }}
                activeDot={{ r: 5 }}
              />
            </LineChart>
          </ResponsiveContainer>
        )}
      </div>

      {/* Per-bucket table */}
      {data.length > 0 && (
        <div className="mt-6 overflow-x-auto">
          <table className="w-full text-sm">
            <thead>
              <tr className="text-left text-[11px] uppercase tracking-wider text-on-surface-variant border-b border-outline-variant/30">
                <th className="pb-2 font-bold">Period</th>
                <th className="pb-2 font-bold text-right">{def.name}</th>
                <th className="pb-2 font-bold text-right">vs Target</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-outline-variant/20">
              {data.map(point => {
                const ratio = def.target > 0 ? point.value / def.target : 0;
                const meets = def.invertedStatus ? ratio <= 1 && ratio > 0 : ratio >= 1;
                return (
                  <tr key={point.bucketStart.toISOString()}>
                    <td className="py-2 font-medium text-on-surface">{point.name}</td>
                    <td className="py-2 text-right font-semibold text-primary">
                      {formatKpiValue(point.value, def.displayFormat)}
                    </td>
                    <td className={`py-2 text-right font-semibold ${
                      def.target === 0
                        ? 'text-on-surface-variant'
                        : meets
                          ? 'text-success'
                          : 'text-danger'
                    }`}>
                      {def.target === 0 ? '—' : `${(ratio * 100).toFixed(0)}%`}
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      )}
    </section>
  );
}

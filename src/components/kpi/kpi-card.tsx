'use client';

import { Card } from '@/components/ui/card';
import { AnimatedNumber } from './animated-number';
import { Sparkline } from './sparkline';
import { KpiCardSkeleton } from '@/components/ui/skeleton';
import { formatKpiValue } from '@/lib/utils/format';
import { statusColor, statusColorInverse, colors } from '@/lib/constants/brand';
import type { KpiValue } from '@/types/kpi';

interface KpiCardProps {
  kpi: KpiValue;
  isLoading?: boolean;
}

export function KpiCard({ kpi, isLoading }: KpiCardProps) {
  if (isLoading) return <KpiCardSkeleton />;

  const getStatus = kpi.invertedStatus ? statusColorInverse : statusColor;
  const statusKey = getStatus(kpi.value, kpi.target);
  const status = (statusKey === 'success' || statusKey === 'warning' || statusKey === 'danger') ? statusKey : 'success' as const;

  const trend = kpi.previousValue !== undefined && kpi.previousValue !== 0
    ? ((kpi.value - kpi.previousValue) / kpi.previousValue) * 100
    : undefined;

  const trendIsGood = kpi.invertedStatus
    ? (trend !== undefined && trend < 0)
    : (trend !== undefined && trend > 0);

  const sparklineColor = status === 'success' ? colors.success
    : status === 'warning' ? colors.warning
    : colors.danger;

  return (
    <Card statusColor={status}>
      <div className="p-5">
        {/* Label */}
        <p className="text-xs font-medium uppercase tracking-wider text-[#3B445C] mb-1">
          {kpi.name}
        </p>

        {/* Value */}
        <div className="flex items-end justify-between mb-2">
          <AnimatedNumber
            value={kpi.value}
            format={kpi.displayFormat}
            className="text-3xl text-[#012650]"
          />
          {trend !== undefined && (
            <div className={`flex items-center gap-0.5 text-sm font-medium ${
              trendIsGood ? 'text-[#22C55E]' : 'text-[#EF4444]'
            }`}>
              <svg className={`w-4 h-4 ${!trendIsGood ? 'rotate-180' : ''}`} fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 10l7-7m0 0l7 7m-7-7v18" />
              </svg>
              {Math.abs(trend).toFixed(1)}%
            </div>
          )}
        </div>

        {/* Target */}
        <div className="flex items-center justify-between">
          <p className="text-xs text-[#3B445C]">
            Target: {formatKpiValue(kpi.target, kpi.displayFormat)}
          </p>
          {kpi.sparklineData && kpi.sparklineData.length > 1 && (
            <div className="w-16">
              <Sparkline data={kpi.sparklineData} color={sparklineColor} height={24} />
            </div>
          )}
        </div>
      </div>
    </Card>
  );
}

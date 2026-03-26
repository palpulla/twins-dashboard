'use client';

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

const STATUS_BORDER_COLORS = {
  success: 'border-l-[#22C55E]',
  warning: 'border-l-[#F59E0B]',
  danger: 'border-l-[#EF4444]',
} as const;

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
    <div className={`bg-surface-container-lowest p-6 rounded-xl card-shadow border-l-4 ${STATUS_BORDER_COLORS[status]}`}>
      {/* Label */}
      <div className="flex justify-between items-start mb-4">
        <p className="text-xs font-bold uppercase tracking-widest text-on-surface-variant">
          {kpi.name}
        </p>
        {kpi.sparklineData && kpi.sparklineData.length > 1 && (
          <div className="w-16">
            <Sparkline data={kpi.sparklineData} color={sparklineColor} height={24} />
          </div>
        )}
      </div>

      {/* Value */}
      <div className="flex items-baseline gap-2">
        <AnimatedNumber
          value={kpi.value}
          format={kpi.displayFormat}
          className="text-3xl text-primary"
        />
        {trend !== undefined && (
          <span className={`text-sm font-bold flex items-center ${
            trendIsGood ? 'text-success' : 'text-danger'
          }`}>
            <span className="material-symbols-outlined text-[14px]">
              {trendIsGood ? 'arrow_upward' : 'arrow_downward'}
            </span>
            {Math.abs(trend).toFixed(1)}%
          </span>
        )}
      </div>

      {/* Target */}
      <p className="text-xs text-on-surface-variant mt-2 font-medium">
        Target: {formatKpiValue(kpi.target, kpi.displayFormat)}
      </p>
    </div>
  );
}

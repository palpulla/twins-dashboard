'use client';

import { KpiCard } from './kpi-card';
import type { KpiValue } from '@/types/kpi';

interface KpiGridProps {
  kpis: KpiValue[];
  isLoading?: boolean;
}

export function KpiGrid({ kpis, isLoading }: KpiGridProps) {
  if (isLoading) {
    return (
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
        {Array.from({ length: 8 }).map((_, i) => (
          <KpiCard key={i} kpi={{} as KpiValue} isLoading />
        ))}
      </div>
    );
  }

  return (
    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
      {kpis.map((kpi) => (
        <KpiCard key={kpi.definitionId} kpi={kpi} />
      ))}
    </div>
  );
}

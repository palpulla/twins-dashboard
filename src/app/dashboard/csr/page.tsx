'use client';

import { Header } from '@/components/layout/header';
import { KpiGrid } from '@/components/kpi/kpi-grid';
import { CallLogTable } from '@/components/tables/call-log-table';
import { TrendChart } from '@/components/charts/trend-chart';
import { Card, CardContent } from '@/components/ui/card';
import { AnimatedNumber } from '@/components/kpi/animated-number';
import { Badge } from '@/components/ui/badge';
import { useAuthStore } from '@/lib/store/auth-store';
import { useCsrMetrics, SEED_USERS } from '@/lib/hooks/use-seed-data';
import { MARKETING_CHANNEL_LABELS } from '@/types/webhooks';
import type { MarketingChannel } from '@/types/webhooks';
import type { KpiValue } from '@/types/kpi';

export default function CsrPage() {
  const { user } = useAuthStore();
  const csrId = user?.role === 'csr' ? user.id : 'user-csr-001';
  const csr = SEED_USERS.find(u => u.id === csrId);
  const metrics = useCsrMetrics(csrId);

  const kpis: KpiValue[] = [
    {
      definitionId: 'booking_rate',
      name: 'Booking Rate',
      value: metrics.bookingRate,
      target: 70,
      displayFormat: 'percentage',
    },
    {
      definitionId: 'appointments_booked',
      name: 'Appointments Booked',
      value: metrics.appointmentsBooked,
      target: 50,
      displayFormat: 'count',
    },
    {
      definitionId: 'total_calls',
      name: 'Total Calls',
      value: metrics.totalCalls,
      target: 60,
      displayFormat: 'count',
    },
  ];

  return (
    <div>
      <Header title="CSR Dashboard" subtitle={csr?.fullName} />

      <div className="p-6 space-y-6">
        {/* CSR Profile */}
        <div className="flex items-center gap-4">
          <div className="w-16 h-16 rounded-full bg-[#012650] flex items-center justify-center text-white text-xl font-bold">
            {csr?.fullName.split(' ').map(n => n[0]).join('') || '?'}
          </div>
          <div>
            <h2 className="text-2xl font-bold text-[#012650]">{csr?.fullName || 'CSR'}</h2>
            <Badge variant="info">Customer Service Representative</Badge>
          </div>
        </div>

        {/* KPIs */}
        <KpiGrid kpis={kpis} />

        {/* Source Attribution */}
        <Card>
          <CardContent className="p-6">
            <h3 className="text-sm font-medium uppercase tracking-wider text-[#3B445C] mb-4">Call Source Attribution</h3>
            <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
              {Object.entries(metrics.sourceBreakdown).map(([source, data]) => (
                <div key={source} className="p-4 bg-[#F5F6FA] rounded-lg">
                  <p className="text-xs font-medium text-[#3B445C] mb-1">
                    {MARKETING_CHANNEL_LABELS[source as MarketingChannel] || source}
                  </p>
                  <p className="text-lg font-bold font-mono text-[#012650]">{data.total}</p>
                  <p className="text-xs text-[#3B445C]">
                    {data.booked} booked ({data.total > 0 ? ((data.booked / data.total) * 100).toFixed(0) : 0}%)
                  </p>
                </div>
              ))}
            </div>
          </CardContent>
        </Card>

        {/* Call Log */}
        <CallLogTable calls={metrics.callRecords} />
      </div>
    </div>
  );
}

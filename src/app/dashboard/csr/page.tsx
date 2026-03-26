'use client';

import { Header } from '@/components/layout/header';
import { KpiGrid } from '@/components/kpi/kpi-grid';
import { CallLogTable } from '@/components/tables/call-log-table';
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
      <Header title="CSR Dashboard" />

      <div className="px-6 md:px-8 space-y-8 py-8">
        {/* CSR Profile */}
        <section className="flex flex-col md:flex-row md:items-end justify-between gap-4">
          <div>
            <h2 className="text-3xl font-bold font-headline text-primary">Welcome, {csr?.fullName || 'CSR'}</h2>
            <div className="flex items-center gap-2 mt-2">
              <span className="px-3 py-1 bg-secondary-container text-on-secondary-container text-xs font-bold rounded-full uppercase tracking-wider">
                Customer Service Representative
              </span>
              <span className="w-2 h-2 rounded-full bg-success animate-pulse"></span>
              <span className="text-xs font-medium text-on-surface-variant">Active Now</span>
            </div>
          </div>
        </section>

        {/* KPIs - Custom layout to match Stitch */}
        <section className="grid grid-cols-1 md:grid-cols-3 gap-6">
          <div className="bg-surface-container-lowest rounded-xl p-6 card-shadow">
            <p className="text-sm font-medium text-on-surface-variant">Booking Rate</p>
            <p className="text-4xl font-bold font-headline text-primary mt-1">{metrics.bookingRate.toFixed(0)}%</p>
          </div>
          <div className="bg-surface-container-lowest rounded-xl p-6 card-shadow">
            <p className="text-sm font-medium text-on-surface-variant">Appointments Booked</p>
            <p className="text-4xl font-bold font-headline text-primary mt-1">{metrics.appointmentsBooked}</p>
          </div>
          <div className="bg-gradient-to-br from-[#012650] to-[#00112b] rounded-xl p-6 card-shadow">
            <p className="text-sm font-medium text-white/70">Total Calls Handled</p>
            <p className="text-4xl font-bold font-headline text-white mt-1">{metrics.totalCalls}</p>
          </div>
        </section>

        {/* Source Attribution */}
        <section className="bg-surface-container-lowest rounded-xl p-6 card-shadow">
          <h3 className="text-sm font-bold uppercase tracking-widest text-on-surface-variant mb-4">Call Source Attribution</h3>
          <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
            {Object.entries(metrics.sourceBreakdown).map(([source, data]) => (
              <div key={source} className="p-4 bg-surface-container-low rounded-lg">
                <p className="text-xs font-medium text-on-surface-variant mb-1">
                  {MARKETING_CHANNEL_LABELS[source as MarketingChannel] || source}
                </p>
                <p className="text-lg font-bold font-mono text-primary">{data.total}</p>
                <p className="text-xs text-on-surface-variant">
                  {data.booked} booked ({data.total > 0 ? ((data.booked / data.total) * 100).toFixed(0) : 0}%)
                </p>
              </div>
            ))}
          </div>
        </section>

        {/* Call Log */}
        <section className="space-y-4">
          <h3 className="text-lg font-headline font-semibold text-primary">Recent Call Log</h3>
          <CallLogTable calls={metrics.callRecords} />
        </section>
      </div>
    </div>
  );
}

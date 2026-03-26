'use client';

import { Header } from '@/components/layout/header';
import { ChannelChart } from '@/components/charts/channel-chart';
import { DataTable } from '@/components/ui/data-table';
import { useMarketingMetrics } from '@/lib/hooks/use-seed-data';
import { formatCurrencyDollars, formatPercentage, formatCount } from '@/lib/utils/format';
import { MARKETING_CHANNEL_LABELS } from '@/types/webhooks';
import type { MarketingChannel } from '@/types/webhooks';

export default function MarketingPage() {
  const metrics = useMarketingMetrics();
  const avgCpa = metrics.totalBooked > 0 ? metrics.totalSpend / metrics.totalBooked : 0;

  const channelColumns = [
    {
      key: 'channel',
      header: 'Channel',
      render: (row: (typeof metrics.channelMetrics)[0]) => (
        <span className="font-medium text-primary">
          {MARKETING_CHANNEL_LABELS[row.channel as MarketingChannel] || row.channel}
        </span>
      ),
    },
    {
      key: 'leads',
      header: 'Leads',
      render: (row: (typeof metrics.channelMetrics)[0]) => (
        <span className="font-mono">{formatCount(row.totalLeads)}</span>
      ),
      className: 'text-right',
    },
    {
      key: 'booked',
      header: 'Booked',
      render: (row: (typeof metrics.channelMetrics)[0]) => (
        <span className="font-mono">{formatCount(row.bookedLeads)}</span>
      ),
      className: 'text-right',
    },
    {
      key: 'spend',
      header: 'Spend',
      render: (row: (typeof metrics.channelMetrics)[0]) => (
        <span className="font-mono">{formatCurrencyDollars(row.totalSpend)}</span>
      ),
      className: 'text-right',
    },
    {
      key: 'cpa',
      header: 'CPA',
      render: (row: (typeof metrics.channelMetrics)[0]) => (
        <span className="font-mono">{row.cpa > 0 ? formatCurrencyDollars(row.cpa) : '—'}</span>
      ),
      className: 'text-right',
    },
    {
      key: 'revenue',
      header: 'Est. Revenue',
      render: (row: (typeof metrics.channelMetrics)[0]) => (
        <span className="font-mono font-bold text-primary">{formatCurrencyDollars(row.estimatedRevenue)}</span>
      ),
      className: 'text-right',
    },
    {
      key: 'roi',
      header: 'ROI',
      render: (row: (typeof metrics.channelMetrics)[0]) => {
        const roiValue = row.roi;
        return (
          <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold ${
            roiValue > 0 ? 'bg-green-100 text-green-800' : roiValue < 0 ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-800'
          }`}>
            {roiValue > 0 ? '+' : ''}{formatPercentage(roiValue)}
          </span>
        );
      },
      className: 'text-right',
    },
  ];

  return (
    <div>
      <Header title="Marketing Dashboard" subtitle="Channel performance & ROI" />

      <div className="px-6 md:px-8 py-8 space-y-12">
        {/* KPI Cards */}
        <section className="grid grid-cols-2 lg:grid-cols-4 gap-6">
          <div className="bg-surface-container-lowest p-6 rounded-xl flex flex-col justify-between min-h-[140px] card-shadow">
            <span className="text-on-surface-variant text-xs font-semibold uppercase tracking-wider">Total Leads</span>
            <div className="text-3xl font-headline font-bold text-primary mt-2">{formatCount(metrics.totalLeads)}</div>
          </div>
          <div className="bg-surface-container-lowest p-6 rounded-xl flex flex-col justify-between min-h-[140px] card-shadow">
            <span className="text-on-surface-variant text-xs font-semibold uppercase tracking-wider">Booked</span>
            <div className="text-3xl font-headline font-bold text-primary mt-2">{formatCount(metrics.totalBooked)}</div>
          </div>
          <div className="bg-surface-container-lowest p-6 rounded-xl flex flex-col justify-between min-h-[140px] card-shadow">
            <span className="text-on-surface-variant text-xs font-semibold uppercase tracking-wider">Ad Spend</span>
            <div className="text-3xl font-headline font-bold text-primary font-mono mt-2">{formatCurrencyDollars(metrics.totalSpend)}</div>
          </div>
          <div className="bg-primary text-white p-6 rounded-xl flex flex-col justify-between min-h-[140px] card-shadow">
            <span className="text-white/60 text-xs font-semibold uppercase tracking-wider">Avg. CPA</span>
            <div className="text-3xl font-headline font-bold font-mono text-white mt-2">{formatCurrencyDollars(avgCpa)}</div>
          </div>
        </section>

        {/* Channel Chart */}
        <ChannelChart
          data={metrics.channelMetrics.filter(c => c.totalSpend > 0)}
          title="Ad Spend vs Estimated Revenue by Channel"
        />

        {/* Channel Breakdown Table */}
        <div className="bg-surface-container-lowest rounded-xl card-shadow overflow-hidden">
          <div className="px-6 py-4 border-b border-surface-container">
            <h3 className="font-headline font-bold text-lg text-primary">Channel Breakdown</h3>
          </div>
          <div className="p-0">
            <DataTable
              columns={channelColumns}
              data={metrics.channelMetrics}
              keyExtractor={(row) => row.channel}
              emptyMessage="No marketing data available"
            />
          </div>
        </div>
      </div>
    </div>
  );
}

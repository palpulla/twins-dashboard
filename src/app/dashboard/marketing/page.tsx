'use client';

import { Header } from '@/components/layout/header';
import { KpiGrid } from '@/components/kpi/kpi-grid';
import { ChannelChart } from '@/components/charts/channel-chart';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import { DataTable } from '@/components/ui/data-table';
import { Badge } from '@/components/ui/badge';
import { useMarketingMetrics } from '@/lib/hooks/use-seed-data';
import { formatCurrencyDollars, formatPercentage, formatCount } from '@/lib/utils/format';
import { MARKETING_CHANNEL_LABELS } from '@/types/webhooks';
import type { MarketingChannel } from '@/types/webhooks';
import type { KpiValue } from '@/types/kpi';

export default function MarketingPage() {
  const metrics = useMarketingMetrics();

  const kpis: KpiValue[] = [
    {
      definitionId: 'total_leads',
      name: 'Total Leads',
      value: metrics.totalLeads,
      target: 200,
      displayFormat: 'count',
    },
    {
      definitionId: 'total_booked',
      name: 'Booked Appointments',
      value: metrics.totalBooked,
      target: 150,
      displayFormat: 'count',
    },
    {
      definitionId: 'total_spend',
      name: 'Total Ad Spend',
      value: metrics.totalSpend,
      target: 15000,
      displayFormat: 'currency',
    },
    {
      definitionId: 'avg_cpa',
      name: 'Avg. Cost per Acquisition',
      value: metrics.totalBooked > 0 ? metrics.totalSpend / metrics.totalBooked : 0,
      target: 100,
      displayFormat: 'currency',
      invertedStatus: true,
    },
  ];

  const channelColumns = [
    {
      key: 'channel',
      header: 'Channel',
      render: (row: (typeof metrics.channelMetrics)[0]) => (
        <span className="font-medium text-[#012650]">
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
        <span className="font-mono font-medium text-[#012650]">{formatCurrencyDollars(row.estimatedRevenue)}</span>
      ),
      className: 'text-right',
    },
    {
      key: 'roi',
      header: 'ROI',
      render: (row: (typeof metrics.channelMetrics)[0]) => {
        const roiValue = row.roi;
        return (
          <Badge variant={roiValue > 0 ? 'success' : roiValue < 0 ? 'danger' : 'default'}>
            {roiValue > 0 ? '+' : ''}{formatPercentage(roiValue)}
          </Badge>
        );
      },
      className: 'text-right',
    },
  ];

  return (
    <div>
      <Header title="Marketing Dashboard" subtitle="Channel performance & ROI" />

      <div className="p-6 space-y-6">
        <KpiGrid kpis={kpis} />

        <ChannelChart
          data={metrics.channelMetrics.filter(c => c.totalSpend > 0)}
          title="Ad Spend vs Estimated Revenue by Channel"
        />

        <Card>
          <CardHeader>
            <CardTitle>Channel Breakdown</CardTitle>
          </CardHeader>
          <CardContent className="p-0">
            <DataTable
              columns={channelColumns}
              data={metrics.channelMetrics}
              keyExtractor={(row) => row.channel}
              emptyMessage="No marketing data available"
            />
          </CardContent>
        </Card>
      </div>
    </div>
  );
}

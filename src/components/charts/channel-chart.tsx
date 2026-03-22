'use client';

import { BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer } from 'recharts';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import { MARKETING_CHANNEL_LABELS } from '@/types/webhooks';
import type { MarketingChannel } from '@/types/webhooks';

interface ChannelChartProps {
  data: { channel: string; totalSpend: number; estimatedRevenue: number }[];
  title?: string;
}

export function ChannelChart({ data, title = 'Channel Performance' }: ChannelChartProps) {
  const chartData = data.map(d => ({
    name: MARKETING_CHANNEL_LABELS[d.channel as MarketingChannel] || d.channel,
    spend: d.totalSpend,
    revenue: d.estimatedRevenue,
  }));

  return (
    <Card>
      <CardHeader>
        <CardTitle>{title}</CardTitle>
      </CardHeader>
      <CardContent>
        <div className="h-80">
          <ResponsiveContainer width="100%" height="100%">
            <BarChart data={chartData} layout="vertical">
              <CartesianGrid strokeDasharray="3 3" stroke="#F5F6FA" />
              <XAxis type="number" tick={{ fontSize: 12, fill: '#3B445C' }} tickFormatter={(v) => `$${(v / 1000).toFixed(0)}k`} />
              <YAxis type="category" dataKey="name" tick={{ fontSize: 11, fill: '#3B445C' }} width={120} />
              <Tooltip
                contentStyle={{ borderRadius: 8, border: 'none', boxShadow: '0 4px 12px rgba(0,0,0,0.1)' }}
                formatter={(value) => [`$${Number(value).toLocaleString()}`, undefined]}
              />
              <Bar dataKey="spend" name="Ad Spend" fill="#EF4444" radius={[0, 4, 4, 0]} />
              <Bar dataKey="revenue" name="Est. Revenue" fill="#22C55E" radius={[0, 4, 4, 0]} />
            </BarChart>
          </ResponsiveContainer>
        </div>
      </CardContent>
    </Card>
  );
}

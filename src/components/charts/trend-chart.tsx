'use client';

import { LineChart, Line, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer } from 'recharts';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';

interface TrendChartProps {
  data: { name: string; value: number }[];
  title?: string;
  color?: string;
  valuePrefix?: string;
  valueSuffix?: string;
}

export function TrendChart({ data, title = 'Trend', color = '#012650', valuePrefix = '', valueSuffix = '' }: TrendChartProps) {
  return (
    <Card>
      <CardHeader>
        <CardTitle>{title}</CardTitle>
      </CardHeader>
      <CardContent>
        <div className="h-64">
          <ResponsiveContainer width="100%" height="100%">
            <LineChart data={data}>
              <CartesianGrid strokeDasharray="3 3" stroke="#F5F6FA" />
              <XAxis dataKey="name" tick={{ fontSize: 12, fill: '#3B445C' }} />
              <YAxis tick={{ fontSize: 12, fill: '#3B445C' }} />
              <Tooltip
                contentStyle={{ borderRadius: 8, border: 'none', boxShadow: '0 4px 12px rgba(0,0,0,0.1)' }}
                formatter={(value) => [`${valuePrefix}${Number(value).toLocaleString()}${valueSuffix}`, undefined]}
              />
              <Line type="monotone" dataKey="value" stroke={color} strokeWidth={2} dot={{ r: 4, fill: color }} />
            </LineChart>
          </ResponsiveContainer>
        </div>
      </CardContent>
    </Card>
  );
}

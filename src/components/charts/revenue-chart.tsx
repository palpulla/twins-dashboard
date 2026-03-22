'use client';

import { BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer, Legend } from 'recharts';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';

interface RevenueChartProps {
  data: { name: string; revenue: number; partsCost: number }[];
  title?: string;
}

export function RevenueChart({ data, title = 'Revenue Overview' }: RevenueChartProps) {
  return (
    <Card>
      <CardHeader>
        <CardTitle>{title}</CardTitle>
      </CardHeader>
      <CardContent>
        <div className="h-80">
          <ResponsiveContainer width="100%" height="100%">
            <BarChart data={data}>
              <CartesianGrid strokeDasharray="3 3" stroke="#F5F6FA" />
              <XAxis dataKey="name" tick={{ fontSize: 12, fill: '#3B445C' }} />
              <YAxis tick={{ fontSize: 12, fill: '#3B445C' }} tickFormatter={(v) => `$${v.toLocaleString()}`} />
              <Tooltip
                contentStyle={{ borderRadius: 8, border: 'none', boxShadow: '0 4px 12px rgba(0,0,0,0.1)' }}
                formatter={(value) => [`$${Number(value).toLocaleString()}`, undefined]}
              />
              <Legend />
              <Bar dataKey="revenue" name="Revenue" fill="#012650" radius={[4, 4, 0, 0]} />
              <Bar dataKey="partsCost" name="Parts Cost" fill="#FBBC03" radius={[4, 4, 0, 0]} />
            </BarChart>
          </ResponsiveContainer>
        </div>
      </CardContent>
    </Card>
  );
}

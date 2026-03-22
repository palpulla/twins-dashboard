'use client';

import { format } from 'date-fns';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { DataTable } from '@/components/ui/data-table';
import { formatCurrencyDollars } from '@/lib/utils/format';

interface JobRow {
  id: string;
  customerName: string;
  job_type: string;
  revenue: number;
  parts_cost: number;
  netRevenue: number;
  commissionEarned: number;
  completed_at: string | null;
  created_at: string;
}

interface JobHistoryTableProps {
  jobs: JobRow[];
}

const JOB_TYPE_VARIANTS: Record<string, 'info' | 'success' | 'warning' | 'danger' | 'default'> = {
  'Door Install': 'success',
  'Door + Opener Install': 'success',
  'Opener Install': 'info',
  'Repair': 'warning',
  'Service Call': 'warning',
  'Opener + Repair': 'warning',
  'Maintenance Visit': 'default',
  'Warranty Call': 'danger',
};

export function JobHistoryTable({ jobs }: JobHistoryTableProps) {
  const columns = [
    {
      key: 'date',
      header: 'Date',
      render: (row: JobRow) => (
        <span className="text-[#012650]">
          {format(new Date(row.completed_at || row.created_at), 'MMM d, yyyy')}
        </span>
      ),
    },
    {
      key: 'customer',
      header: 'Customer',
      render: (row: JobRow) => <span className="font-medium text-[#012650]">{row.customerName}</span>,
    },
    {
      key: 'type',
      header: 'Job Type',
      render: (row: JobRow) => (
        <Badge variant={JOB_TYPE_VARIANTS[row.job_type] || 'default'}>
          {row.job_type}
        </Badge>
      ),
    },
    {
      key: 'revenue',
      header: 'Revenue',
      render: (row: JobRow) => <span className="font-mono font-medium text-[#012650]">{formatCurrencyDollars(row.revenue)}</span>,
      className: 'text-right',
    },
    {
      key: 'parts',
      header: 'Parts Cost',
      render: (row: JobRow) => <span className="font-mono text-[#3B445C]">{formatCurrencyDollars(row.parts_cost)}</span>,
      className: 'text-right',
    },
    {
      key: 'net',
      header: 'Net Revenue',
      render: (row: JobRow) => <span className="font-mono font-medium text-[#012650]">{formatCurrencyDollars(row.netRevenue)}</span>,
      className: 'text-right',
    },
    {
      key: 'commission',
      header: 'Commission',
      render: (row: JobRow) => <span className="font-mono font-medium text-[#22C55E]">{formatCurrencyDollars(row.commissionEarned)}</span>,
      className: 'text-right',
    },
  ];

  return (
    <Card>
      <CardHeader>
        <CardTitle>Recent Completed Jobs</CardTitle>
      </CardHeader>
      <CardContent className="p-0">
        <DataTable
          columns={columns}
          data={jobs}
          keyExtractor={(row) => row.id}
          emptyMessage="No completed jobs in the selected period"
        />
      </CardContent>
    </Card>
  );
}

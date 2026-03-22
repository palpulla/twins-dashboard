'use client';

import { Header } from '@/components/layout/header';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { DataTable } from '@/components/ui/data-table';
import { DEFAULT_KPI_DEFINITIONS } from '@/lib/constants/kpi-defaults';
import { formatKpiValue } from '@/lib/utils/format';
import type { KpiDefinition } from '@/types/kpi';

export default function KpisAdminPage() {
  const columns = [
    {
      key: 'name',
      header: 'KPI Name',
      render: (row: KpiDefinition) => (
        <span className="font-medium text-[#012650]">{row.name}</span>
      ),
    },
    {
      key: 'formula',
      header: 'Formula ID',
      render: (row: KpiDefinition) => (
        <code className="text-xs font-mono bg-gray-100 px-2 py-1 rounded">{row.formula}</code>
      ),
    },
    {
      key: 'target',
      header: 'Target',
      render: (row: KpiDefinition) => (
        <span className="font-mono">{formatKpiValue(row.target, row.displayFormat)}</span>
      ),
      className: 'text-right',
    },
    {
      key: 'format',
      header: 'Format',
      render: (row: KpiDefinition) => (
        <Badge variant="default">{row.displayFormat}</Badge>
      ),
    },
    {
      key: 'status',
      header: 'Status',
      render: (row: KpiDefinition) => (
        <Badge variant={row.isActive ? 'success' : 'danger'}>
          {row.isActive ? 'Active' : 'Inactive'}
        </Badge>
      ),
    },
    {
      key: 'actions',
      header: '',
      render: () => <Button variant="ghost" size="sm">Edit</Button>,
    },
  ];

  return (
    <div>
      <Header
        title="KPI Definitions"
        showDatePicker={false}
        actions={<Button>Add KPI</Button>}
      />

      <div className="p-6">
        <Card>
          <CardHeader>
            <CardTitle>All KPI Metrics</CardTitle>
          </CardHeader>
          <CardContent className="p-0">
            <DataTable
              columns={columns}
              data={DEFAULT_KPI_DEFINITIONS}
              keyExtractor={(row) => row.id}
            />
          </CardContent>
        </Card>
      </div>
    </div>
  );
}

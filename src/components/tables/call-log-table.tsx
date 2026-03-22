'use client';

import { format } from 'date-fns';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { DataTable } from '@/components/ui/data-table';
import { formatDuration, formatPhoneNumber } from '@/lib/utils/format';
import { MARKETING_CHANNEL_LABELS } from '@/types/webhooks';
import type { MarketingChannel } from '@/types/webhooks';
import type { Tables } from '@/types/database';

interface CallLogTableProps {
  calls: Tables<'call_records'>[];
}

const OUTCOME_VARIANTS: Record<string, 'success' | 'warning' | 'danger' | 'default'> = {
  booked: 'success',
  not_booked: 'danger',
  voicemail: 'warning',
};

export function CallLogTable({ calls }: CallLogTableProps) {
  const columns = [
    {
      key: 'timestamp',
      header: 'Time',
      render: (row: Tables<'call_records'>) => (
        <span className="text-[#012650]">
          {format(new Date(row.created_at), 'MMM d, h:mm a')}
        </span>
      ),
    },
    {
      key: 'caller',
      header: 'Caller',
      render: (row: Tables<'call_records'>) => (
        <div>
          <p className="font-medium text-[#012650]">{row.caller_name || 'Unknown'}</p>
          <p className="text-xs text-[#3B445C]">{row.caller_phone ? formatPhoneNumber(row.caller_phone) : ''}</p>
        </div>
      ),
    },
    {
      key: 'source',
      header: 'Source',
      render: (row: Tables<'call_records'>) => (
        <Badge variant="info">
          {MARKETING_CHANNEL_LABELS[row.source as MarketingChannel] || row.source}
        </Badge>
      ),
    },
    {
      key: 'duration',
      header: 'Duration',
      render: (row: Tables<'call_records'>) => (
        <span className="font-mono text-sm text-[#3B445C]">{formatDuration(row.duration_seconds)}</span>
      ),
    },
    {
      key: 'outcome',
      header: 'Outcome',
      render: (row: Tables<'call_records'>) => (
        <Badge variant={OUTCOME_VARIANTS[row.outcome] || 'default'}>
          {row.outcome === 'booked' ? 'Booked' : row.outcome === 'not_booked' ? 'Not Booked' : 'Voicemail'}
        </Badge>
      ),
    },
    {
      key: 'notes',
      header: 'Notes',
      render: (row: Tables<'call_records'>) => (
        <span className="text-sm text-[#3B445C]">{row.notes || '—'}</span>
      ),
    },
  ];

  return (
    <Card>
      <CardHeader>
        <CardTitle>Call Log</CardTitle>
      </CardHeader>
      <CardContent className="p-0">
        <DataTable
          columns={columns}
          data={calls}
          keyExtractor={(row) => row.id}
          emptyMessage="No calls in the selected period"
        />
      </CardContent>
    </Card>
  );
}

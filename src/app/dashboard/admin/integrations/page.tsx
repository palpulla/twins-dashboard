'use client';

import { Header } from '@/components/layout/header';
import { Card, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

const INTEGRATIONS = [
  {
    name: 'HousecallPro',
    description: 'Webhooks for jobs, invoices, customers, estimates, leads',
    status: 'connected' as const,
    lastReceived: '2 minutes ago',
    errorCount: 0,
    eventsToday: 47,
  },
  {
    name: 'GoHighLevel (Agency A)',
    description: 'Automation, communications, call tracking',
    status: 'connected' as const,
    lastReceived: '5 minutes ago',
    errorCount: 1,
    eventsToday: 23,
  },
  {
    name: 'GoHighLevel (Agency B)',
    description: 'Google Ads, Meta Ads management',
    status: 'connected' as const,
    lastReceived: '8 minutes ago',
    errorCount: 0,
    eventsToday: 12,
  },
  {
    name: 'Google Ads API',
    description: 'Campaign spend, impressions, clicks, conversions',
    status: 'connected' as const,
    lastReceived: '1 hour ago',
    errorCount: 0,
    eventsToday: 6,
  },
  {
    name: 'Google LSA',
    description: 'Local Services Ads leads and spend',
    status: 'connected' as const,
    lastReceived: '1 hour ago',
    errorCount: 0,
    eventsToday: 4,
  },
  {
    name: 'Google Business Profile',
    description: '5-star review tracking and attribution',
    status: 'warning' as const,
    lastReceived: '3 hours ago',
    errorCount: 2,
    eventsToday: 3,
  },
];

const STATUS_CONFIG = {
  connected: { variant: 'success' as const, label: 'Connected' },
  warning: { variant: 'warning' as const, label: 'Warning' },
  disconnected: { variant: 'danger' as const, label: 'Disconnected' },
};

export default function IntegrationsAdminPage() {
  return (
    <div>
      <Header title="Integration Health" showDatePicker={false} />

      <div className="p-6 space-y-6">
        {/* Integration Cards */}
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          {INTEGRATIONS.map(integration => {
            const statusConfig = STATUS_CONFIG[integration.status];
            return (
              <Card key={integration.name}>
                <CardContent className="p-6">
                  <div className="flex items-start justify-between mb-3">
                    <div>
                      <h3 className="font-semibold text-[#012650]">{integration.name}</h3>
                      <p className="text-xs text-[#3B445C] mt-0.5">{integration.description}</p>
                    </div>
                    <Badge variant={statusConfig.variant}>{statusConfig.label}</Badge>
                  </div>
                  <div className="grid grid-cols-3 gap-4 mt-4">
                    <div>
                      <p className="text-xs text-[#3B445C]">Last Received</p>
                      <p className="text-sm font-medium text-[#012650]">{integration.lastReceived}</p>
                    </div>
                    <div>
                      <p className="text-xs text-[#3B445C]">Errors (24h)</p>
                      <p className={`text-sm font-medium font-mono ${integration.errorCount > 0 ? 'text-[#EF4444]' : 'text-[#22C55E]'}`}>
                        {integration.errorCount}
                      </p>
                    </div>
                    <div>
                      <p className="text-xs text-[#3B445C]">Events Today</p>
                      <p className="text-sm font-medium font-mono text-[#012650]">{integration.eventsToday}</p>
                    </div>
                  </div>
                </CardContent>
              </Card>
            );
          })}
        </div>

        {/* Webhook Replay */}
        <Card>
          <CardContent className="p-6">
            <h3 className="font-semibold text-[#012650] mb-2">Webhook Replay</h3>
            <p className="text-sm text-[#3B445C] mb-4">
              Reprocess raw webhook events if processing logic has changed. Select a date range and source to replay.
            </p>
            <div className="flex gap-4 items-end">
              <div>
                <label className="block text-xs font-medium text-[#3B445C] mb-1">Source</label>
                <select className="px-3 py-2 border border-gray-200 rounded-lg text-sm">
                  <option>All Sources</option>
                  <option>HousecallPro</option>
                  <option>GoHighLevel (A)</option>
                  <option>GoHighLevel (B)</option>
                </select>
              </div>
              <div>
                <label className="block text-xs font-medium text-[#3B445C] mb-1">From</label>
                <input type="date" className="px-3 py-2 border border-gray-200 rounded-lg text-sm" />
              </div>
              <div>
                <label className="block text-xs font-medium text-[#3B445C] mb-1">To</label>
                <input type="date" className="px-3 py-2 border border-gray-200 rounded-lg text-sm" />
              </div>
              <Button variant="outline">Replay Events</Button>
            </div>
          </CardContent>
        </Card>

        {/* Data Quality */}
        <Card>
          <CardContent className="p-6">
            <h3 className="font-semibold text-[#012650] mb-2">Data Quality Checks</h3>
            <p className="text-sm text-[#3B445C] mb-4">
              Automated checks for data discrepancies and missing records.
            </p>
            <div className="space-y-2">
              {[
                { check: 'Jobs without invoices', count: 3, severity: 'warning' as const },
                { check: 'Invoices without matching jobs', count: 0, severity: 'success' as const },
                { check: 'Completed jobs without commission records', count: 1, severity: 'warning' as const },
                { check: 'Unattributed reviews', count: 5, severity: 'default' as const },
              ].map(item => (
                <div key={item.check} className="flex items-center justify-between p-3 bg-[#F5F6FA] rounded-lg">
                  <span className="text-sm text-[#012650]">{item.check}</span>
                  <Badge variant={item.count === 0 ? 'success' : item.severity}>
                    {item.count} {item.count === 1 ? 'issue' : 'issues'}
                  </Badge>
                </div>
              ))}
            </div>
          </CardContent>
        </Card>
      </div>
    </div>
  );
}

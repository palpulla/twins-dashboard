'use client';

import { Header } from '@/components/layout/header';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { DataTable } from '@/components/ui/data-table';
import { SEED_USERS, SEED_COMMISSION_TIERS } from '@/lib/seed-data';
import { COMMISSION_TIERS } from '@/lib/utils/commission';

export default function CommissionsAdminPage() {
  const techsWithTiers = SEED_USERS
    .filter(u => u.role === 'technician')
    .map(tech => {
      const tier = SEED_COMMISSION_TIERS.find(t => t.user_id === tech.id);
      return { ...tech, tier };
    });

  const tierColumns = [
    {
      key: 'name',
      header: 'Technician',
      render: (row: (typeof techsWithTiers)[0]) => (
        <span className="font-medium text-[#012650]">{row.fullName}</span>
      ),
    },
    {
      key: 'tier',
      header: 'Current Tier',
      render: (row: (typeof techsWithTiers)[0]) => (
        <Badge variant="info">Tier {row.tier?.tier_level || 1}</Badge>
      ),
    },
    {
      key: 'rate',
      header: 'Commission Rate',
      render: (row: (typeof techsWithTiers)[0]) => (
        <span className="font-mono font-medium text-[#012650]">
          {((row.tier?.rate || 0.16) * 100).toFixed(0)}%
        </span>
      ),
    },
    {
      key: 'effective',
      header: 'Effective Since',
      render: (row: (typeof techsWithTiers)[0]) => (
        <span className="text-[#3B445C]">{row.tier?.effective_date || '—'}</span>
      ),
    },
    {
      key: 'actions',
      header: '',
      render: () => <Button variant="ghost" size="sm">Change Tier</Button>,
    },
  ];

  return (
    <div>
      <Header title="Commission Configuration" showDatePicker={false} />

      <div className="p-6 space-y-6">
        {/* Tier Definitions */}
        <Card>
          <CardHeader>
            <CardTitle>Commission Tier Definitions</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
              {Object.values(COMMISSION_TIERS).map(tier => (
                <div key={tier.level} className="p-4 border border-gray-200 rounded-lg">
                  <div className="flex items-center justify-between mb-2">
                    <h4 className="font-semibold text-[#012650]">Tier {tier.level}</h4>
                    <span className="text-2xl font-bold font-mono text-[#012650]">{(tier.rate * 100).toFixed(0)}%</span>
                  </div>
                  <p className="text-sm text-[#3B445C]">Applied to Net Revenue (Revenue - Parts Cost)</p>
                </div>
              ))}
            </div>
          </CardContent>
        </Card>

        {/* Technician Assignments */}
        <Card>
          <CardHeader>
            <CardTitle>Technician Tier Assignments</CardTitle>
          </CardHeader>
          <CardContent className="p-0">
            <DataTable
              columns={tierColumns}
              data={techsWithTiers}
              keyExtractor={(row) => row.id}
            />
          </CardContent>
        </Card>

        {/* Manager Override & Bonus Rules */}
        <Card>
          <CardHeader>
            <CardTitle>Manager Compensation Rules</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="space-y-4">
              <div className="p-4 bg-[#F5F6FA] rounded-lg">
                <h4 className="font-semibold text-[#012650] mb-1">Manager Override</h4>
                <p className="text-sm text-[#3B445C]">
                  <span className="font-mono font-bold text-[#012650]">2%</span> of net revenue on every job completed by technicians assigned under the manager.
                </p>
              </div>
              <div className="p-4 bg-[#F5F6FA] rounded-lg">
                <h4 className="font-semibold text-[#012650] mb-2">Manager Bonus Structure</h4>
                <div className="grid grid-cols-2 md:grid-cols-4 gap-2 text-sm">
                  {[400, 500, 600, 700, 800].map(threshold => {
                    const bonus = 20 + Math.max(0, (threshold - 400) / 100) * 10;
                    return (
                      <div key={threshold} className="p-2 bg-white rounded border border-gray-100 text-center">
                        <p className="font-mono text-xs text-[#3B445C]">${threshold}+ net</p>
                        <p className="font-mono font-bold text-[#012650]">${bonus}</p>
                      </div>
                    );
                  })}
                </div>
                <p className="text-xs text-[#3B445C] mt-2">+$10 for every additional $100 above $400 net revenue</p>
              </div>
              <div className="p-4 bg-[#FBBC03]/10 rounded-lg border border-[#FBBC03]/20">
                <h4 className="font-semibold text-[#012650] mb-1">Monthly Team Bonus</h4>
                <p className="text-sm text-[#3B445C]">
                  Placeholder — Monthly team bonus structure is planned but not yet defined. Configuration will be available here when finalized.
                </p>
              </div>
            </div>
          </CardContent>
        </Card>
      </div>
    </div>
  );
}

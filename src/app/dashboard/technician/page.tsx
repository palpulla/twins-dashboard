'use client';

import { Header } from '@/components/layout/header';
import { KpiGrid } from '@/components/kpi/kpi-grid';
import { JobHistoryTable } from '@/components/tables/job-history-table';
import { useAuthStore } from '@/lib/store/auth-store';
import { useTechnicianKpis, useTechnicianJobs, SEED_USERS } from '@/lib/hooks/use-seed-data';
import { SEED_COMMISSION_TIERS } from '@/lib/seed-data';
import { Badge } from '@/components/ui/badge';

export default function TechnicianPage() {
  const { user } = useAuthStore();
  const techId = user?.role === 'technician' ? user.id : 'user-tech-001';
  const tech = SEED_USERS.find(u => u.id === techId);
  const tier = SEED_COMMISSION_TIERS.find(t => t.user_id === techId);

  const kpis = useTechnicianKpis(techId);
  const jobs = useTechnicianJobs(techId);

  return (
    <div>
      <Header
        title="Technician Scorecard"
        subtitle={tech?.fullName}
      />

      <div className="p-6 space-y-6">
        {/* Tech Profile Header */}
        <div className="flex items-center gap-4">
          <div className="w-16 h-16 rounded-full bg-[#012650] flex items-center justify-center text-white text-xl font-bold">
            {tech?.fullName.split(' ').map(n => n[0]).join('') || '?'}
          </div>
          <div>
            <h2 className="text-2xl font-bold text-[#012650]">{tech?.fullName || 'Technician'}</h2>
            <div className="flex items-center gap-2 mt-1">
              <Badge variant="info">
                Tier {tier?.tier_level || 1} — {((tier?.rate || 0.16) * 100).toFixed(0)}% Commission
              </Badge>
            </div>
          </div>
        </div>

        {/* KPI Grid */}
        <KpiGrid kpis={kpis} />

        {/* Job History */}
        <JobHistoryTable jobs={jobs} />
      </div>
    </div>
  );
}

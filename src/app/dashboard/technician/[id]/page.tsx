'use client';

import { use } from 'react';
import { Header } from '@/components/layout/header';
import { KpiGrid } from '@/components/kpi/kpi-grid';
import { JobHistoryTable } from '@/components/tables/job-history-table';
import { TechnicianTrendsCard } from '@/components/charts/technician-trends-card';
import { useTechnicianKpis, useTechnicianJobs, SEED_USERS } from '@/lib/hooks/use-seed-data';
import { SEED_COMMISSION_TIERS } from '@/lib/seed-data';
import { Badge } from '@/components/ui/badge';

export default function TechnicianDetailPage({ params }: { params: Promise<{ id: string }> }) {
  const { id: techId } = use(params);
  const tech = SEED_USERS.find(u => u.id === techId);
  const tier = SEED_COMMISSION_TIERS.find(t => t.user_id === techId);

  const kpis = useTechnicianKpis(techId);
  const jobs = useTechnicianJobs(techId);

  if (!tech) {
    return (
      <div>
        <Header title="Technician Not Found" showDatePicker={false} />
        <div className="p-6">
          <p className="text-[#3B445C]">The requested technician could not be found.</p>
        </div>
      </div>
    );
  }

  return (
    <div>
      <Header
        title="Technician Scorecard"
        subtitle={tech.fullName}
      />

      <div className="p-6 space-y-6">
        {/* Tech Profile Header */}
        <div className="flex items-center gap-4">
          <div className="w-16 h-16 rounded-full bg-[#012650] flex items-center justify-center text-white text-xl font-bold">
            {tech.fullName.split(' ').map(n => n[0]).join('')}
          </div>
          <div>
            <h2 className="text-2xl font-bold text-[#012650]">{tech.fullName}</h2>
            <div className="flex items-center gap-2 mt-1">
              <Badge variant="info">
                Tier {tier?.tier_level || 1} — {((tier?.rate || 0.16) * 100).toFixed(0)}% Commission
              </Badge>
              <Badge variant="default">{tech.email}</Badge>
            </div>
          </div>
        </div>

        {/* KPI Grid */}
        <KpiGrid kpis={kpis} />

        {/* Trends — pick KPI + period to see how it moves over time */}
        <TechnicianTrendsCard technicianId={techId} />

        {/* Job History */}
        <JobHistoryTable jobs={jobs} />
      </div>
    </div>
  );
}

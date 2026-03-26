'use client';

import { Header } from '@/components/layout/header';
import { KpiGrid } from '@/components/kpi/kpi-grid';
import { JobHistoryTable } from '@/components/tables/job-history-table';
import { useAuthStore } from '@/lib/store/auth-store';
import { useTechnicianKpis, useTechnicianJobs, SEED_USERS } from '@/lib/hooks/use-seed-data';
import { SEED_COMMISSION_TIERS } from '@/lib/seed-data';

export default function TechnicianPage() {
  const { user } = useAuthStore();
  const techId = user?.role === 'technician' ? user.id : 'user-tech-001';
  const tech = SEED_USERS.find(u => u.id === techId);
  const tier = SEED_COMMISSION_TIERS.find(t => t.user_id === techId);

  const kpis = useTechnicianKpis(techId);
  const jobs = useTechnicianJobs(techId);

  return (
    <div className="bg-surface-container-low min-h-screen">
      <Header title="Fleet Efficiency" />

      <div className="p-6 md:p-8 space-y-12">
        {/* Section header */}
        <div className="flex flex-col md:flex-row md:items-end justify-between gap-6">
          <div>
            <p className="text-secondary font-semibold tracking-wider text-sm uppercase mb-1">Performance Analytics</p>
            <h2 className="text-4xl font-extrabold font-headline tracking-tight text-primary">Technician Scorecard</h2>
            <p className="text-on-surface-variant mt-2 max-w-xl">Comprehensive efficiency and revenue metrics for field service operations. Updated in real-time.</p>
          </div>
          <div className="flex items-center gap-3">
            <button className="bg-primary text-white px-6 py-3 rounded-xl font-semibold shadow-lg hover:scale-[1.02] active:scale-95 transition-all">
              Export Report
            </button>
          </div>
        </div>

        {/* Tech Profile Header */}
        <section className="bg-surface-container-lowest p-8 rounded-full card-shadow flex flex-wrap items-center gap-8">
          <div className="w-24 h-24 rounded-full bg-primary flex items-center justify-center text-white text-3xl font-bold font-headline">
            {tech?.fullName.split(' ').map(n => n[0]).join('') || '?'}
          </div>
          <div className="flex-1">
            <div className="flex items-center gap-3 flex-wrap">
              <h3 className="text-3xl font-bold text-on-surface">{tech?.fullName || 'Technician'}</h3>
              <span className="bg-secondary/10 text-secondary px-4 py-1 rounded-full text-sm font-bold border border-secondary/20">
                Tier {tier?.tier_level || 1} — {((tier?.rate || 0.16) * 100).toFixed(0)}% Commission
              </span>
            </div>
            <div className="flex gap-6 mt-3 text-on-surface-variant font-medium">
              <span className="flex items-center gap-1">
                <span className="material-symbols-outlined text-sm">id_card</span>
                TECH-{techId.slice(-3)}
              </span>
              <span className="flex items-center gap-1">
                <span className="material-symbols-outlined text-sm">location_on</span>
                Madison, WI
              </span>
              <span className="flex items-center gap-1">
                <span className="material-symbols-outlined text-sm">verified</span>
                {tier?.tier_level === 3 ? 'Lead Installer' : tier?.tier_level === 2 ? 'Senior Tech' : 'Technician'}
              </span>
            </div>
          </div>
        </section>

        {/* KPI Grid */}
        <KpiGrid kpis={kpis} />

        {/* Job History */}
        <JobHistoryTable jobs={jobs} />
      </div>
    </div>
  );
}

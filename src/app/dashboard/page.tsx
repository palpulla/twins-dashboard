'use client';

import { useMemo } from 'react';
import { useRouter } from 'next/navigation';
import { Header } from '@/components/layout/header';
import { KpiGrid } from '@/components/kpi/kpi-grid';
import { LeaderboardTable } from '@/components/tables/leaderboard-table';
import { RevenueChart } from '@/components/charts/revenue-chart';
import { Card, CardContent } from '@/components/ui/card';
import { AnimatedNumber } from '@/components/kpi/animated-number';
import { useCompanyKpis, useLeaderboard } from '@/lib/hooks/use-seed-data';
import { useAuthStore } from '@/lib/store/auth-store';
import { useDashboardStore } from '@/lib/store/dashboard-store';
import { SEED_JOBS, SEED_COMMISSION_RECORDS } from '@/lib/seed-data';
import { isWithinInterval } from 'date-fns';

export default function DashboardPage() {
  const router = useRouter();
  const { user } = useAuthStore();
  const { dateRange } = useDashboardStore();
  const kpis = useCompanyKpis();
  const leaderboard = useLeaderboard();

  // Redirect non-owner/manager users
  if (user && user.role === 'technician') {
    router.push('/dashboard/technician');
    return null;
  }
  if (user && user.role === 'csr') {
    router.push('/dashboard/csr');
    return null;
  }

  const financialSummary = useMemo(() => {
    const filteredJobs = SEED_JOBS.filter(j => {
      if (!j.completed_at) return false;
      return isWithinInterval(new Date(j.completed_at), { start: dateRange.from, end: dateRange.to });
    });

    const totalRevenue = filteredJobs.reduce((sum, j) => sum + j.revenue, 0);
    const totalParts = filteredJobs.reduce((sum, j) => sum + (j.parts_cost_override ?? j.parts_cost), 0);

    const filteredCommissions = SEED_COMMISSION_RECORDS.filter(cr => {
      return isWithinInterval(new Date(cr.created_at), { start: dateRange.from, end: dateRange.to });
    });
    const totalCommissions = filteredCommissions.reduce((sum, cr) => sum + cr.commission_amount, 0);
    const totalManagerOverrides = filteredCommissions.reduce((sum, cr) => sum + cr.manager_override, 0);
    const totalManagerBonuses = filteredCommissions.reduce((sum, cr) => sum + cr.manager_bonus, 0);
    const totalPayouts = totalCommissions + totalManagerOverrides + totalManagerBonuses;
    const grossProfit = totalRevenue - totalParts - totalPayouts;

    return { totalRevenue, totalParts, totalPayouts, grossProfit, totalJobs: filteredJobs.length };
  }, [dateRange]);

  // Revenue by month chart data
  const revenueChartData = useMemo(() => {
    const months: Record<string, { revenue: number; partsCost: number }> = {};
    SEED_JOBS.forEach(job => {
      if (job.status !== 'completed' || !job.completed_at) return;
      const date = new Date(job.completed_at);
      const key = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`;
      const monthName = date.toLocaleString('en-US', { month: 'short', year: '2-digit' });
      if (!months[key]) months[key] = { revenue: 0, partsCost: 0 };
      months[key].revenue += job.revenue;
      months[key].partsCost += (job.parts_cost_override ?? job.parts_cost);
    });

    return Object.entries(months)
      .sort(([a], [b]) => a.localeCompare(b))
      .slice(-6)
      .map(([key, data]) => {
        const [year, month] = key.split('-');
        const date = new Date(parseInt(year), parseInt(month) - 1);
        return {
          name: date.toLocaleString('en-US', { month: 'short' }),
          revenue: data.revenue,
          partsCost: data.partsCost,
        };
      });
  }, []);

  return (
    <div>
      <Header title="Company Dashboard" subtitle="Twins Garage Doors — Madison, WI" />

      <div className="p-6 space-y-6">
        {/* Financial Summary Cards */}
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
          {[
            { label: 'Total Revenue', value: financialSummary.totalRevenue, format: 'currency' as const, color: '#012650' },
            { label: 'Total Jobs', value: financialSummary.totalJobs, format: 'count' as const, color: '#012650' },
            { label: 'Parts Cost', value: financialSummary.totalParts, format: 'currency' as const, color: '#F59E0B' },
            { label: 'Commission Payouts', value: financialSummary.totalPayouts, format: 'currency' as const, color: '#EF4444' },
            { label: 'Gross Profit', value: financialSummary.grossProfit, format: 'currency' as const, color: '#22C55E' },
          ].map(item => (
            <Card key={item.label}>
              <CardContent className="p-5">
                <p className="text-xs font-medium uppercase tracking-wider text-[#3B445C] mb-1">{item.label}</p>
                <AnimatedNumber value={item.value} format={item.format} className="text-2xl" />
              </CardContent>
            </Card>
          ))}
        </div>

        {/* Company KPIs */}
        <div>
          <h2 className="text-sm font-semibold uppercase tracking-wider text-[#3B445C] mb-3">Company-Wide KPIs</h2>
          <KpiGrid kpis={kpis} />
        </div>

        {/* Charts */}
        <div className="grid grid-cols-1 lg:grid-cols-1 gap-6">
          <RevenueChart data={revenueChartData} title="Monthly Revenue vs Parts Cost" />
        </div>

        {/* Leaderboard */}
        <LeaderboardTable
          data={leaderboard}
          onTechClick={(id) => router.push(`/dashboard/technician/${id}`)}
        />
      </div>
    </div>
  );
}

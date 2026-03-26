'use client';

import { useMemo } from 'react';
import { useRouter } from 'next/navigation';
import { Header } from '@/components/layout/header';
import { KpiGrid } from '@/components/kpi/kpi-grid';
import { LeaderboardTable } from '@/components/tables/leaderboard-table';
import { RevenueChart } from '@/components/charts/revenue-chart';
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
    const margin = totalRevenue > 0 ? (grossProfit / totalRevenue * 100) : 0;

    return { totalRevenue, totalParts, totalPayouts, grossProfit, totalJobs: filteredJobs.length, margin };
  }, [dateRange]);

  // Revenue by month chart data
  const revenueChartData = useMemo(() => {
    const months: Record<string, { revenue: number; partsCost: number }> = {};
    SEED_JOBS.forEach(job => {
      if (job.status !== 'completed' || !job.completed_at) return;
      const date = new Date(job.completed_at);
      const key = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`;
      if (!months[key]) months[key] = { revenue: 0, partsCost: 0 };
      months[key].revenue += job.revenue;
      months[key].partsCost += (job.parts_cost_override ?? job.parts_cost);
    });

    return Object.entries(months)
      .sort(([a], [b]) => a.localeCompare(b))
      .slice(-6)
      .map(([key]) => {
        const [year, month] = key.split('-');
        const date = new Date(parseInt(year), parseInt(month) - 1);
        return {
          name: date.toLocaleString('en-US', { month: 'short' }),
          revenue: months[key].revenue,
          partsCost: months[key].partsCost,
        };
      });
  }, []);

  const financialCards = [
    {
      label: 'Total Revenue',
      value: financialSummary.totalRevenue,
      format: 'currency' as const,
      icon: 'payments',
      iconColor: 'text-secondary',
      accent: 'border-b-4 border-secondary-container',
      trendLabel: `${financialSummary.totalJobs} jobs completed`,
    },
    {
      label: 'Total Jobs',
      value: financialSummary.totalJobs,
      format: 'count' as const,
      icon: 'construction',
      iconColor: 'text-primary',
      accent: '',
      trendLabel: 'In selected period',
    },
    {
      label: 'Parts Cost',
      value: financialSummary.totalParts,
      format: 'currency' as const,
      icon: 'inventory_2',
      iconColor: 'text-error',
      accent: '',
      trendLabel: 'Materials & supplies',
    },
    {
      label: 'Commission',
      value: financialSummary.totalPayouts,
      format: 'currency' as const,
      icon: 'person_celebrate',
      iconColor: 'text-on-primary-container',
      accent: '',
      trendLabel: 'Paid to technicians',
    },
    {
      label: 'Gross Profit',
      value: financialSummary.grossProfit,
      format: 'currency' as const,
      icon: 'account_balance_wallet',
      iconColor: 'text-secondary-container',
      accent: 'bg-primary-container text-white',
      trendLabel: `${financialSummary.margin.toFixed(0)}% Margin`,
      isHighlighted: true,
    },
  ];

  return (
    <div>
      <Header title="Fleet Efficiency" subtitle="Twins Garage Doors — Madison, WI" />

      <div className="px-6 md:px-8 space-y-12 max-w-[1600px] mx-auto py-8">
        {/* Financial Summary Cards */}
        <section className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-6">
          {financialCards.map(item => (
            <div
              key={item.label}
              className={`p-6 rounded-xl card-shadow ${
                item.isHighlighted
                  ? 'bg-primary-container text-white'
                  : `bg-surface-container-lowest ${item.accent}`
              }`}
            >
              <div className="flex items-center justify-between mb-4">
                <span className="text-xs font-bold tracking-wider uppercase text-on-surface-variant">
                  {!item.isHighlighted ? item.label : ''}
                </span>
                {item.isHighlighted && (
                  <span className="text-xs font-bold tracking-wider uppercase text-on-primary-container">{item.label}</span>
                )}
                <span
                  className={`material-symbols-outlined ${item.iconColor}`}
                  style={item.isHighlighted ? { fontVariationSettings: "'FILL' 1" } : {}}
                >
                  {item.icon}
                </span>
              </div>
              <AnimatedNumber
                value={item.value}
                format={item.format}
                className={`text-3xl ${item.isHighlighted ? 'text-white' : 'text-primary'}`}
              />
              <div className={`flex items-center gap-1 mt-2 text-xs font-medium ${
                item.isHighlighted ? 'text-secondary-container' : 'text-on-surface-variant'
              }`}>
                {item.isHighlighted && (
                  <span className="material-symbols-outlined text-[14px]">verified</span>
                )}
                <span>{item.trendLabel}</span>
              </div>
            </div>
          ))}
        </section>

        {/* Company KPIs */}
        <section>
          <h2 className="font-headline font-bold text-2xl text-primary mb-6">Company-Wide KPIs</h2>
          <KpiGrid kpis={kpis} />
        </section>

        {/* Charts */}
        <section>
          <RevenueChart data={revenueChartData} title="Monthly Revenue vs Parts Cost" />
        </section>

        {/* Leaderboard */}
        <section className="pb-12">
          <LeaderboardTable
            data={leaderboard}
            onTechClick={(id) => router.push(`/dashboard/technician/${id}`)}
          />
        </section>
      </div>
    </div>
  );
}

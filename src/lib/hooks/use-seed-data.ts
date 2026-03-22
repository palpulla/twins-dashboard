'use client';

import { useMemo } from 'react';
import { isWithinInterval } from 'date-fns';
import { useDashboardStore } from '@/lib/store/dashboard-store';
import {
  SEED_JOBS, SEED_COMMISSION_RECORDS, SEED_REVIEWS,
  SEED_CALL_RECORDS, SEED_MARKETING_SPEND, SEED_USERS,
  SEED_COMMISSION_TIERS, SEED_CUSTOMERS, SEED_INVOICES,
} from '@/lib/seed-data';
import type { Tables } from '@/types/database';
import type { KpiValue } from '@/types/kpi';
import { KPI_CALCULATORS } from '@/lib/utils/kpi-calculations';
import { DEFAULT_KPI_DEFINITIONS } from '@/lib/constants/kpi-defaults';
import { getPreviousPeriodRange } from '@/lib/utils/date-utils';

function filterByDateRange<T extends { created_at: string } | { completed_at: string | null }>(
  items: T[],
  from: Date,
  to: Date,
  dateField: 'created_at' | 'completed_at' = 'created_at'
): T[] {
  return items.filter(item => {
    const dateStr = (item as Record<string, unknown>)[dateField] as string | null;
    if (!dateStr) return false;
    const date = new Date(dateStr);
    return isWithinInterval(date, { start: from, end: to });
  });
}

export function useTechnicianKpis(technicianId: string) {
  const { dateRange } = useDashboardStore();

  return useMemo(() => {
    const from = dateRange.from;
    const to = dateRange.to;
    const prevRange = getPreviousPeriodRange(dateRange);

    const techJobs = SEED_JOBS.filter(j => j.technician_id === technicianId);
    const currentJobs = filterByDateRange(techJobs, from, to, 'created_at');
    const prevJobs = filterByDateRange(techJobs, prevRange.from, prevRange.to, 'created_at');

    const techCommissions = SEED_COMMISSION_RECORDS.filter(cr => cr.technician_id === technicianId);
    const currentCommissions = filterByDateRange(techCommissions, from, to);
    const prevCommissions = filterByDateRange(techCommissions, prevRange.from, prevRange.to);

    const techReviews = SEED_REVIEWS.filter(r => r.technician_id === technicianId);
    const currentReviews = filterByDateRange(techReviews, from, to);
    const prevReviews = filterByDateRange(techReviews, prevRange.from, prevRange.to);

    const currentInput = { jobs: currentJobs, commissionRecords: currentCommissions, reviews: currentReviews };
    const prevInput = { jobs: prevJobs, commissionRecords: prevCommissions, reviews: prevReviews };

    const kpis: KpiValue[] = [];
    for (const def of DEFAULT_KPI_DEFINITIONS) {
      if (!def.isActive) continue;
      const calc = KPI_CALCULATORS[def.formula];
      if (!calc) continue;

      const value = calc(currentInput);
      const previousValue = calc(prevInput);

      // Generate sparkline from last 7 days
      const sparklineData: number[] = [];
      for (let i = 6; i >= 0; i--) {
        const dayStart = new Date(to);
        dayStart.setDate(dayStart.getDate() - i);
        dayStart.setHours(0, 0, 0, 0);
        const dayEnd = new Date(dayStart);
        dayEnd.setHours(23, 59, 59, 999);
        const dayJobs = filterByDateRange(techJobs, dayStart, dayEnd, 'created_at');
        const dayCommissions = filterByDateRange(techCommissions, dayStart, dayEnd);
        const dayReviews = filterByDateRange(techReviews, dayStart, dayEnd);
        sparklineData.push(calc({ jobs: dayJobs, commissionRecords: dayCommissions, reviews: dayReviews }));
      }

      kpis.push({
        definitionId: def.id,
        name: def.name,
        value,
        target: def.target,
        previousValue,
        displayFormat: def.displayFormat,
        invertedStatus: def.invertedStatus,
        sparklineData,
      });
    }

    return kpis;
  }, [technicianId, dateRange]);
}

export function useCompanyKpis() {
  const { dateRange } = useDashboardStore();

  return useMemo(() => {
    const from = dateRange.from;
    const to = dateRange.to;
    const prevRange = getPreviousPeriodRange(dateRange);

    const currentJobs = filterByDateRange(SEED_JOBS, from, to, 'created_at');
    const prevJobs = filterByDateRange(SEED_JOBS, prevRange.from, prevRange.to, 'created_at');
    const currentCommissions = filterByDateRange(SEED_COMMISSION_RECORDS, from, to);
    const prevCommissions = filterByDateRange(SEED_COMMISSION_RECORDS, prevRange.from, prevRange.to);
    const currentReviews = filterByDateRange(SEED_REVIEWS, from, to);
    const prevReviews = filterByDateRange(SEED_REVIEWS, prevRange.from, prevRange.to);

    const currentInput = { jobs: currentJobs, commissionRecords: currentCommissions, reviews: currentReviews };
    const prevInput = { jobs: prevJobs, commissionRecords: prevCommissions, reviews: prevReviews };

    const results: KpiValue[] = [];
    for (const def of DEFAULT_KPI_DEFINITIONS) {
      if (!def.isActive) continue;
      const calc = KPI_CALCULATORS[def.formula];
      if (!calc) continue;
      results.push({
        definitionId: def.id,
        name: def.name,
        value: calc(currentInput),
        target: def.target,
        previousValue: calc(prevInput),
        displayFormat: def.displayFormat,
        invertedStatus: def.invertedStatus,
      });
    }
    return results;
  }, [dateRange]);
}

export function useTechnicianJobs(technicianId: string) {
  const { dateRange } = useDashboardStore();

  return useMemo(() => {
    const techJobs = SEED_JOBS.filter(j => j.technician_id === technicianId && j.status === 'completed');
    const filtered = filterByDateRange(techJobs, dateRange.from, dateRange.to, 'created_at');

    return filtered.map(job => {
      const customer = SEED_CUSTOMERS.find(c => c.id === job.customer_id);
      const commission = SEED_COMMISSION_RECORDS.find(cr => cr.job_id === job.id);
      return {
        ...job,
        customerName: customer?.name || 'Unknown',
        commissionEarned: commission?.commission_amount || 0,
        netRevenue: (job.parts_cost_override ?? job.parts_cost) ? job.revenue - (job.parts_cost_override ?? job.parts_cost) : job.revenue,
      };
    }).sort((a, b) => new Date(b.completed_at || b.created_at).getTime() - new Date(a.completed_at || a.created_at).getTime());
  }, [technicianId, dateRange]);
}

export function useCsrMetrics(csrId: string) {
  const { dateRange } = useDashboardStore();

  return useMemo(() => {
    const calls = SEED_CALL_RECORDS.filter(c => c.csr_id === csrId);
    const filtered = filterByDateRange(calls, dateRange.from, dateRange.to);
    const totalCalls = filtered.length;
    const booked = filtered.filter(c => c.outcome === 'booked').length;
    const bookingRate = totalCalls > 0 ? (booked / totalCalls) * 100 : 0;

    // Source breakdown
    const sourceCounts: Record<string, { total: number; booked: number }> = {};
    filtered.forEach(call => {
      if (!sourceCounts[call.source]) sourceCounts[call.source] = { total: 0, booked: 0 };
      sourceCounts[call.source].total++;
      if (call.outcome === 'booked') sourceCounts[call.source].booked++;
    });

    return {
      totalCalls,
      appointmentsBooked: booked,
      bookingRate,
      callRecords: filtered.sort((a, b) => new Date(b.created_at).getTime() - new Date(a.created_at).getTime()),
      sourceBreakdown: sourceCounts,
    };
  }, [csrId, dateRange]);
}

export function useMarketingMetrics() {
  const { dateRange } = useDashboardStore();

  return useMemo(() => {
    const spend = filterByDateRange(SEED_MARKETING_SPEND, dateRange.from, dateRange.to);
    const calls = filterByDateRange(SEED_CALL_RECORDS, dateRange.from, dateRange.to);

    const channels = ['google_ads', 'google_lsa', 'meta_ads', 'website_contact_form', 'website_chat', 'organic', 'referral'];

    const channelMetrics = channels.map(channel => {
      const channelSpend = spend.filter(s => s.channel === channel);
      const channelLeads = calls.filter(c => c.source === channel);
      const totalSpend = channelSpend.reduce((sum, s) => sum + s.spend, 0);
      const totalImpressions = channelSpend.reduce((sum, s) => sum + s.impressions, 0);
      const totalClicks = channelSpend.reduce((sum, s) => sum + s.clicks, 0);
      const totalConversions = channelSpend.reduce((sum, s) => sum + s.conversions, 0);
      const totalLeads = channelLeads.length;
      const bookedLeads = channelLeads.filter(l => l.outcome === 'booked').length;

      // Estimate revenue by finding jobs attributed to this channel's leads
      const estimatedRevenue = bookedLeads * 750; // Average ticket estimate

      return {
        channel,
        totalSpend,
        totalImpressions,
        totalClicks,
        totalConversions,
        totalLeads,
        bookedLeads,
        cpa: bookedLeads > 0 ? totalSpend / bookedLeads : 0,
        roi: totalSpend > 0 ? ((estimatedRevenue - totalSpend) / totalSpend) * 100 : 0,
        estimatedRevenue,
      };
    });

    return {
      channelMetrics,
      totalSpend: channelMetrics.reduce((sum, c) => sum + c.totalSpend, 0),
      totalLeads: channelMetrics.reduce((sum, c) => sum + c.totalLeads, 0),
      totalBooked: channelMetrics.reduce((sum, c) => sum + c.bookedLeads, 0),
    };
  }, [dateRange]);
}

export function useLeaderboard() {
  const { dateRange } = useDashboardStore();

  return useMemo(() => {
    const techs = SEED_USERS.filter(u => u.role === 'technician');

    return techs.map(tech => {
      const techJobs = SEED_JOBS.filter(j => j.technician_id === tech.id);
      const filtered = filterByDateRange(techJobs, dateRange.from, dateRange.to, 'created_at');
      const completed = filtered.filter(j => j.status === 'completed');
      const revenueJobs = completed.filter(j => j.revenue > 0 && j.job_type !== 'Warranty Call');
      const totalRevenue = revenueJobs.reduce((sum, j) => sum + j.revenue, 0);
      const avgTicket = revenueJobs.length > 0 ? totalRevenue / revenueJobs.length : 0;
      const conversionRate = filtered.length > 0 ? (revenueJobs.length / filtered.length) * 100 : 0;

      const techReviews = SEED_REVIEWS.filter(r => r.technician_id === tech.id);
      const filteredReviews = filterByDateRange(techReviews, dateRange.from, dateRange.to);
      const fiveStarCount = filteredReviews.filter(r => r.rating === 5).length;

      const doorsInstalled = completed.filter(j => j.job_type === 'Door Install' || j.job_type === 'Door + Opener Install').length;
      const protectionPlans = completed.filter(j => j.protection_plan_sold).length;

      const techCommissions = SEED_COMMISSION_RECORDS.filter(cr => cr.technician_id === tech.id);
      const filteredCommissions = filterByDateRange(techCommissions, dateRange.from, dateRange.to);
      const totalCommission = filteredCommissions.reduce((sum, cr) => sum + cr.commission_amount, 0);

      return {
        ...tech,
        totalRevenue,
        avgTicket,
        conversionRate,
        fiveStarReviews: fiveStarCount,
        doorsInstalled,
        protectionPlans,
        totalJobs: filtered.length,
        completedJobs: completed.length,
        totalCommission,
      };
    });
  }, [dateRange]);
}

export { SEED_USERS, SEED_JOBS, SEED_COMMISSION_TIERS, SEED_CUSTOMERS };

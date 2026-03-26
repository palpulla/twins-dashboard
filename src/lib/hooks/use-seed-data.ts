'use client';

import { useMemo } from 'react';
import { isWithinInterval } from 'date-fns';
import { useDashboardStore } from '@/lib/store/dashboard-store';
import {
  SEED_JOBS, SEED_COMMISSION_RECORDS, SEED_REVIEWS,
  SEED_CALL_RECORDS, SEED_MARKETING_SPEND, SEED_USERS,
  SEED_COMMISSION_TIERS, SEED_CUSTOMERS, SEED_INVOICES,
} from '@/lib/seed-data';
import {
  useSupabaseJobs, useSupabaseCommissionRecords, useSupabaseReviews,
  useSupabaseCallRecords, useSupabaseMarketingSpend, useSupabaseUsers,
  useSupabaseCustomers, useSupabaseCommissionTiers,
} from './use-supabase-data';
import type { Tables } from '@/types/database';
import type { KpiValue } from '@/types/kpi';
import type { UserProfile } from '@/types/roles';
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

// Helper: use Supabase data if available and non-empty, otherwise fall back to seed
function useWithFallback<T>(supabaseData: T[] | null | undefined, seedData: T[]): T[] {
  return (supabaseData && supabaseData.length > 0) ? supabaseData : seedData;
}

// Convert Supabase user rows to UserProfile format
function dbUsersToProfiles(users: Tables<'users'>[]): UserProfile[] {
  return users.map(u => ({
    id: u.id,
    email: u.email,
    fullName: u.full_name,
    role: u.role as UserProfile['role'],
    avatarUrl: u.avatar_url || undefined,
    managerId: u.manager_id || undefined,
    isActive: u.is_active,
    createdAt: u.created_at,
  }));
}

export function useTechnicianKpis(technicianId: string) {
  const { dateRange } = useDashboardStore();
  const prevRange = getPreviousPeriodRange(dateRange);

  const { data: sbJobs } = useSupabaseJobs(dateRange.from, dateRange.to, technicianId);
  const { data: sbPrevJobs } = useSupabaseJobs(prevRange.from, prevRange.to, technicianId);
  const { data: sbCommissions } = useSupabaseCommissionRecords(dateRange.from, dateRange.to, technicianId);
  const { data: sbPrevCommissions } = useSupabaseCommissionRecords(prevRange.from, prevRange.to, technicianId);
  const { data: sbReviews } = useSupabaseReviews(dateRange.from, dateRange.to, technicianId);
  const { data: sbPrevReviews } = useSupabaseReviews(prevRange.from, prevRange.to, technicianId);

  return useMemo(() => {
    const from = dateRange.from;
    const to = dateRange.to;

    // Seed data filtered by tech
    const seedTechJobs = SEED_JOBS.filter(j => j.technician_id === technicianId);
    const seedTechCommissions = SEED_COMMISSION_RECORDS.filter(cr => cr.technician_id === technicianId);
    const seedTechReviews = SEED_REVIEWS.filter(r => r.technician_id === technicianId);

    const currentJobs = useWithFallback(sbJobs, filterByDateRange(seedTechJobs, from, to, 'created_at'));
    const prevJobs = useWithFallback(sbPrevJobs, filterByDateRange(seedTechJobs, prevRange.from, prevRange.to, 'created_at'));
    const currentCommissions = useWithFallback(sbCommissions, filterByDateRange(seedTechCommissions, from, to));
    const prevCommissions = useWithFallback(sbPrevCommissions, filterByDateRange(seedTechCommissions, prevRange.from, prevRange.to));
    const currentReviews = useWithFallback(sbReviews, filterByDateRange(seedTechReviews, from, to));
    const prevReviews = useWithFallback(sbPrevReviews, filterByDateRange(seedTechReviews, prevRange.from, prevRange.to));

    const currentInput = { jobs: currentJobs, commissionRecords: currentCommissions, reviews: currentReviews };
    const prevInput = { jobs: prevJobs, commissionRecords: prevCommissions, reviews: prevReviews };

    const kpis: KpiValue[] = [];
    for (const def of DEFAULT_KPI_DEFINITIONS) {
      if (!def.isActive) continue;
      const calc = KPI_CALCULATORS[def.formula];
      if (!calc) continue;

      const value = calc(currentInput);
      const previousValue = calc(prevInput);

      kpis.push({
        definitionId: def.id,
        name: def.name,
        value,
        target: def.target,
        previousValue,
        displayFormat: def.displayFormat,
        invertedStatus: def.invertedStatus,
      });
    }

    return kpis;
  }, [technicianId, dateRange, sbJobs, sbPrevJobs, sbCommissions, sbPrevCommissions, sbReviews, sbPrevReviews, prevRange]);
}

export function useCompanyKpis() {
  const { dateRange } = useDashboardStore();
  const prevRange = getPreviousPeriodRange(dateRange);

  const { data: sbJobs } = useSupabaseJobs(dateRange.from, dateRange.to);
  const { data: sbPrevJobs } = useSupabaseJobs(prevRange.from, prevRange.to);
  const { data: sbCommissions } = useSupabaseCommissionRecords(dateRange.from, dateRange.to);
  const { data: sbPrevCommissions } = useSupabaseCommissionRecords(prevRange.from, prevRange.to);
  const { data: sbReviews } = useSupabaseReviews(dateRange.from, dateRange.to);
  const { data: sbPrevReviews } = useSupabaseReviews(prevRange.from, prevRange.to);

  return useMemo(() => {
    const from = dateRange.from;
    const to = dateRange.to;

    const currentJobs = useWithFallback(sbJobs, filterByDateRange(SEED_JOBS, from, to, 'created_at'));
    const prevJobs = useWithFallback(sbPrevJobs, filterByDateRange(SEED_JOBS, prevRange.from, prevRange.to, 'created_at'));
    const currentCommissions = useWithFallback(sbCommissions, filterByDateRange(SEED_COMMISSION_RECORDS, from, to));
    const prevCommissions = useWithFallback(sbPrevCommissions, filterByDateRange(SEED_COMMISSION_RECORDS, prevRange.from, prevRange.to));
    const currentReviews = useWithFallback(sbReviews, filterByDateRange(SEED_REVIEWS, from, to));
    const prevReviews = useWithFallback(sbPrevReviews, filterByDateRange(SEED_REVIEWS, prevRange.from, prevRange.to));

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
  }, [dateRange, sbJobs, sbPrevJobs, sbCommissions, sbPrevCommissions, sbReviews, sbPrevReviews, prevRange]);
}

export function useTechnicianJobs(technicianId: string) {
  const { dateRange } = useDashboardStore();

  const { data: sbJobs } = useSupabaseJobs(dateRange.from, dateRange.to, technicianId);
  const { data: sbCustomers } = useSupabaseCustomers();
  const { data: sbCommissions } = useSupabaseCommissionRecords(dateRange.from, dateRange.to, technicianId);

  return useMemo(() => {
    const seedTechJobs = SEED_JOBS.filter(j => j.technician_id === technicianId && j.status === 'completed');
    const seedFiltered = filterByDateRange(seedTechJobs, dateRange.from, dateRange.to, 'created_at');

    const jobs = useWithFallback(sbJobs, seedFiltered).filter(j => j.status === 'completed');
    const customers = useWithFallback(sbCustomers, SEED_CUSTOMERS);
    const commissions = useWithFallback(sbCommissions, SEED_COMMISSION_RECORDS.filter(cr => cr.technician_id === technicianId));

    return jobs.map(job => {
      const customer = customers.find(c => c.id === job.customer_id);
      const commission = commissions.find(cr => cr.job_id === job.id);
      return {
        ...job,
        customerName: customer?.name || 'Unknown',
        commissionEarned: commission?.commission_amount || 0,
        netRevenue: (job.parts_cost_override ?? job.parts_cost) ? job.revenue - (job.parts_cost_override ?? job.parts_cost) : job.revenue,
      };
    }).sort((a, b) => new Date(b.completed_at || b.created_at).getTime() - new Date(a.completed_at || a.created_at).getTime());
  }, [technicianId, dateRange, sbJobs, sbCustomers, sbCommissions]);
}

export function useCsrMetrics(csrId: string) {
  const { dateRange } = useDashboardStore();

  const { data: sbCalls } = useSupabaseCallRecords(dateRange.from, dateRange.to, csrId);

  return useMemo(() => {
    const seedCalls = SEED_CALL_RECORDS.filter(c => c.csr_id === csrId);
    const seedFiltered = filterByDateRange(seedCalls, dateRange.from, dateRange.to);

    const filtered = useWithFallback(sbCalls, seedFiltered);
    const totalCalls = filtered.length;
    const booked = filtered.filter(c => c.outcome === 'booked').length;
    const bookingRate = totalCalls > 0 ? (booked / totalCalls) * 100 : 0;

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
      callRecords: [...filtered].sort((a, b) => new Date(b.created_at).getTime() - new Date(a.created_at).getTime()),
      sourceBreakdown: sourceCounts,
    };
  }, [csrId, dateRange, sbCalls]);
}

export function useMarketingMetrics() {
  const { dateRange } = useDashboardStore();

  const { data: sbSpend } = useSupabaseMarketingSpend(dateRange.from, dateRange.to);
  const { data: sbCalls } = useSupabaseCallRecords(dateRange.from, dateRange.to);

  return useMemo(() => {
    const spend = useWithFallback(sbSpend, filterByDateRange(SEED_MARKETING_SPEND, dateRange.from, dateRange.to));
    const calls = useWithFallback(sbCalls, filterByDateRange(SEED_CALL_RECORDS, dateRange.from, dateRange.to));

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
      const estimatedRevenue = bookedLeads * 750;

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
  }, [dateRange, sbSpend, sbCalls]);
}

export function useLeaderboard() {
  const { dateRange } = useDashboardStore();

  const { data: sbUsers } = useSupabaseUsers();
  const { data: sbJobs } = useSupabaseJobs(dateRange.from, dateRange.to);
  const { data: sbReviews } = useSupabaseReviews(dateRange.from, dateRange.to);
  const { data: sbCommissions } = useSupabaseCommissionRecords(dateRange.from, dateRange.to);

  return useMemo(() => {
    const allUsers = sbUsers && sbUsers.length > 0 ? dbUsersToProfiles(sbUsers) : SEED_USERS;
    const techs = allUsers.filter(u => u.role === 'technician');

    const allJobs = useWithFallback(sbJobs, filterByDateRange(SEED_JOBS, dateRange.from, dateRange.to, 'created_at'));
    const allReviews = useWithFallback(sbReviews, filterByDateRange(SEED_REVIEWS, dateRange.from, dateRange.to));
    const allCommissions = useWithFallback(sbCommissions, filterByDateRange(SEED_COMMISSION_RECORDS, dateRange.from, dateRange.to));

    return techs.map(tech => {
      const techJobs = allJobs.filter(j => j.technician_id === tech.id);
      const completed = techJobs.filter(j => j.status === 'completed');
      const revenueJobs = completed.filter(j => j.revenue > 0 && j.job_type !== 'Warranty Call');
      const totalRevenue = revenueJobs.reduce((sum, j) => sum + j.revenue, 0);
      const avgTicket = revenueJobs.length > 0 ? totalRevenue / revenueJobs.length : 0;
      const conversionRate = techJobs.length > 0 ? (revenueJobs.length / techJobs.length) * 100 : 0;

      const techReviews = allReviews.filter(r => r.technician_id === tech.id);
      const fiveStarCount = techReviews.filter(r => r.rating === 5).length;
      const doorsInstalled = completed.filter(j => j.job_type === 'Door Install' || j.job_type === 'Door + Opener Install').length;
      const protectionPlans = completed.filter(j => j.protection_plan_sold).length;

      const techCommissions = allCommissions.filter(cr => cr.technician_id === tech.id);
      const totalCommission = techCommissions.reduce((sum, cr) => sum + cr.commission_amount, 0);

      return {
        ...tech,
        totalRevenue,
        avgTicket,
        conversionRate,
        fiveStarReviews: fiveStarCount,
        doorsInstalled,
        protectionPlans,
        totalJobs: techJobs.length,
        completedJobs: completed.length,
        totalCommission,
      };
    });
  }, [dateRange, sbUsers, sbJobs, sbReviews, sbCommissions]);
}

export { SEED_USERS, SEED_JOBS, SEED_COMMISSION_TIERS, SEED_CUSTOMERS };

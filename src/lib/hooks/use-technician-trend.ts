'use client';

import { useMemo } from 'react';
import {
  eachDayOfInterval, eachWeekOfInterval, eachMonthOfInterval,
  startOfDay, endOfDay, startOfWeek, endOfWeek,
  startOfMonth, endOfMonth, format,
} from 'date-fns';
import { useDashboardStore } from '@/lib/store/dashboard-store';
import {
  useSupabaseJobs, useSupabaseCommissionRecords, useSupabaseReviews,
} from './use-supabase-data';
import { SEED_JOBS, SEED_COMMISSION_RECORDS, SEED_REVIEWS } from '@/lib/seed-data';
import { KPI_CALCULATORS } from '@/lib/utils/kpi-calculations';

export type TrendGranularity = 'day' | 'week' | 'month';

export interface TrendPoint {
  bucketStart: Date;
  bucketEnd: Date;
  name: string;
  value: number;
}

function withFallback<T>(supa: T[] | null | undefined, seed: T[]): T[] {
  return supa && supa.length > 0 ? supa : seed;
}

function inRange(iso: string | null | undefined, from: Date, to: Date): boolean {
  if (!iso) return false;
  const d = new Date(iso).getTime();
  return d >= from.getTime() && d <= to.getTime();
}

export function useTechnicianTrend(
  technicianId: string,
  kpiFormula: string,
  granularity: TrendGranularity,
): TrendPoint[] {
  const { dateRange } = useDashboardStore();

  const { data: sbJobs } = useSupabaseJobs(dateRange.from, dateRange.to, technicianId);
  const { data: sbCommissions } = useSupabaseCommissionRecords(dateRange.from, dateRange.to, technicianId);
  const { data: sbReviews } = useSupabaseReviews(dateRange.from, dateRange.to, technicianId);

  return useMemo(() => {
    const calc = KPI_CALCULATORS[kpiFormula];
    if (!calc) return [];

    const seedJobs = SEED_JOBS.filter(j =>
      j.technician_id === technicianId && inRange(j.created_at, dateRange.from, dateRange.to),
    );
    const seedCommissions = SEED_COMMISSION_RECORDS.filter(cr =>
      cr.technician_id === technicianId && inRange(cr.created_at, dateRange.from, dateRange.to),
    );
    const seedReviews = SEED_REVIEWS.filter(r =>
      r.technician_id === technicianId && inRange(r.created_at, dateRange.from, dateRange.to),
    );

    const jobs = withFallback(sbJobs, seedJobs);
    const commissions = withFallback(sbCommissions, seedCommissions);
    const reviews = withFallback(sbReviews, seedReviews);

    type Bucket = { start: Date; end: Date; label: string };
    const buckets: Bucket[] = [];

    if (granularity === 'day') {
      eachDayOfInterval({ start: dateRange.from, end: dateRange.to }).forEach(d =>
        buckets.push({ start: startOfDay(d), end: endOfDay(d), label: format(d, 'MMM d') }),
      );
    } else if (granularity === 'week') {
      eachWeekOfInterval(
        { start: dateRange.from, end: dateRange.to },
        { weekStartsOn: 1 },
      ).forEach(d => {
        const start = startOfWeek(d, { weekStartsOn: 1 });
        const end = endOfWeek(d, { weekStartsOn: 1 });
        buckets.push({ start, end, label: format(start, 'MMM d') });
      });
    } else {
      eachMonthOfInterval({ start: dateRange.from, end: dateRange.to }).forEach(d =>
        buckets.push({ start: startOfMonth(d), end: endOfMonth(d), label: format(d, 'MMM yyyy') }),
      );
    }

    return buckets.map(b => {
      const bjobs = jobs.filter(j => inRange(j.created_at, b.start, b.end));
      const bcomm = commissions.filter(cr => inRange(cr.created_at, b.start, b.end));
      const brev = reviews.filter(r => inRange(r.created_at, b.start, b.end));
      const value = calc({ jobs: bjobs, commissionRecords: bcomm, reviews: brev });
      return { bucketStart: b.start, bucketEnd: b.end, name: b.label, value };
    });
  }, [technicianId, kpiFormula, granularity, dateRange, sbJobs, sbCommissions, sbReviews]);
}

export function defaultGranularity(spanDays: number): TrendGranularity {
  if (spanDays <= 31) return 'day';
  if (spanDays <= 180) return 'week';
  return 'month';
}

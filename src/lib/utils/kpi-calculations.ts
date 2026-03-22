import type { Tables } from '@/types/database';
import {
  REPAIR_JOB_TYPES, INSTALLATION_JOB_TYPES,
  NEW_DOOR_JOB_TYPES, WARRANTY_JOB_TYPES,
} from '@/lib/constants/job-types';
import type { JobType } from '@/lib/constants/job-types';

type Job = Tables<'jobs'>;
type CommissionRecord = Tables<'commission_records'>;
type Review = Tables<'reviews'>;

interface KpiInput {
  jobs: Job[];
  commissionRecords: CommissionRecord[];
  reviews: Review[];
  technicianId?: string;
}

function completedRevenueJobs(jobs: Job[]): Job[] {
  return jobs.filter(j =>
    j.status === 'completed' &&
    j.revenue > 0 &&
    !WARRANTY_JOB_TYPES.includes(j.job_type as JobType)
  );
}

function allCompletedJobs(jobs: Job[]): Job[] {
  return jobs.filter(j => j.status === 'completed');
}

export function calcAverageTicket(input: KpiInput): number {
  const revenueJobs = completedRevenueJobs(input.jobs);
  if (revenueJobs.length === 0) return 0;
  const total = revenueJobs.reduce((sum, j) => sum + j.revenue, 0);
  return total / revenueJobs.length;
}

export function calcAverageOpportunity(input: KpiInput): number {
  const completed = allCompletedJobs(input.jobs);
  if (completed.length === 0) return 0;
  const total = completed.reduce((sum, j) => sum + j.revenue, 0);
  return total / completed.length;
}

export function calcConversionRate(input: KpiInput): number {
  const totalOpportunities = input.jobs.length;
  if (totalOpportunities === 0) return 0;
  const converted = input.jobs.filter(j =>
    j.status === 'completed' && j.revenue > 0
  ).length;
  return (converted / totalOpportunities) * 100;
}

export function calcAvgRepairTicket(input: KpiInput): number {
  const repairJobs = completedRevenueJobs(input.jobs).filter(j =>
    REPAIR_JOB_TYPES.includes(j.job_type as JobType)
  );
  if (repairJobs.length === 0) return 0;
  const total = repairJobs.reduce((sum, j) => sum + j.revenue, 0);
  return total / repairJobs.length;
}

export function calcAvgInstallTicket(input: KpiInput): number {
  const installJobs = completedRevenueJobs(input.jobs).filter(j =>
    INSTALLATION_JOB_TYPES.includes(j.job_type as JobType)
  );
  if (installJobs.length === 0) return 0;
  const total = installJobs.reduce((sum, j) => sum + j.revenue, 0);
  return total / installJobs.length;
}

export function calcNewDoorsInstalled(input: KpiInput): number {
  return allCompletedJobs(input.jobs).filter(j =>
    NEW_DOOR_JOB_TYPES.includes(j.job_type as JobType)
  ).length;
}

export function calcTotalOpportunities(input: KpiInput): number {
  return input.jobs.length;
}

export function calcFiveStarReviews(input: KpiInput): number {
  return input.reviews.filter(r => r.rating === 5).length;
}

export function calcProtectionPlanSales(input: KpiInput): number {
  return input.jobs.filter(j => j.protection_plan_sold).length;
}

export function calcCommission(input: KpiInput): number {
  return input.commissionRecords.reduce((sum, cr) => sum + cr.commission_amount, 0);
}

export function calcCallbackRate(input: KpiInput): number {
  const completed = allCompletedJobs(input.jobs);
  if (completed.length === 0) return 0;
  const callbacks = completed.filter(j =>
    WARRANTY_JOB_TYPES.includes(j.job_type as JobType)
  ).length;
  return (callbacks / completed.length) * 100;
}

export type KpiCalculator = (input: KpiInput) => number;

export const KPI_CALCULATORS: Record<string, KpiCalculator> = {
  avg_ticket: calcAverageTicket,
  avg_opportunity: calcAverageOpportunity,
  conversion_rate: calcConversionRate,
  avg_repair_ticket: calcAvgRepairTicket,
  avg_install_ticket: calcAvgInstallTicket,
  new_doors_installed: calcNewDoorsInstalled,
  total_opportunities: calcTotalOpportunities,
  five_star_reviews: calcFiveStarReviews,
  protection_plan_sales: calcProtectionPlanSales,
  commission: calcCommission,
  callback_rate: calcCallbackRate,
};

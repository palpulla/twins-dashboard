/**
 * Commission Calculation Engine
 *
 * All monetary calculations use integer cents internally to avoid
 * floating-point precision issues. Input values are in dollars,
 * internally converted to cents, then results returned in dollars.
 *
 * Commission Formula per job:
 *   (Invoice Total - Parts Cost) × Tier Rate = Commission
 *
 * Manager Override: 2% of net revenue for each job by techs under them
 *
 * Manager Bonus (per ticket, based on net revenue):
 *   $400+ → $20 base
 *   $500+ → $30 ($20 + $10)
 *   $600+ → $40 ($20 + $10 + $10)
 *   $700+ → $50 ($20 + $10 + $10 + $10)
 *   Pattern: +$10 for every additional $100 above $400
 */

const MANAGER_OVERRIDE_RATE = 0.02;
const MANAGER_BONUS_BASE_THRESHOLD = 400;
const MANAGER_BONUS_BASE_AMOUNT = 20;
const MANAGER_BONUS_STEP_THRESHOLD = 100;
const MANAGER_BONUS_STEP_AMOUNT = 10;

export interface CommissionTier {
  level: 1 | 2 | 3;
  rate: number;
}

export const COMMISSION_TIERS: Record<number, CommissionTier> = {
  1: { level: 1, rate: 0.16 },
  2: { level: 2, rate: 0.18 },
  3: { level: 3, rate: 0.20 },
};

function toCents(dollars: number): number {
  return Math.round(dollars * 100);
}

function toDollars(cents: number): number {
  return cents / 100;
}

export function getEffectivePartsCost(
  partsCost: number,
  partsCostOverride: number | null
): number {
  return partsCostOverride !== null ? partsCostOverride : partsCost;
}

export function calculateNetRevenue(
  invoiceTotal: number,
  partsCost: number,
  partsCostOverride: number | null = null
): number {
  const effectivePartsCost = getEffectivePartsCost(partsCost, partsCostOverride);
  const netCents = toCents(invoiceTotal) - toCents(effectivePartsCost);
  return toDollars(Math.max(0, netCents));
}

export function calculateTechCommission(
  invoiceTotal: number,
  partsCost: number,
  tierRate: number,
  partsCostOverride: number | null = null
): number {
  const netRevenue = calculateNetRevenue(invoiceTotal, partsCost, partsCostOverride);
  const commissionCents = Math.round(toCents(netRevenue) * tierRate);
  return toDollars(commissionCents);
}

export function calculateManagerOverride(
  invoiceTotal: number,
  partsCost: number,
  partsCostOverride: number | null = null
): number {
  const netRevenue = calculateNetRevenue(invoiceTotal, partsCost, partsCostOverride);
  const overrideCents = Math.round(toCents(netRevenue) * MANAGER_OVERRIDE_RATE);
  return toDollars(overrideCents);
}

export function calculateManagerBonus(netRevenue: number): number {
  if (netRevenue < MANAGER_BONUS_BASE_THRESHOLD) {
    return 0;
  }

  let bonus = MANAGER_BONUS_BASE_AMOUNT;
  let threshold = MANAGER_BONUS_BASE_THRESHOLD + MANAGER_BONUS_STEP_THRESHOLD;

  while (netRevenue >= threshold) {
    bonus += MANAGER_BONUS_STEP_AMOUNT;
    threshold += MANAGER_BONUS_STEP_THRESHOLD;
  }

  return bonus;
}

export interface JobCommissionResult {
  grossRevenue: number;
  partsCost: number;
  netRevenue: number;
  tierRate: number;
  techCommission: number;
  managerOverride: number;
  managerBonus: number;
}

export function calculateJobCommission(
  invoiceTotal: number,
  partsCost: number,
  tierRate: number,
  partsCostOverride: number | null = null
): JobCommissionResult {
  const netRevenue = calculateNetRevenue(invoiceTotal, partsCost, partsCostOverride);
  const techCommission = calculateTechCommission(invoiceTotal, partsCost, tierRate, partsCostOverride);
  const managerOverride = calculateManagerOverride(invoiceTotal, partsCost, partsCostOverride);
  const managerBonus = calculateManagerBonus(netRevenue);

  return {
    grossRevenue: invoiceTotal,
    partsCost: getEffectivePartsCost(partsCost, partsCostOverride),
    netRevenue,
    tierRate,
    techCommission,
    managerOverride,
    managerBonus,
  };
}

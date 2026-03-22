import {
  startOfDay, endOfDay, startOfWeek, endOfWeek,
  startOfMonth, endOfMonth, startOfQuarter, endOfQuarter,
  startOfYear, endOfYear, subDays, subWeeks, subMonths,
  subQuarters, subYears,
} from 'date-fns';
import { toZonedTime } from 'date-fns-tz';
import type { DatePreset, DateRange } from '@/types/kpi';

const TIMEZONE = 'America/Chicago';

function nowCT(): Date {
  return toZonedTime(new Date(), TIMEZONE);
}

export function getDateRange(preset: DatePreset, customFrom?: Date, customTo?: Date): DateRange {
  const now = nowCT();

  switch (preset) {
    case 'today':
      return { from: startOfDay(now), to: endOfDay(now), preset };
    case 'yesterday': {
      const yesterday = subDays(now, 1);
      return { from: startOfDay(yesterday), to: endOfDay(yesterday), preset };
    }
    case 'this_week':
      return { from: startOfWeek(now, { weekStartsOn: 1 }), to: endOfWeek(now, { weekStartsOn: 1 }), preset };
    case 'last_week': {
      const lastWeek = subWeeks(now, 1);
      return { from: startOfWeek(lastWeek, { weekStartsOn: 1 }), to: endOfWeek(lastWeek, { weekStartsOn: 1 }), preset };
    }
    case 'this_month':
      return { from: startOfMonth(now), to: endOfMonth(now), preset };
    case 'last_month': {
      const lastMonth = subMonths(now, 1);
      return { from: startOfMonth(lastMonth), to: endOfMonth(lastMonth), preset };
    }
    case 'this_quarter':
      return { from: startOfQuarter(now), to: endOfQuarter(now), preset };
    case 'last_quarter': {
      const lastQ = subQuarters(now, 1);
      return { from: startOfQuarter(lastQ), to: endOfQuarter(lastQ), preset };
    }
    case 'this_year':
      return { from: startOfYear(now), to: endOfYear(now), preset };
    case 'last_year': {
      const lastYear = subYears(now, 1);
      return { from: startOfYear(lastYear), to: endOfYear(lastYear), preset };
    }
    case 'all_time':
      return { from: new Date(2020, 0, 1), to: endOfDay(now), preset };
    case 'custom':
      return {
        from: customFrom ? startOfDay(customFrom) : startOfMonth(now),
        to: customTo ? endOfDay(customTo) : endOfDay(now),
        preset,
      };
  }
}

export function getPreviousPeriodRange(range: DateRange): DateRange {
  const durationMs = range.to.getTime() - range.from.getTime();
  return {
    from: new Date(range.from.getTime() - durationMs),
    to: new Date(range.from.getTime() - 1),
    preset: range.preset,
  };
}

export const DATE_PRESET_LABELS: Record<DatePreset, string> = {
  today: 'Today',
  yesterday: 'Yesterday',
  this_week: 'This Week',
  last_week: 'Last Week',
  this_month: 'This Month',
  last_month: 'Last Month',
  this_quarter: 'This Quarter',
  last_quarter: 'Last Quarter',
  this_year: 'This Year',
  last_year: 'Last Year',
  all_time: 'All Time',
  custom: 'Custom Range',
};

export { TIMEZONE };

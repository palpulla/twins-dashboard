export type DisplayFormat = 'currency' | 'percentage' | 'count';

export interface KpiDefinition {
  id: string;
  name: string;
  description: string;
  formula: string;
  dataSource: string;
  target: number;
  displayFormat: DisplayFormat;
  isActive: boolean;
  invertedStatus?: boolean;
  sortOrder: number;
}

export interface KpiValue {
  definitionId: string;
  name: string;
  value: number;
  target: number;
  previousValue?: number;
  displayFormat: DisplayFormat;
  invertedStatus?: boolean;
  sparklineData?: number[];
}

export type DatePreset =
  | 'today'
  | 'yesterday'
  | 'this_week'
  | 'last_week'
  | 'this_month'
  | 'last_month'
  | 'this_quarter'
  | 'last_quarter'
  | 'this_year'
  | 'last_year'
  | 'all_time'
  | 'custom';

export interface DateRange {
  from: Date;
  to: Date;
  preset: DatePreset;
}

import { create } from 'zustand';
import type { DateRange, DatePreset } from '@/types/kpi';
import { getDateRange } from '@/lib/utils/date-utils';

interface DashboardState {
  dateRange: DateRange;
  selectedTechnicianId: string | null;
  sidebarOpen: boolean;
  setDatePreset: (preset: DatePreset) => void;
  setCustomDateRange: (from: Date, to: Date) => void;
  setSelectedTechnicianId: (id: string | null) => void;
  toggleSidebar: () => void;
  setSidebarOpen: (open: boolean) => void;
}

export const useDashboardStore = create<DashboardState>((set) => ({
  dateRange: getDateRange('this_month'),
  selectedTechnicianId: null,
  sidebarOpen: true,
  setDatePreset: (preset) => set({ dateRange: getDateRange(preset) }),
  setCustomDateRange: (from, to) => set({ dateRange: getDateRange('custom', from, to) }),
  setSelectedTechnicianId: (id) => set({ selectedTechnicianId: id }),
  toggleSidebar: () => set((state) => ({ sidebarOpen: !state.sidebarOpen })),
  setSidebarOpen: (open) => set({ sidebarOpen: open }),
}));

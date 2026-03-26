'use client';

import { useDashboardStore } from '@/lib/store/dashboard-store';
import { DateRangePicker } from '@/components/ui/date-range-picker';

interface HeaderProps {
  title: string;
  subtitle?: string;
  showDatePicker?: boolean;
  actions?: React.ReactNode;
}

export function Header({ title, subtitle, showDatePicker = true, actions }: HeaderProps) {
  const { toggleSidebar } = useDashboardStore();

  return (
    <header className="sticky top-0 z-30 bg-[#f8f9fd]/80 backdrop-blur-md border-b border-outline-variant/20">
      <div className="flex items-center justify-between px-6 md:px-8 py-4">
        <div className="flex items-center gap-4">
          <button
            onClick={toggleSidebar}
            className="p-2 text-on-surface-variant hover:text-primary hover:bg-surface-container rounded-lg transition-colors lg:hidden"
          >
            <span className="material-symbols-outlined">menu</span>
          </button>
          <div className="flex flex-col">
            <h1 className="font-headline font-bold text-lg md:text-2xl tracking-tight text-primary leading-tight">{title}</h1>
            {subtitle && <p className="text-sm text-on-surface-variant/70">{subtitle}</p>}
          </div>
        </div>
        <div className="flex items-center gap-4">
          {actions}
          {showDatePicker && <DateRangePicker />}
        </div>
      </div>
    </header>
  );
}

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
    <header className="sticky top-0 z-30 bg-[#F5F6FA]/80 backdrop-blur-sm border-b border-gray-200">
      <div className="flex items-center justify-between h-16 px-6">
        <div className="flex items-center gap-4">
          <button
            onClick={toggleSidebar}
            className="p-2 text-[#3B445C] hover:text-[#012650] hover:bg-white rounded-lg transition-colors lg:hidden"
          >
            <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 6h16M4 12h16M4 18h16" />
            </svg>
          </button>
          <div>
            <h1 className="text-xl font-bold text-[#012650]">{title}</h1>
            {subtitle && <p className="text-sm text-[#3B445C]">{subtitle}</p>}
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

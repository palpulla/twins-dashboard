'use client';

import { useDashboardStore } from '@/lib/store/dashboard-store';

export function MobileNav() {
  const { sidebarOpen, setSidebarOpen } = useDashboardStore();

  if (!sidebarOpen) return null;

  return (
    <div
      className="fixed inset-0 bg-black/50 z-30 lg:hidden"
      onClick={() => setSidebarOpen(false)}
    />
  );
}

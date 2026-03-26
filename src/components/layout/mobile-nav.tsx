'use client';

import Link from 'next/link';
import { usePathname } from 'next/navigation';
import { useAuthStore } from '@/lib/store/auth-store';
import { useDashboardStore } from '@/lib/store/dashboard-store';

interface MobileNavItem {
  label: string;
  href: string;
  icon: string;
  roles: string[];
}

const MOBILE_NAV_ITEMS: MobileNavItem[] = [
  { label: 'Dashboard', href: '/dashboard', icon: 'dashboard', roles: ['owner', 'manager'] },
  { label: 'Tech', href: '/dashboard/technician', icon: 'engineering', roles: ['owner', 'manager', 'technician'] },
  { label: 'Mktg', href: '/dashboard/marketing', icon: 'campaign', roles: ['owner', 'manager'] },
  { label: 'Admin', href: '/dashboard/admin', icon: 'settings', roles: ['owner'] },
];

export function MobileNav() {
  const pathname = usePathname();
  const { user } = useAuthStore();
  const { sidebarOpen, setSidebarOpen } = useDashboardStore();

  const userRole = user?.role || 'owner';
  const filteredItems = MOBILE_NAV_ITEMS.filter(item => item.roles.includes(userRole));

  return (
    <>
      {/* Backdrop overlay */}
      {sidebarOpen && (
        <div
          className="fixed inset-0 bg-black/50 z-30 lg:hidden"
          onClick={() => setSidebarOpen(false)}
        />
      )}

      {/* Bottom nav bar */}
      <nav className="md:hidden fixed bottom-0 left-0 w-full flex justify-around items-center p-2 pb-[env(safe-area-inset-bottom,8px)] bg-[#012650] rounded-t-xl z-50 shadow-[0_-4px_12px_rgba(0,0,0,0.1)]">
        {filteredItems.map((item) => {
          const isActive = pathname === item.href || (item.href !== '/dashboard' && pathname.startsWith(item.href));
          return (
            <Link
              key={item.href}
              href={item.href}
              className={`flex flex-col items-center justify-center px-3 py-1 transition-all duration-150 ${
                isActive
                  ? 'bg-[#795900] text-white rounded-xl shadow-inner'
                  : 'text-white/60 active:bg-white/5'
              }`}
            >
              <span
                className="material-symbols-outlined text-[20px]"
                style={isActive ? { fontVariationSettings: "'FILL' 1" } : {}}
              >
                {item.icon}
              </span>
              <span className="text-[10px] font-medium mt-1">{item.label}</span>
            </Link>
          );
        })}
      </nav>
    </>
  );
}

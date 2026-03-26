'use client';

import Link from 'next/link';
import { usePathname } from 'next/navigation';
import { useAuthStore } from '@/lib/store/auth-store';
import { useDashboardStore } from '@/lib/store/dashboard-store';

interface NavItem {
  label: string;
  href: string;
  icon: string;
  roles: string[];
}

const NAV_ITEMS: NavItem[] = [
  { label: 'Dashboard', href: '/dashboard', icon: 'dashboard', roles: ['owner', 'manager'] },
  { label: 'Technician', href: '/dashboard/technician', icon: 'engineering', roles: ['owner', 'manager', 'technician'] },
  { label: 'CSR', href: '/dashboard/csr', icon: 'headset_mic', roles: ['owner', 'manager', 'csr'] },
  { label: 'Marketing', href: '/dashboard/marketing', icon: 'campaign', roles: ['owner', 'manager'] },
  { label: 'Admin', href: '/dashboard/admin', icon: 'settings', roles: ['owner'] },
];

export function Sidebar() {
  const pathname = usePathname();
  const { user } = useAuthStore();
  const { sidebarOpen } = useDashboardStore();

  const userRole = user?.role || 'owner';
  const filteredItems = NAV_ITEMS.filter(item => item.roles.includes(userRole));

  return (
    <aside
      className={`fixed left-0 top-0 h-full glass-sidebar text-white transition-all duration-300 z-40 flex flex-col shadow-[0_12px_32px_-4px_rgba(1,38,80,0.06)] ${
        sidebarOpen ? 'w-64' : 'w-20'
      }`}
    >
      {/* Logo */}
      <div className="flex items-center h-16 px-4 border-b border-white/10 bg-gradient-to-br from-[#012650] to-[#00112b]">
        <div className="flex items-center gap-3">
          <div className="w-10 h-10 bg-secondary-container rounded-lg flex items-center justify-center font-bold text-on-secondary-container text-lg flex-shrink-0">
            TG
          </div>
          {sidebarOpen && (
            <div className="overflow-hidden">
              <p className="font-bold text-sm leading-tight font-headline tracking-tight">Twins Garage</p>
              <p className="text-xs text-white/60">Doors</p>
            </div>
          )}
        </div>
      </div>

      {/* Navigation */}
      <nav className="flex-1 py-6 space-y-2 px-4">
        {filteredItems.map((item) => {
          const isActive = pathname === item.href || (item.href !== '/dashboard' && pathname.startsWith(item.href));
          return (
            <Link
              key={item.href}
              href={item.href}
              className={`flex items-center gap-3 px-3 py-2.5 rounded-xl transition-all duration-200 group ${
                isActive
                  ? 'bg-gradient-to-br from-[#012650] to-[#00112b] text-white font-semibold shadow-lg'
                  : 'text-white/70 hover:bg-white/10 hover:text-white'
              }`}
              title={!sidebarOpen ? item.label : undefined}
            >
              <span
                className="material-symbols-outlined flex-shrink-0"
                style={isActive ? { fontVariationSettings: "'FILL' 1" } : {}}
              >
                {item.icon}
              </span>
              {sidebarOpen && (
                <span className="text-sm font-headline tracking-tight">{item.label}</span>
              )}
            </Link>
          );
        })}
      </nav>

      {/* User info */}
      {user && (
        <div className="p-4 border-t border-white/10">
          <div className="flex items-center gap-3 p-2">
            <div className="w-10 h-10 rounded-full bg-secondary-container flex items-center justify-center text-on-secondary-container font-bold text-xs flex-shrink-0">
              {user.fullName.split(' ').map(n => n[0]).join('').toUpperCase()}
            </div>
            {sidebarOpen && (
              <div className="overflow-hidden">
                <p className="text-sm font-semibold truncate">{user.fullName}</p>
                <p className="text-xs text-white/50 capitalize">{user.role}</p>
              </div>
            )}
          </div>
        </div>
      )}
    </aside>
  );
}

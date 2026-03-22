'use client';

import { useEffect } from 'react';
import { useRouter } from 'next/navigation';
import { Sidebar } from '@/components/layout/sidebar';
import { MobileNav } from '@/components/layout/mobile-nav';
import { useAuthStore } from '@/lib/store/auth-store';
import { useDashboardStore } from '@/lib/store/dashboard-store';
import { SEED_USERS } from '@/lib/seed-data';

export default function DashboardLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  const router = useRouter();
  const { user, setUser } = useAuthStore();
  const { sidebarOpen } = useDashboardStore();

  // Auto-login as CEO for demo purposes
  useEffect(() => {
    if (!user) {
      const ceo = SEED_USERS.find(u => u.role === 'owner');
      if (ceo) {
        setUser(ceo);
      } else {
        router.push('/login');
      }
    }
  }, [user, setUser, router]);

  return (
    <div className="min-h-screen bg-[#F5F6FA]">
      <Sidebar />
      <MobileNav />
      <main
        className={`transition-all duration-300 ${
          sidebarOpen ? 'lg:ml-64' : 'lg:ml-20'
        }`}
      >
        {children}
      </main>
    </div>
  );
}

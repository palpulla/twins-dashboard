'use client';

import Link from 'next/link';
import { Header } from '@/components/layout/header';

const ADMIN_SECTIONS = [
  {
    title: 'User Management',
    description: 'Control permissions, onboard new staff, and manage organizational hierarchy settings.',
    href: '/dashboard/admin/users',
    icon: 'manage_accounts',
    iconBg: 'bg-primary-container',
    iconColor: 'text-white',
  },
  {
    title: 'Commission Config',
    description: 'Set tiers, adjust percentages, and define automated payout schedules for technicians.',
    href: '/dashboard/admin/commissions',
    icon: 'payments',
    iconBg: 'bg-secondary-container',
    iconColor: 'text-on-secondary-container',
  },
  {
    title: 'KPI Definitions',
    description: 'Add new KPIs, edit formulas, set targets, activate/deactivate metrics.',
    href: '/dashboard/admin/kpis',
    icon: 'monitoring',
    iconBg: 'bg-primary-container',
    iconColor: 'text-white',
  },
  {
    title: 'Integration Health',
    description: 'Monitor webhook status, API connections, error rates across all integrations.',
    href: '/dashboard/admin/integrations',
    icon: 'bolt',
    iconBg: 'bg-secondary-container',
    iconColor: 'text-on-secondary-container',
  },
  {
    title: 'Parts Library',
    description: 'Manage the parts catalog. Edit prices individually or in bulk by $ amount or %, scoped by category or selection.',
    href: '/dashboard/admin/parts',
    icon: 'category',
    iconBg: 'bg-primary-container',
    iconColor: 'text-white',
  },
];

export default function AdminPage() {
  return (
    <div>
      <Header title="Admin Panel" showDatePicker={false} />

      <section className="px-6 md:px-12 py-8 bg-surface-container-low">
        <div className="max-w-5xl mx-auto">
          <div className="flex flex-col gap-1">
            <h2 className="text-3xl md:text-4xl font-headline font-bold text-primary tracking-tight">System configuration & management</h2>
            <p className="text-on-surface-variant">Exclusive access for system owners and high-level administrators.</p>
          </div>
        </div>
      </section>

      <section className="px-6 md:px-12 py-10">
        <div className="max-w-5xl mx-auto">
          <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
            {ADMIN_SECTIONS.map(section => (
              <Link key={section.href} href={section.href}>
                <button className="group relative flex flex-col items-start p-8 bg-surface-container-lowest rounded-xl card-shadow hover:scale-[0.98] transition-all duration-200 text-left overflow-hidden w-full">
                  <div className={`w-14 h-14 rounded-full ${section.iconBg} flex items-center justify-center mb-6 shadow-lg`}>
                    <span className={`material-symbols-outlined ${section.iconColor} text-3xl`}>{section.icon}</span>
                  </div>
                  <h3 className="text-xl font-headline font-bold text-primary mb-2">{section.title}</h3>
                  <p className="text-on-surface-variant text-sm leading-relaxed">{section.description}</p>
                </button>
              </Link>
            ))}
          </div>
        </div>
      </section>
    </div>
  );
}

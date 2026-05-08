'use client'
import Link from 'next/link'
import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { useUnresolvedCount } from '@/lib/hooks/use-unresolved-count'
import { IssuePill } from './issue-pill'

interface DropdownAlert {
  id: string
  job_id: string
  hcp_id: string | null
  job_summary: string
  customer_label: string
  total_amount: number
  primary_tech_name: string
  co_tech_name: string | null
  pills: { label: string; variant: 'red' | 'amber' | 'orange' }[]
}

export function NotificationsBell() {
  const [open, setOpen] = useState(false)
  const { data: count = 0 } = useUnresolvedCount()
  const dropdown = useQuery({
    queryKey: ['notifications', 'dropdown'],
    queryFn: async () => {
      const res = await fetch('/api/notifications?limit=4')
      return (await res.json()) as { alerts: DropdownAlert[] }
    },
    enabled: open,
  })

  return (
    <div className="relative">
      <button
        onClick={() => setOpen(o => !o)}
        className="relative p-2 text-on-surface-variant hover:text-primary hover:bg-surface-container rounded-lg transition-colors"
        aria-label={`Notifications (${count} unresolved)`}
      >
        <span className="material-symbols-outlined">notifications</span>
        {count > 0 && (
          <span className="absolute top-0.5 right-0.5 min-w-[16px] h-4 px-1 bg-red-600 text-white text-[10px] font-bold rounded-full flex items-center justify-center border-2 border-[#f8f9fd]">
            {count > 99 ? '99+' : count}
          </span>
        )}
      </button>

      {open && (
        <div className="absolute right-0 top-12 w-96 bg-white border border-gray-200 rounded-xl shadow-xl z-50">
          <div className="flex justify-between items-center p-3 border-b border-gray-100">
            <strong className="text-sm">Notifications</strong>
            {count > 0 && <IssuePill label={`${count} unresolved`} variant="red" />}
          </div>
          {dropdown.isLoading && <div className="p-4 text-sm text-gray-500">Loading…</div>}
          {dropdown.data?.alerts.length === 0 && <div className="p-4 text-sm text-gray-500">No unresolved alerts.</div>}
          {dropdown.data?.alerts.map(a => (
            <div key={a.id} className="p-3 border-b border-gray-100 hover:bg-gray-50">
              <div className="flex justify-between text-sm">
                <strong>Job #{a.hcp_id ?? a.job_id.slice(0, 6)} · {a.job_summary}</strong>
                <span className="text-xs text-gray-500">${a.total_amount.toLocaleString()}</span>
              </div>
              <div className="text-xs text-gray-500 my-1">
                {a.primary_tech_name}{a.co_tech_name ? ` (+${a.co_tech_name})` : ''}
              </div>
              <div className="flex flex-wrap gap-1">
                {a.pills.map((p, i) => <IssuePill key={i} label={p.label} variant={p.variant} />)}
              </div>
            </div>
          ))}
          <div className="p-2 text-center">
            <Link href="/dashboard/admin/notifications" className="text-sm font-semibold text-blue-900 hover:underline">
              View all notifications →
            </Link>
          </div>
        </div>
      )}
    </div>
  )
}

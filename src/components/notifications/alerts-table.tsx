'use client'
import { useState } from 'react'
import { useQueryClient } from '@tanstack/react-query'
import { IssuePill } from './issue-pill'

interface AlertRow {
  id: string
  alert_type: 'missing_buttons' | 'missing_notes'
  details: { type: string; missing?: string[]; total_amount?: number; pay_overdue_hours?: number }
  digest_date: string
  attributed_tech: { id: string; full_name: string } | null
  jobs: {
    id: string; hcp_id: string | null; job_type: string | null; revenue: number; completed_at: string | null
    customers: { first_name: string | null; last_name: string | null } | null
    job_technicians: { users: { id: string; full_name: string } }[]
  } | null
}

export function AlertsTable({ rows }: { rows: AlertRow[] }) {
  const qc = useQueryClient()
  const [pending, setPending] = useState<Set<string>>(new Set())

  const byJob = new Map<string, AlertRow[]>()
  for (const r of rows) {
    const k = r.jobs?.id ?? r.id
    byJob.set(k, [...(byJob.get(k) ?? []), r])
  }

  if (byJob.size === 0) {
    return <div className="bg-white border border-gray-200 rounded-lg p-6 text-sm text-gray-500 text-center">All clear — no unresolved alerts.</div>
  }

  async function resolve(id: string) {
    setPending(p => new Set(p).add(id))
    await fetch(`/api/notifications/${id}/resolve`, { method: 'POST' })
    qc.invalidateQueries({ queryKey: ['notifications'] })
    setPending(p => { const s = new Set(p); s.delete(id); return s })
  }

  return (
    <table className="w-full text-sm bg-white border border-gray-200 rounded-lg overflow-hidden">
      <thead>
        <tr className="bg-gray-50 text-left text-[11px] uppercase tracking-wider text-gray-500">
          <th className="p-3">Job</th>
          <th className="p-3">Tech</th>
          <th className="p-3">Total</th>
          <th className="p-3">Issues</th>
          <th className="p-3"></th>
        </tr>
      </thead>
      <tbody>
        {[...byJob.values()].map(group => {
          const j = group[0].jobs!
          const techs = j.job_technicians.map(jt => jt.users.full_name)
          const primary = group[0].attributed_tech?.full_name ?? techs[0] ?? 'Unassigned'
          const co = techs.find(n => n !== primary)
          return (
            <tr key={j.id} className="border-t border-gray-100">
              <td className="p-3 align-top">
                <strong>#{j.hcp_id ?? j.id.slice(0, 6)}</strong>
                <div className="text-xs text-gray-500">
                  {j.customers ? `${j.customers.first_name?.[0] ?? ''}. ${j.customers.last_name ?? ''}` : '—'} · {j.job_type ?? 'Job'}
                </div>
              </td>
              <td className="p-3 align-top">
                {primary}
                {co && <div className="text-[11px] text-gray-500">+{co}</div>}
              </td>
              <td className="p-3 align-top">${(j.revenue ?? 0).toLocaleString()}</td>
              <td className="p-3 align-top">
                <div className="flex flex-wrap gap-1">
                  {group.flatMap(r => {
                    if (r.alert_type === 'missing_notes')
                      return [<IssuePill key={r.id} label="No Notes" variant="amber" />]
                    return (r.details.missing ?? []).map((m: string) => {
                      if (m === 'PAY' && r.details.pay_overdue_hours)
                        return <IssuePill key={`${r.id}-${m}`} label={`Pay overdue ${Math.floor(r.details.pay_overdue_hours / 24)}d`} variant="orange" />
                      return <IssuePill key={`${r.id}-${m}`} label={`No ${m}`} variant="red" />
                    })
                  })}
                </div>
              </td>
              <td className="p-3 align-top">
                {group.map(r => (
                  <button key={r.id}
                    onClick={() => resolve(r.id)}
                    disabled={pending.has(r.id)}
                    className="text-xs px-2 py-1 border border-gray-300 rounded hover:bg-gray-50 disabled:opacity-50 mr-1 mb-1">
                    {pending.has(r.id) ? '…' : 'Mark resolved'}
                  </button>
                ))}
              </td>
            </tr>
          )
        })}
      </tbody>
    </table>
  )
}

'use client'
import { useQuery } from '@tanstack/react-query'

interface DigestSummary {
  digest_date: string
  ticket_count: number
  issue_count: number
  resolved_count: number
}

export function PastDigestsList() {
  const { data, isLoading } = useQuery({
    queryKey: ['notifications', 'past-digests'],
    queryFn: async () => {
      const res = await fetch('/api/notifications/past-digests')
      return (await res.json()) as { digests: DigestSummary[] }
    },
  })

  if (isLoading) return <div className="text-sm text-gray-500">Loading…</div>
  if (!data?.digests.length) return <div className="text-sm text-gray-500 bg-white border rounded-lg p-4">No digests yet.</div>

  return (
    <div className="space-y-1.5">
      {data.digests.map(d => (
        <div key={d.digest_date} className="grid grid-cols-[120px_1fr_auto_auto] gap-4 items-center bg-white border rounded-lg px-4 py-2.5 text-sm">
          <span className="text-gray-500 text-xs">{new Date(d.digest_date).toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric' })}</span>
          <span>{d.ticket_count} tickets · {d.issue_count} issues</span>
          <span className="text-xs text-gray-500">{d.resolved_count} resolved</span>
          <span className="text-xs text-gray-400">View →</span>
        </div>
      ))}
    </div>
  )
}

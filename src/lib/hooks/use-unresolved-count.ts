'use client'
import { useQuery } from '@tanstack/react-query'

export function useUnresolvedCount() {
  return useQuery({
    queryKey: ['notifications', 'count'],
    queryFn: async () => {
      const res = await fetch('/api/notifications/count')
      const json = await res.json()
      return json.count as number
    },
    staleTime: 60_000,
  })
}

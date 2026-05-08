import { NextResponse } from 'next/server'
import { createServerSupabaseClient } from '@/lib/supabase/server'

export async function GET(req: Request) {
  const url = new URL(req.url)
  const limit = Math.min(parseInt(url.searchParams.get('limit') ?? '50', 10), 100)

  const supabase = await createServerSupabaseClient()
  const { data, error } = await supabase
    .from('supervisor_alerts')
    .select(`
      id, alert_type, details, digest_date,
      jobs:job_id ( id, hcp_id, job_type, revenue ),
      attributed_tech:attributed_tech_id ( id, full_name )
    `)
    .is('resolved_at', null)
    .order('digest_date', { ascending: false })
    .limit(limit)

  if (error) return NextResponse.json({ alerts: [], error: error.message }, { status: 500 })

  type Row = {
    id: string
    alert_type: string
    details: { missing?: string[]; total_amount?: number; pay_overdue_hours?: number } | null
    jobs: { id?: string; hcp_id?: string | null; job_type?: string | null; revenue?: number } | null
    attributed_tech: { id?: string; full_name?: string } | null
  }

  const alerts = ((data ?? []) as unknown as Row[]).map((r) => ({
    id: r.id,
    job_id: r.jobs?.id ?? '',
    hcp_id: r.jobs?.hcp_id ?? null,
    job_summary: r.jobs?.job_type ?? 'Job',
    customer_label: 'Customer',
    total_amount: Number(r.jobs?.revenue ?? 0),
    primary_tech_name: r.attributed_tech?.full_name ?? '—',
    co_tech_name: null as string | null,
    pills: pillsFor(r.alert_type, r.details),
  }))
  return NextResponse.json({ alerts })
}

function pillsFor(type: string, details: { missing?: string[]; pay_overdue_hours?: number } | null) {
  if (type === 'missing_notes') return [{ label: 'No Notes', variant: 'amber' as const }]
  return (details?.missing ?? []).map((m: string) => ({
    label: m === 'PAY' ? 'Pay overdue' : `No ${m}`,
    variant: m === 'PAY' ? ('orange' as const) : ('red' as const),
  }))
}

// supabase/functions/daily-supervisor-digest/index.ts
import { createClient } from 'https://esm.sh/@supabase/supabase-js@2'
import { renderDigestHtml, renderDigestSubject, type RenderTicket } from './render-email.ts'
import { sendDigestEmail } from './send-email.ts'
import { evaluateMissingButtons, evaluateMissingNotes, attributeTech } from './rules-vendored.ts'

const SUPABASE_URL = Deno.env.get('SUPABASE_URL')!
const SERVICE_ROLE = Deno.env.get('SUPABASE_SERVICE_ROLE_KEY')!
const RESEND_KEY = Deno.env.get('RESEND_API_KEY')

Deno.serve(async (_req) => {
  try {
    const supabase = createClient(SUPABASE_URL, SERVICE_ROLE, { auth: { persistSession: false } })

    // 1. Load settings
    const { data: settings, error: settingsErr } = await supabase
      .from('app_settings').select('*').eq('id', 1).single()
    if (settingsErr || !settings) throw new Error('app_settings row missing')

    const now = new Date()
    const digestDate = now.toISOString().slice(0, 10)

    // 2. Determine windows
    const lastSent = settings.last_digest_sent_at ? new Date(settings.last_digest_sent_at) : null
    const recentStart = lastSent ?? new Date(now.getTime() - 24 * 60 * 60 * 1000)
    const recentEnd = now

    const payGraceEnd = new Date(now.getTime() - settings.pay_grace_hours * 60 * 60 * 1000)
    const payGraceStart = new Date(payGraceEnd.getTime() - 24 * 60 * 60 * 1000)

    // 3. Pass A — recent finishers (Rule 1 except PAY, plus Rule 2)
    const { data: recentJobs } = await supabase
      .from('jobs')
      .select(`
        id, hcp_id, job_type, revenue,
        scheduled_at, started_at, completed_at, invoiced_at, work_notes,
        customers:customer_id ( first_name, last_name ),
        invoices ( id, paid_at ),
        job_technicians ( technician_id, assigned_at, users:technician_id ( id, full_name ) )
      `)
      .gte('completed_at', recentStart.toISOString())
      .lte('completed_at', recentEnd.toISOString())

    // 4. Pass B — PAY-grace agers
    const { data: payAgerJobs } = await supabase
      .from('jobs')
      .select(`
        id, hcp_id, revenue, completed_at,
        invoices ( id, paid_at ),
        job_technicians ( technician_id, users:technician_id ( id, full_name ) )
      `)
      .gte('completed_at', payGraceStart.toISOString())
      .lt('completed_at', payGraceEnd.toISOString())
      .gt('revenue', 0)

    const alerts: Array<{ job_id: string; alert_type: string; details: unknown; attributed_tech_id: string | null; digest_date: string }> = []
    const renderTickets: RenderTicket[] = []

    for (const j of recentJobs ?? []) {
      const job = mapJob(j)
      const buttons = evaluateMissingButtons(job, settings, now)
      const notes = evaluateMissingNotes(job, settings)
      if (!buttons && !notes) continue

      const attribTechId = attributeTech(job.assigned_techs, settings.co_tech_default_user_id)
      if (buttons) alerts.push({ ...buttons, attributed_tech_id: attribTechId, digest_date: digestDate })
      if (notes) alerts.push({ ...notes, attributed_tech_id: attribTechId, digest_date: digestDate })

      renderTickets.push(toRenderTicket(job, [buttons, notes].filter(Boolean) as never[]))
    }

    for (const j of payAgerJobs ?? []) {
      const job = mapJob(j)
      const result = evaluateMissingButtons(job, settings, now)
      if (!result) continue
      const dets = result.details as { type: 'missing_buttons'; missing: string[] }
      if (!dets.missing.includes('PAY')) continue
      const attribTechId = attributeTech(job.assigned_techs, settings.co_tech_default_user_id)
      alerts.push({ ...result, attributed_tech_id: attribTechId, digest_date: digestDate })
      renderTickets.push(toRenderTicket(job, [result] as never[]))
    }

    // 6. Persist
    if (alerts.length > 0) {
      await supabase.from('supervisor_alerts').upsert(
        alerts,
        { onConflict: 'job_id,alert_type,digest_date', ignoreDuplicates: false },
      )
    }

    // 7. Email — render union of newly-found + still-unresolved-from-prior-days
    const { data: unresolved } = await supabase
      .from('supervisor_alerts')
      .select('*, jobs!inner(*, customers:customer_id(*))')
      .is('resolved_at', null)
      .order('digest_date', { ascending: false })

    if ((unresolved?.length ?? 0) > 0 && RESEND_KEY) {
      const grouped = groupByJob(unresolved!)
      const subject = renderDigestSubject(grouped)
      const html = renderDigestHtml({
        digest_date: digestDate,
        total_revenue_today: (recentJobs ?? []).reduce((s, j: any) => s + Number(j.revenue ?? 0), 0),
        tickets: grouped,
      })
      await sendDigestEmail({
        to: settings.digest_recipient_email,
        subject, html, apiKey: RESEND_KEY,
      })
    }

    // 8. Mark sent
    await supabase.from('app_settings').update({ last_digest_sent_at: now.toISOString() }).eq('id', 1)

    return new Response(JSON.stringify({ ok: true, alerts: alerts.length, sent: !!RESEND_KEY && (unresolved?.length ?? 0) > 0 }), {
      status: 200, headers: { 'content-type': 'application/json' },
    })
  } catch (err) {
    console.error('digest failed', err)
    return new Response(JSON.stringify({ ok: false, error: String(err) }), {
      status: 500, headers: { 'content-type': 'application/json' },
    })
  }
})

// --- helpers ---

function mapJob(j: any) {
  return {
    id: j.id,
    hcp_id: j.hcp_id,
    customer_first_name: j.customers?.first_name ?? null,
    customer_last_name: j.customers?.last_name ?? null,
    job_type: j.job_type ?? null,
    total_amount: Number(j.revenue ?? 0),
    scheduled_at: j.scheduled_at ?? null,
    started_at: j.started_at ?? null,
    completed_at: j.completed_at ?? null,
    invoiced_at: j.invoiced_at ?? null,
    work_notes: j.work_notes ?? null,
    assigned_techs: (j.job_technicians ?? [])
      .sort((a: any, b: any) =>
        (a.assigned_at ?? '').localeCompare(b.assigned_at ?? '') || (a.technician_id ?? '').localeCompare(b.technician_id ?? ''))
      .map((jt: any) => ({ id: jt.users?.id ?? jt.technician_id, full_name: jt.users?.full_name ?? '' })),
    // TODO: OMW status events aren't captured with timestamps in this schema.
    // Either add a job_status_events junction or extend jobs with omw_at, then populate here.
    status_events: [],
    invoice: (j.invoices?.[0]) ? { id: j.invoices[0].id, paid_at: j.invoices[0].paid_at } : null,
  }
}

function toRenderTicket(job: ReturnType<typeof mapJob>, _alerts: any[]): RenderTicket {
  const primary = job.assigned_techs[0]?.full_name ?? 'Unassigned'
  const cust = `${(job.customer_first_name ?? '').slice(0,1)}. ${job.customer_last_name ?? ''}`.trim()
  return {
    job_id: job.id,
    hcp_id: job.hcp_id,
    job_summary: job.job_type ?? 'Job',
    customer_label: cust || 'Customer',
    total_amount: job.total_amount,
    finished_at: job.completed_at ?? new Date().toISOString(),
    primary_tech_name: primary,
    co_tech_name: job.assigned_techs.length > 1 ? job.assigned_techs[1].full_name : null,
    alerts: _alerts.map((a: any) => ({ type: a.alert_type, details: a.details })),
  }
}

function groupByJob(rows: any[]): RenderTicket[] {
  const byId = new Map<string, any[]>()
  for (const r of rows) {
    const arr = byId.get(r.job_id) ?? []
    arr.push(r); byId.set(r.job_id, arr)
  }
  return [...byId.values()].map(group => {
    const j = group[0].jobs
    return {
      job_id: j.id, hcp_id: j.hcp_id,
      job_summary: j.job_type ?? 'Job',
      customer_label: `${(j.customers?.first_name ?? '').slice(0,1)}. ${j.customers?.last_name ?? ''}`.trim() || 'Customer',
      total_amount: Number(j.revenue ?? 0),
      finished_at: j.completed_at ?? new Date().toISOString(),
      primary_tech_name: 'Tech', co_tech_name: null,
      alerts: group.map(g => ({ type: g.alert_type, details: g.details })),
    }
  })
}

import { createClient } from '@supabase/supabase-js'
import { evaluateMissingButtons, evaluateMissingNotes } from '../src/lib/alerts/rules'
import { attributeTech } from '../src/lib/alerts/attribution'

const SUPABASE_URL = process.env.SUPABASE_URL ?? 'http://127.0.0.1:54321'
const SERVICE_ROLE = process.env.SERVICE_ROLE!
if (!SERVICE_ROLE) { console.error('SERVICE_ROLE env var required'); process.exit(1) }

const supabase = createClient(SUPABASE_URL, SERVICE_ROLE, { auth: { persistSession: false } })

async function main() {
  const { data: settings } = await supabase
    .from('app_settings').select('*').eq('id', 1).single()
  if (!settings) { console.error('no settings'); return }

  const now = new Date()
  const recentStart = new Date(now.getTime() - 24 * 60 * 60 * 1000)

  const { data: recentJobs, error } = await supabase
    .from('jobs')
    .select(`
      id, hcp_id, job_type, revenue,
      scheduled_at, started_at, completed_at, invoiced_at, work_notes,
      customers:customer_id ( name ),
      invoices ( id, paid_at ),
      job_technicians ( technician_id, assigned_at, users:technician_id ( id, full_name ) )
    `)
    .gte('completed_at', recentStart.toISOString())
    .lte('completed_at', now.toISOString())

  if (error) { console.error('query error:', error); return }
  console.log(`\n=== Found ${recentJobs?.length ?? 0} recent jobs ===\n`)

  for (const j of (recentJobs ?? []) as any[]) {
    const job: any = {
      id: j.id, hcp_id: j.hcp_id, customer_name: j.customers?.name ?? null,
      job_type: j.job_type, total_amount: Number(j.revenue ?? 0),
      scheduled_at: j.scheduled_at, started_at: j.started_at, completed_at: j.completed_at,
      invoiced_at: j.invoiced_at, work_notes: j.work_notes,
      assigned_techs: (j.job_technicians ?? [])
        .sort((a: any, b: any) => (a.assigned_at ?? '').localeCompare(b.assigned_at ?? '') || (a.technician_id ?? '').localeCompare(b.technician_id ?? ''))
        .map((jt: any) => ({ id: jt.users?.id ?? jt.technician_id, full_name: jt.users?.full_name ?? '' })),
      status_events: [],
      invoice: (j.invoices?.[0]) ? { id: j.invoices[0].id, paid_at: j.invoices[0].paid_at } : null,
    }

    const buttons = evaluateMissingButtons(job, settings, now)
    const notes = evaluateMissingNotes(job, settings)
    const attribTech = attributeTech(job.assigned_techs, settings.co_tech_default_user_id)
    const attribName = job.assigned_techs.find((t: any) => t.id === attribTech)?.full_name ?? '?'

    console.log(`Job #${job.hcp_id} - $${job.total_amount} - customer: ${job.customer_name}`)
    console.log(`  Techs assigned (in order): ${job.assigned_techs.map((t: any) => t.full_name).join(' -> ')}`)
    console.log(`  Attributed to: ${attribName}`)
    console.log(`  Missing buttons: ${buttons ? (buttons.details as any).missing.join(',') : 'none'}`)
    console.log(`  Missing notes: ${notes ? 'YES (under threshold + blank)' : 'no'}`)
  }
}

main().catch(e => { console.error(e); process.exit(1) })

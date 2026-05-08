// scripts/backfill-job-fields.ts
// Backfill jobs.work_notes / started_at and the job_technicians junction from
// historical raw_events rows. Safe to re-run: only fills nulls, and the
// junction insert is idempotent via upsert + ignoreDuplicates.
//
// Usage: npx tsx scripts/backfill-job-fields.ts
//
// Required env: SUPABASE_URL, SUPABASE_SERVICE_ROLE_KEY
//
// HCP payload shape for these fields was not verified against a real webhook.
// If field names differ in production, adjust the JobEventPayload interface.
// See: docs/superpowers/plans/2026-05-08-supervisor-ticket-discipline-alerts.md

import { createClient } from '@supabase/supabase-js'

const supabase = createClient(
  process.env.SUPABASE_URL!,
  process.env.SUPABASE_SERVICE_ROLE_KEY!,
)

interface JobEventPayload {
  id?: string
  notes?: string | null
  assigned_employees?: { id: string; assigned_at?: string }[]
}

async function main() {
  // Pull every raw HCP job event we have stored
  const { data: events, error } = await supabase
    .from('raw_events')
    .select('payload, event_type, created_at')
    .like('event_type', 'job.%')
    .order('created_at', { ascending: true })

  if (error) throw error
  if (!events) return

  let updated = 0
  for (const ev of events) {
    const p = ev.payload as JobEventPayload
    if (!p.id) continue

    // Find the job by hcp_id
    const { data: job } = await supabase
      .from('jobs')
      .select('id, work_notes, started_at')
      .eq('hcp_id', p.id)
      .single()

    if (!job) continue

    const updates: Record<string, unknown> = {}
    if (job.work_notes === null && p.notes) updates.work_notes = p.notes
    if (job.started_at === null && ev.event_type === 'job.started') updates.started_at = ev.created_at

    if (Object.keys(updates).length > 0) {
      await supabase.from('jobs').update(updates).eq('id', job.id)
      updated++
    }

    // Populate job_technicians from assigned_employees. users.hcp_id may not
    // exist in the current schema; if the lookup fails, log and continue so
    // the rest of the backfill keeps running.
    if (p.assigned_employees?.length) {
      try {
        const { data: techs, error: techsError } = await supabase
          .from('users')
          .select('id, hcp_id')
          .in('hcp_id', p.assigned_employees.map((e) => e.id))

        if (techsError) {
          console.warn(`Skipping job_technicians populate for job ${job.id}: ${techsError.message}`)
        } else if (techs?.length) {
          const rows = techs
            .map((t: { id: string; hcp_id?: string }) => {
              const ae = p.assigned_employees!.find((e) => e.id === t.hcp_id)
              return ae
                ? { job_id: job.id, technician_id: t.id, assigned_at: ae.assigned_at ?? ev.created_at }
                : null
            })
            .filter((r): r is { job_id: string; technician_id: string; assigned_at: string } => r !== null)

          if (rows.length > 0) {
            await supabase
              .from('job_technicians')
              .upsert(rows, { onConflict: 'job_id,technician_id', ignoreDuplicates: true })
          }
        }
      } catch (e) {
        console.warn(`Skipping job_technicians populate for job ${job.id}: ${e}`)
      }
    }
  }

  console.log(`Backfilled ${updated} jobs.`)
}

main().catch((e) => {
  console.error(e)
  process.exit(1)
})

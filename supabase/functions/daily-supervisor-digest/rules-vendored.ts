// THIS IS A VENDORED COPY of src/lib/alerts/{rules,attribution,types}.ts
// for Deno (edge functions can't import from src/).
// When you change the originals, update this file.

// --- types.ts ---

export type ButtonCheck =
  | 'SCHEDULE' | 'OMW' | 'START' | 'FINISH' | 'INVOICE' | 'PAY'

export interface JobForAlerting {
  id: string
  hcp_id: string | null
  customer_name: string | null
  job_type: string | null
  total_amount: number              // dollars (post-conversion from HCP cents)
  scheduled_at: string | null       // ISO timestamps throughout
  started_at: string | null
  completed_at: string | null
  invoiced_at: string | null
  work_notes: string | null
  /** All techs assigned to the job, ordered by `assigned_at ASC, technician_id ASC`. */
  assigned_techs: { id: string; full_name: string }[]
  /** Status events captured from HCP webhooks; we only need `on_my_way` presence. */
  status_events: { event: string; at: string }[]
  /** Latest invoice, if any. */
  invoice: { id: string; paid_at: string | null } | null
}

export interface AppSettingsForAlerting {
  notes_threshold_dollars: number
  pay_grace_hours: number
  enabled_button_checks: ButtonCheck[]
  co_tech_default_user_id: string | null
}

export type AlertType = 'missing_buttons' | 'missing_notes'

export interface AlertRow {
  job_id: string
  alert_type: AlertType
  attributed_tech_id: string | null
  details: AlertDetails
}

export type AlertDetails =
  | { type: 'missing_buttons'; missing: ButtonCheck[]; pay_overdue_hours?: number }
  | { type: 'missing_notes'; total_amount: number }

// --- attribution.ts ---

/**
 * Resolves "who owns this alert" using the Charles co-tech rule.
 * @param techs Already ordered by assigned_at ASC, id ASC.
 * @param coTechDefaultUserId The configurable Charles user UUID; null disables the rule.
 */
export function attributeTech(
  techs: JobForAlerting['assigned_techs'],
  coTechDefaultUserId: string | null,
): string | null {
  if (techs.length === 0) return null
  if (techs.length === 1) return techs[0].id
  if (!coTechDefaultUserId) return techs[0].id
  const nonCharles = techs.find(t => t.id !== coTechDefaultUserId)
  return nonCharles ? nonCharles.id : techs[0].id
}

// --- rules.ts ---

export function evaluateMissingButtons(
  job: JobForAlerting,
  settings: AppSettingsForAlerting,
  now: Date,
): AlertRow | null {
  const enabled = new Set<ButtonCheck>(settings.enabled_button_checks)
  const missing: ButtonCheck[] = []

  if (enabled.has('SCHEDULE') && !job.scheduled_at) missing.push('SCHEDULE')
  if (enabled.has('OMW') && !job.status_events.some(e => e.event === 'on_my_way')) missing.push('OMW')
  if (enabled.has('START') && !job.started_at) missing.push('START')
  if (enabled.has('FINISH') && !job.completed_at) missing.push('FINISH')

  // INVOICE/PAY skip when total = 0
  if (job.total_amount > 0) {
    if (enabled.has('INVOICE') && !job.invoiced_at) missing.push('INVOICE')

    if (enabled.has('PAY') && job.completed_at) {
      const finishedAt = new Date(job.completed_at).getTime()
      const ageHours = (now.getTime() - finishedAt) / (1000 * 60 * 60)
      const pastGrace = ageHours >= settings.pay_grace_hours
      const unpaid = job.invoice && job.invoice.paid_at === null
      if (pastGrace && unpaid) missing.push('PAY')
    }
  }

  if (missing.length === 0) return null

  const payOverdueHours =
    missing.includes('PAY') && job.completed_at
      ? Math.floor((now.getTime() - new Date(job.completed_at).getTime()) / (1000 * 60 * 60))
      : undefined

  return {
    job_id: job.id,
    alert_type: 'missing_buttons',
    attributed_tech_id: null,
    details: { type: 'missing_buttons', missing, ...(payOverdueHours !== undefined && { pay_overdue_hours: payOverdueHours }) },
  }
}

export function evaluateMissingNotes(
  job: JobForAlerting,
  settings: AppSettingsForAlerting,
): AlertRow | null {
  if (job.total_amount >= settings.notes_threshold_dollars) return null
  const notes = (job.work_notes ?? '').trim()
  if (notes.length > 0) return null
  return {
    job_id: job.id,
    alert_type: 'missing_notes',
    attributed_tech_id: null,
    details: { type: 'missing_notes', total_amount: job.total_amount },
  }
}

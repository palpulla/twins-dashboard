// src/lib/alerts/rules.ts
import type {
  AlertRow, AppSettingsForAlerting, ButtonCheck, JobForAlerting,
} from './types'

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

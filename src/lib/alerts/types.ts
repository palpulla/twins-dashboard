// src/lib/alerts/types.ts

export type ButtonCheck =
  | 'SCHEDULE' | 'OMW' | 'START' | 'FINISH' | 'INVOICE' | 'PAY'

export interface JobForAlerting {
  id: string
  hcp_id: string | null
  customer_first_name: string | null
  customer_last_name: string | null
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

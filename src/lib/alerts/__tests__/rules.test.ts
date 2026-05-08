// src/lib/alerts/__tests__/rules.test.ts
import { describe, it, expect } from 'vitest'
import { evaluateMissingButtons, evaluateMissingNotes } from '../rules'
import type { JobForAlerting, AppSettingsForAlerting } from '../types'

const baseJob: JobForAlerting = {
  id: 'job-1', hcp_id: '123',
  customer_first_name: 'Sarah', customer_last_name: 'Jenkins',
  job_type: 'service_call', total_amount: 99,
  scheduled_at: '2026-05-08T14:00:00Z', started_at: '2026-05-08T15:00:00Z',
  completed_at: '2026-05-08T16:00:00Z', invoiced_at: '2026-05-08T16:30:00Z',
  work_notes: 'Replaced springs',
  assigned_techs: [{ id: 'tech-1', full_name: 'Maurice' }],
  status_events: [{ event: 'on_my_way', at: '2026-05-08T14:30:00Z' }],
  invoice: { id: 'inv-1', paid_at: '2026-05-08T16:35:00Z' },
}

const baseSettings: AppSettingsForAlerting = {
  notes_threshold_dollars: 185,
  pay_grace_hours: 48,
  enabled_button_checks: ['SCHEDULE','OMW','START','FINISH','INVOICE','PAY'],
  co_tech_default_user_id: null,
}

const NOW = new Date('2026-05-09T00:00:00Z')  // 8 hours after baseJob completed_at

describe('evaluateMissingButtons', () => {
  it('returns null when all buttons clicked', () => {
    expect(evaluateMissingButtons(baseJob, baseSettings, NOW)).toBeNull()
  })

  it('flags missing OMW (no on_my_way event)', () => {
    const r = evaluateMissingButtons({ ...baseJob, status_events: [] }, baseSettings, NOW)
    expect(r?.details.type).toBe('missing_buttons')
    expect(r?.details.type === 'missing_buttons' && r.details.missing).toContain('OMW')
  })

  it('flags missing START / SCHEDULE / FINISH / INVOICE when null', () => {
    const r = evaluateMissingButtons(
      { ...baseJob, scheduled_at: null, started_at: null, invoiced_at: null },
      baseSettings, NOW)
    expect(r?.details.type === 'missing_buttons' && r.details.missing.sort()).toEqual(
      ['INVOICE','SCHEDULE','START'].sort())
  })

  it('skips PAY when total_amount is 0', () => {
    const r = evaluateMissingButtons(
      { ...baseJob, total_amount: 0, invoice: null, invoiced_at: null },
      baseSettings, NOW)
    expect(r).toBeNull()  // SCHEDULE/OMW/START/FINISH all set, PAY+INVOICE skipped
  })

  it('skips INVOICE when total_amount is 0 even if no invoice', () => {
    const r = evaluateMissingButtons(
      { ...baseJob, total_amount: 0, invoice: null, invoiced_at: null },
      baseSettings, NOW)
    expect(r).toBeNull()
  })

  it('does NOT flag PAY within grace period', () => {
    // completed 8h ago, grace=48h => PAY not flagged
    const r = evaluateMissingButtons(
      { ...baseJob, invoice: { id: 'inv-1', paid_at: null } },
      baseSettings, NOW)
    expect(r).toBeNull()
  })

  it('flags PAY past grace period', () => {
    // 50h after completed_at
    const now = new Date('2026-05-10T18:00:00Z')
    const r = evaluateMissingButtons(
      { ...baseJob, invoice: { id: 'inv-1', paid_at: null } },
      baseSettings, now)
    expect(r?.details.type === 'missing_buttons' && r.details.missing).toEqual(['PAY'])
  })

  it('respects enabled_button_checks toggle', () => {
    const r = evaluateMissingButtons(
      { ...baseJob, scheduled_at: null },
      { ...baseSettings, enabled_button_checks: ['OMW','START','FINISH','INVOICE','PAY'] },
      NOW)
    expect(r).toBeNull()  // SCHEDULE check disabled, all others pass
  })
})

describe('evaluateMissingNotes', () => {
  it('null when total >= threshold', () => {
    const r = evaluateMissingNotes({ ...baseJob, total_amount: 185, work_notes: null }, baseSettings)
    expect(r).toBeNull()
  })

  it('null when notes present', () => {
    const r = evaluateMissingNotes({ ...baseJob, total_amount: 99 }, baseSettings)
    expect(r).toBeNull()
  })

  it('flags when total < threshold AND notes blank', () => {
    const r = evaluateMissingNotes({ ...baseJob, total_amount: 99, work_notes: null }, baseSettings)
    expect(r?.alert_type).toBe('missing_notes')
  })

  it('flags when total = 0 (warranty visit)', () => {
    const r = evaluateMissingNotes({ ...baseJob, total_amount: 0, work_notes: '' }, baseSettings)
    expect(r?.alert_type).toBe('missing_notes')
  })

  it('treats whitespace-only notes as blank', () => {
    const r = evaluateMissingNotes({ ...baseJob, total_amount: 99, work_notes: '   ' }, baseSettings)
    expect(r?.alert_type).toBe('missing_notes')
  })
})

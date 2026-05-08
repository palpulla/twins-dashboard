// supabase/functions/daily-supervisor-digest/__test__/render.test.ts
import { describe, it, expect } from 'vitest'
import { renderDigestHtml, renderDigestSubject } from '../render-email'

describe('renderDigestHtml', () => {
  it('produces non-empty HTML with subject + tickets', () => {
    const html = renderDigestHtml({
      digest_date: '2026-05-08',
      total_revenue_today: 1816,
      tickets: [{
        job_id: 'a', hcp_id: '4521', job_summary: 'Service call', customer_label: 'S. Jenkins',
        total_amount: 99, finished_at: '2026-05-08T16:00:00Z',
        primary_tech_name: 'Maurice', co_tech_name: null,
        alerts: [{ type: 'missing_notes', details: {} }, { type: 'missing_buttons', details: { missing: ['OMW'] } }],
      }],
    })
    expect(html).toContain('Job #4521')
    expect(html).toContain('Missing Notes')
    expect(html).toContain('Missing OMW')
    expect(renderDigestSubject([{ alerts: [{}, {}] } as any, { alerts: [{}] } as any])).toContain('3 issues across 2 tickets')
  })
})

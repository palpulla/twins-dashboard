// src/lib/alerts/__tests__/window.test.ts
import { describe, it, expect } from 'vitest'
import { recentFinisherWindow, payGraceWindow } from '../window'

describe('recentFinisherWindow', () => {
  it('uses last_digest_sent_at as start when present', () => {
    const now = new Date('2026-05-08T23:00:00Z')
    const last = new Date('2026-05-07T23:00:00Z')
    expect(recentFinisherWindow(now, last)).toEqual({
      start: last.toISOString(),
      end: now.toISOString(),
    })
  })

  it('falls back to 24h when last_digest_sent_at is null', () => {
    const now = new Date('2026-05-08T23:00:00Z')
    const w = recentFinisherWindow(now, null)
    expect(w.end).toBe(now.toISOString())
    expect(new Date(w.start).getTime()).toBe(now.getTime() - 24 * 60 * 60 * 1000)
  })
})

describe('payGraceWindow', () => {
  it('returns the band of jobs that just aged past grace', () => {
    const now = new Date('2026-05-08T23:00:00Z')
    const w = payGraceWindow(now, 48)
    // Jobs finished between (now - 72h) and (now - 48h) are newly eligible
    expect(new Date(w.start).getTime()).toBe(now.getTime() - 72 * 60 * 60 * 1000)
    expect(new Date(w.end).getTime()).toBe(now.getTime() - 48 * 60 * 60 * 1000)
  })
})

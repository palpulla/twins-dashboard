// src/lib/alerts/window.ts
export interface TimeWindow { start: string; end: string }

export function recentFinisherWindow(now: Date, lastDigestSentAt: Date | null): TimeWindow {
  const start = lastDigestSentAt ?? new Date(now.getTime() - 24 * 60 * 60 * 1000)
  return { start: start.toISOString(), end: now.toISOString() }
}

export function payGraceWindow(now: Date, payGraceHours: number): TimeWindow {
  const end = new Date(now.getTime() - payGraceHours * 60 * 60 * 1000)
  const start = new Date(end.getTime() - 24 * 60 * 60 * 1000)
  return { start: start.toISOString(), end: end.toISOString() }
}

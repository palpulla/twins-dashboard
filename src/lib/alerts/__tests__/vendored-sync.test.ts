// src/lib/alerts/__tests__/vendored-sync.test.ts
import { describe, it, expect } from 'vitest'
import { readFileSync } from 'node:fs'

describe('vendored rules', () => {
  it('contains evaluateMissingButtons, evaluateMissingNotes, attributeTech', () => {
    const text = readFileSync('supabase/functions/daily-supervisor-digest/rules-vendored.ts', 'utf8')
    expect(text).toContain('export function evaluateMissingButtons')
    expect(text).toContain('export function evaluateMissingNotes')
    expect(text).toContain('export function attributeTech')
  })
})

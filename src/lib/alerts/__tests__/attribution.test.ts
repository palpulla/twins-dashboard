// src/lib/alerts/__tests__/attribution.test.ts
import { describe, it, expect } from 'vitest'
import { attributeTech } from '../attribution'

const CHARLES = 'charles-uuid'
const MAURICE = 'maurice-uuid'
const NICHOLAS = 'nicholas-uuid'

describe('attributeTech', () => {
  it('returns null when there are no assigned techs', () => {
    expect(attributeTech([], CHARLES)).toBeNull()
  })

  it('returns the only tech when one is assigned, even if Charles', () => {
    expect(attributeTech([{ id: CHARLES, full_name: 'Charles' }], CHARLES)).toBe(CHARLES)
    expect(attributeTech([{ id: MAURICE, full_name: 'Maurice' }], CHARLES)).toBe(MAURICE)
  })

  it('returns the first non-Charles tech when Charles is one of multiple', () => {
    expect(
      attributeTech(
        [{ id: CHARLES, full_name: 'Charles' }, { id: MAURICE, full_name: 'Maurice' }],
        CHARLES,
      ),
    ).toBe(MAURICE)
    expect(
      attributeTech(
        [{ id: MAURICE, full_name: 'Maurice' }, { id: CHARLES, full_name: 'Charles' }],
        CHARLES,
      ),
    ).toBe(MAURICE)
  })

  it('returns the first tech when multiple techs and none are Charles', () => {
    expect(
      attributeTech(
        [{ id: NICHOLAS, full_name: 'Nicholas' }, { id: MAURICE, full_name: 'Maurice' }],
        CHARLES,
      ),
    ).toBe(NICHOLAS)
  })

  it('falls back to first tech when co_tech_default_user_id is null', () => {
    expect(
      attributeTech(
        [{ id: CHARLES, full_name: 'Charles' }, { id: MAURICE, full_name: 'Maurice' }],
        null,
      ),
    ).toBe(CHARLES)
  })
})

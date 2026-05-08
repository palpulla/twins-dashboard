// src/lib/alerts/attribution.ts
import type { JobForAlerting } from './types'

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

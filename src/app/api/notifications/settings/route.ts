import { NextResponse } from 'next/server'
import { createServerSupabaseClient } from '@/lib/supabase/server'

const ALL_BUTTONS = ['SCHEDULE','OMW','START','FINISH','INVOICE','PAY'] as const
const HHMM = /^([01]\d|2[0-3]):[0-5]\d$/

export async function PATCH(req: Request) {
  const supabase = await createServerSupabaseClient()
  const { data: { user } } = await supabase.auth.getUser()
  if (!user) return NextResponse.json({ error: 'unauthorized' }, { status: 401 })

  const { data: profile } = await supabase.from('users').select('role').eq('auth_id', user.id).single()
  if (!profile || !['owner', 'manager'].includes(profile.role))
    return NextResponse.json({ error: 'forbidden' }, { status: 403 })

  const body = (await req.json()) as Partial<{
    digest_time: string
    digest_recipient_email: string
    notes_threshold_dollars: number
    pay_grace_hours: number
    enabled_button_checks: string[]
  }>

  if (body.digest_time !== undefined && !HHMM.test(body.digest_time))
    return NextResponse.json({ error: 'digest_time must be HH:MM' }, { status: 400 })
  if (body.notes_threshold_dollars !== undefined && (body.notes_threshold_dollars < 0 || body.notes_threshold_dollars > 100000))
    return NextResponse.json({ error: 'notes_threshold_dollars out of range' }, { status: 400 })
  if (body.pay_grace_hours !== undefined && (body.pay_grace_hours < 0 || body.pay_grace_hours > 720))
    return NextResponse.json({ error: 'pay_grace_hours out of range' }, { status: 400 })
  if (body.enabled_button_checks !== undefined &&
      !body.enabled_button_checks.every(b => (ALL_BUTTONS as readonly string[]).includes(b)))
    return NextResponse.json({ error: 'invalid button name' }, { status: 400 })

  const updates: Record<string, unknown> = { ...body, updated_at: new Date().toISOString() }

  if (body.digest_time) {
    const [hh, mm] = body.digest_time.split(':')
    // CDT offset (5h). DST-aware computation deferred to v2.
    const utcHour = (parseInt(hh, 10) + 5) % 24
    const cronExpr = `${parseInt(mm, 10)} ${utcHour} * * *`
    updates.digest_cron_expression = cronExpr

    const { error: rpcErr } = await supabase.rpc('reschedule_digest', { new_cron_expression: cronExpr })
    if (rpcErr) return NextResponse.json({ error: `cron reschedule failed: ${rpcErr.message}` }, { status: 500 })
  }

  const { error } = await supabase.from('app_settings').update(updates).eq('id', 1)
  if (error) return NextResponse.json({ error: error.message }, { status: 500 })
  return NextResponse.json({ ok: true })
}

import { NextResponse } from 'next/server'
import { createServerSupabaseClient } from '@/lib/supabase/server'

export async function POST() {
  const supabase = await createServerSupabaseClient()
  const { data: { user } } = await supabase.auth.getUser()
  if (!user) return NextResponse.json({ error: 'unauthorized' }, { status: 401 })

  const { data: profile } = await supabase.from('users').select('role').eq('auth_id', user.id).single()
  if (!profile || !['owner', 'manager'].includes(profile.role))
    return NextResponse.json({ error: 'forbidden' }, { status: 403 })

  const url = `${process.env.NEXT_PUBLIC_SUPABASE_URL}/functions/v1/daily-supervisor-digest`
  const res = await fetch(url, {
    method: 'POST',
    headers: { Authorization: `Bearer ${process.env.SUPABASE_SERVICE_ROLE_KEY ?? ''}` },
  })
  if (!res.ok) return NextResponse.json({ error: `digest function returned ${res.status}` }, { status: 502 })
  const json = await res.json()
  return NextResponse.json({ ok: true, ...json })
}

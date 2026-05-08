import { NextResponse } from 'next/server'
import { createServerSupabaseClient } from '@/lib/supabase/server'

export async function POST(_req: Request, { params }: { params: Promise<{ id: string }> }) {
  const { id } = await params
  const supabase = await createServerSupabaseClient()
  const { data: { user } } = await supabase.auth.getUser()
  if (!user) return NextResponse.json({ error: 'unauthorized' }, { status: 401 })

  const { data: profile } = await supabase.from('users').select('id, role').eq('auth_id', user.id).single()
  if (!profile || !['owner', 'manager'].includes(profile.role))
    return NextResponse.json({ error: 'forbidden' }, { status: 403 })

  const { error } = await supabase
    .from('supervisor_alerts')
    .update({ resolved_at: new Date().toISOString(), resolved_by: profile.id })
    .eq('id', id)
  if (error) return NextResponse.json({ error: error.message }, { status: 500 })
  return NextResponse.json({ ok: true })
}

import { NextResponse } from 'next/server'
import { createServerSupabaseClient } from '@/lib/supabase/server'

export async function GET() {
  const supabase = await createServerSupabaseClient()
  const { count, error } = await supabase
    .from('supervisor_alerts')
    .select('id', { count: 'exact', head: true })
    .is('resolved_at', null)

  if (error) return NextResponse.json({ count: 0, error: error.message }, { status: 500 })
  return NextResponse.json({ count: count ?? 0 })
}

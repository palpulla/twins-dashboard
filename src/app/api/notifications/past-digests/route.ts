import { NextResponse } from 'next/server'
import { createServerSupabaseClient } from '@/lib/supabase/server'

export async function GET() {
  const supabase = await createServerSupabaseClient()
  const { data, error } = await supabase.rpc('past_digests_summary', { days: 14 })
  if (error) return NextResponse.json({ digests: [], error: error.message }, { status: 500 })
  return NextResponse.json({ digests: data ?? [] })
}

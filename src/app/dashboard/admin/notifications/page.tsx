import { redirect } from 'next/navigation'
import { createServerSupabaseClient } from '@/lib/supabase/server'
import { AlertsTable } from '@/components/notifications/alerts-table'
import { PastDigestsList } from '@/components/notifications/past-digests-list'
import { SettingsPanel } from '@/components/notifications/settings-panel'

export default async function NotificationsPage() {
  const supabase = await createServerSupabaseClient()
  const { data: { user } } = await supabase.auth.getUser()
  if (!user) redirect('/dashboard')

  const { data: profile } = await supabase
    .from('users').select('role').eq('auth_id', user.id).single()
  if (!profile || !['owner', 'manager'].includes(profile.role)) redirect('/dashboard')

  const { data: settings } = await supabase
    .from('app_settings').select('*').eq('id', 1).single()

  const { data: openAlerts } = await supabase
    .from('supervisor_alerts')
    .select(`
      id, alert_type, details, created_at, digest_date,
      attributed_tech:attributed_tech_id ( id, full_name ),
      jobs:job_id ( id, hcp_id, job_type, revenue, completed_at,
        customers:customer_id (name),
        job_technicians ( users:technician_id (id, full_name) ) )
    `)
    .is('resolved_at', null)
    .order('digest_date', { ascending: false })

  return (
    <div className="space-y-8 p-6">
      <header>
        <h1 className="text-2xl font-bold text-primary">Notifications</h1>
        <p className="text-sm text-on-surface-variant">Tickets flagged by the daily supervisor digest.</p>
      </header>

      <section>
        <div className="flex items-baseline justify-between mb-3">
          <h2 className="text-base font-semibold">Today&apos;s open issues</h2>
          <span className="text-xs px-2 py-1 rounded-full bg-red-100 text-red-800 font-semibold">
            {openAlerts?.length ?? 0} unresolved
          </span>
        </div>
        {/* eslint-disable-next-line @typescript-eslint/no-explicit-any */}
        <AlertsTable rows={(openAlerts ?? []) as any} />
      </section>

      <section>
        <div className="flex items-baseline justify-between mb-3">
          <h2 className="text-base font-semibold">Past digests</h2>
          <span className="text-xs text-gray-500">Last 14 days</span>
        </div>
        <PastDigestsList />
      </section>

      <section>
        <h2 className="text-base font-semibold mb-3">Settings</h2>
        {settings && <SettingsPanel initial={settings} />}
      </section>
    </div>
  )
}

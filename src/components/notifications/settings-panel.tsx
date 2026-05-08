'use client'
import { useState } from 'react'
import type { Database } from '@/types/database'

type Settings = Database['public']['Tables']['app_settings']['Row']
const ALL_BUTTONS = ['SCHEDULE','OMW','START','FINISH','INVOICE','PAY'] as const

export function SettingsPanel({ initial }: { initial: Settings }) {
  const [s, setS] = useState(initial)
  const [busy, setBusy] = useState(false)
  const [msg, setMsg] = useState<string | null>(null)

  function update<K extends keyof Settings>(k: K, v: Settings[K]) {
    setS({ ...s, [k]: v })
  }

  async function save() {
    setBusy(true); setMsg(null)
    const res = await fetch('/api/notifications/settings', {
      method: 'PATCH',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        digest_time: s.digest_time?.slice(0, 5),
        digest_recipient_email: s.digest_recipient_email,
        notes_threshold_dollars: s.notes_threshold_dollars,
        pay_grace_hours: s.pay_grace_hours,
        enabled_button_checks: s.enabled_button_checks,
      }),
    })
    setBusy(false)
    setMsg(res.ok ? 'Saved.' : `Error: ${(await res.json()).error}`)
  }

  async function sendTest() {
    setBusy(true); setMsg(null)
    const res = await fetch('/api/notifications/test-digest', { method: 'POST' })
    setBusy(false)
    setMsg(res.ok ? 'Test digest sent.' : `Error: ${(await res.json()).error}`)
  }

  function toggleButton(btn: string) {
    const has = s.enabled_button_checks?.includes(btn) ?? false
    const next = has
      ? s.enabled_button_checks!.filter(b => b !== btn)
      : [...(s.enabled_button_checks ?? []), btn]
    update('enabled_button_checks', next)
  }

  const Row = ({ label, children }: { label: string; children: React.ReactNode }) => (
    <div className="grid grid-cols-[220px_1fr] items-center py-2.5 border-b border-gray-100 last:border-0 text-sm">
      <label className="text-gray-700 font-medium">{label}</label>
      <div>{children}</div>
    </div>
  )

  const inputCls = 'border border-gray-300 rounded px-2.5 py-1.5 text-sm bg-gray-50'

  return (
    <div className="bg-white border border-gray-200 rounded-lg p-5">
      <Row label="Digest send time">
        <input type="time" value={s.digest_time?.slice(0, 5) ?? ''} onChange={e => update('digest_time', e.target.value)} className={inputCls} /> CDT
      </Row>
      <Row label="Recipient email">
        <input type="email" value={s.digest_recipient_email ?? ''} onChange={e => update('digest_recipient_email', e.target.value)} className={`${inputCls} w-72`} />
      </Row>
      <Row label="Sub-labor notes threshold">
        $ <input type="number" value={s.notes_threshold_dollars} onChange={e => update('notes_threshold_dollars', Number(e.target.value))} className={`${inputCls} w-20`} />
      </Row>
      <Row label="PAY grace period">
        <input type="number" value={s.pay_grace_hours} onChange={e => update('pay_grace_hours', Number(e.target.value))} className={`${inputCls} w-20`} /> hours after FINISH
      </Row>
      <Row label="Buttons checked">
        <div className="flex flex-wrap gap-2">
          {ALL_BUTTONS.map(b => {
            const on = s.enabled_button_checks?.includes(b) ?? false
            return (
              <button key={b} onClick={() => toggleButton(b)}
                className={`text-[11px] font-semibold px-2.5 py-1 rounded-full uppercase tracking-wide ${on ? 'bg-blue-100 text-blue-900' : 'bg-gray-100 text-gray-400 line-through'}`}>
                {b}
              </button>
            )
          })}
        </div>
      </Row>
      <div className="flex gap-2 pt-4">
        <button onClick={save} disabled={busy} className="bg-blue-900 text-white text-sm font-semibold px-4 py-2 rounded disabled:opacity-50">
          {busy ? 'Saving…' : 'Save settings'}
        </button>
        <button onClick={sendTest} disabled={busy} className="border border-gray-300 text-sm font-semibold px-4 py-2 rounded disabled:opacity-50">
          Send test digest now
        </button>
        {msg && <span className="self-center text-sm text-gray-600">{msg}</span>}
      </div>
    </div>
  )
}

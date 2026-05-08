// supabase/functions/daily-supervisor-digest/render-email.ts
// Pure HTML rendering. No Supabase imports — testable from Node and Deno.

interface RenderInput {
  digest_date: string  // YYYY-MM-DD
  total_revenue_today: number
  tickets: RenderTicket[]
}

interface RenderTicket {
  job_id: string
  hcp_id: string | null
  job_summary: string         // e.g. "Spring repl."
  customer_label: string      // "S. Jenkins"
  total_amount: number
  finished_at: string         // ISO
  primary_tech_name: string
  co_tech_name: string | null  // e.g. "Charles" or null
  alerts: { type: 'missing_buttons' | 'missing_notes'; details: Record<string, unknown> }[]
}

const PILL = (label: string, color: 'red' | 'amber' | 'orange') => {
  const c = { red: '#fee2e2;color:#991b1b', amber: '#fef3c7;color:#92400e', orange: '#fed7aa;color:#9a3412' }[color]
  return `<span style="display:inline-block;background:${c};font-size:11px;font-weight:600;padding:2px 8px;border-radius:10px;text-transform:uppercase;letter-spacing:.3px;margin-right:4px;">${label}</span>`
}

export function renderDigestSubject(tickets: RenderTicket[]): string {
  const issueCount = tickets.reduce((n, t) => n + t.alerts.length, 0)
  return `Daily ticket review — ${issueCount} issues across ${tickets.length} tickets`
}

export function renderDigestHtml(input: RenderInput): string {
  const issueCount = input.tickets.reduce((n, t) => n + t.alerts.length, 0)

  const ticketBlocks = input.tickets.map(t => {
    const techLine = t.co_tech_name
      ? `${t.primary_tech_name} (${t.co_tech_name} co-tech, attributed to ${t.primary_tech_name})`
      : t.primary_tech_name

    const pills = t.alerts.flatMap(a => {
      if (a.type === 'missing_notes') return [PILL('Missing Notes', 'amber')]
      const missing = (a.details as { missing: string[] }).missing
      const overdueHours = (a.details as { pay_overdue_hours?: number }).pay_overdue_hours
      return missing.map(m => {
        if (m === 'PAY' && overdueHours)
          return PILL(`Pay overdue (${Math.floor(overdueHours / 24)}d)`, 'orange')
        return PILL(`Missing ${m}`, 'red')
      })
    }).join('')

    const hcpLink = t.hcp_id
      ? `<a href="https://pro.housecallpro.com/app/jobs/${t.hcp_id}" style="display:inline-block;background:#1e3a8a;color:#fff;text-decoration:none;padding:8px 14px;border-radius:6px;font-size:12px;font-weight:600;margin-top:8px;">Open in HCP →</a>`
      : ''

    return `
      <div style="border:1px solid #e5e7eb;border-radius:6px;padding:14px 16px;margin-bottom:12px;">
        <div style="display:flex;justify-content:space-between;align-items:baseline;">
          <strong style="color:#111827;">Job #${t.hcp_id ?? t.job_id.slice(0,8)} · ${escape(t.job_summary)}</strong>
          <span style="color:${t.total_amount === 0 ? '#6b7280' : '#059669'};font-weight:600;">$${t.total_amount.toLocaleString()}</span>
        </div>
        <div style="color:#6b7280;font-size:12px;margin:4px 0 8px;">
          Customer: ${escape(t.customer_label)} · Tech: ${escape(techLine)} · Finished ${formatDate(t.finished_at)}
        </div>
        <div>${pills}</div>
        ${hcpLink}
      </div>`
  }).join('')

  return `<!doctype html><html><body style="font-family:-apple-system,BlinkMacSystemFont,sans-serif;color:#1f2937;background:#f3f4f6;padding:24px;">
    <div style="background:#fff;border-radius:8px;max-width:720px;margin:0 auto;padding:24px;">
      <h1 style="font-size:18px;margin:0 0 4px;">Daily digest · ${formatDate(input.digest_date)}</h1>
      <div style="display:flex;gap:24px;margin:16px 0 24px;padding:14px 16px;background:#fef3c7;border-left:4px solid #f59e0b;border-radius:4px;font-size:13px;">
        <div><strong>${input.tickets.length}</strong> tickets need review</div>
        <div><strong>${issueCount}</strong> issues flagged</div>
        <div><strong>$${input.total_revenue_today.toLocaleString()}</strong> revenue today</div>
      </div>
      ${ticketBlocks}
      <div style="font-size:11px;color:#9ca3af;text-align:center;padding-top:20px;border-top:1px solid #f3f4f6;margin-top:24px;">
        Adjust digest time, threshold, or recipient → twinsdash.com/dashboard/admin/notifications
      </div>
    </div>
  </body></html>`
}

function escape(s: string): string {
  return s.replace(/[&<>"']/g, c => (
    { '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' } as Record<string,string>
  )[c])
}

function formatDate(iso: string): string {
  const d = new Date(iso)
  return d.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' })
}

export type { RenderInput, RenderTicket }

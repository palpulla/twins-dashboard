// supabase/functions/daily-supervisor-digest/send-email.ts

interface SendArgs {
  to: string
  subject: string
  html: string
  apiKey: string
  from?: string
}

export async function sendDigestEmail(args: SendArgs): Promise<{ id: string }> {
  const res = await fetch('https://api.resend.com/emails', {
    method: 'POST',
    headers: {
      Authorization: `Bearer ${args.apiKey}`,
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({
      from: args.from ?? 'Twins Dashboard <noreply@twinsdash.com>',
      to: args.to,
      subject: args.subject,
      html: args.html,
    }),
  })

  if (!res.ok) {
    const body = await res.text()
    throw new Error(`Resend error ${res.status}: ${body}`)
  }
  return res.json() as Promise<{ id: string }>
}

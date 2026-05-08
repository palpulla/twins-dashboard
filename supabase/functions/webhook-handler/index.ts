// Supabase Edge Function: HousecallPro Webhook Handler
// Receives webhook events, stores raw payloads, and processes into normalized tables

import { createClient } from 'https://esm.sh/@supabase/supabase-js@2';

const SUPABASE_URL = Deno.env.get('SUPABASE_URL')!;
const SUPABASE_SERVICE_KEY = Deno.env.get('SUPABASE_SERVICE_ROLE_KEY')!;
const HCP_WEBHOOK_SECRET = Deno.env.get('HCP_WEBHOOK_SECRET');

const supabase = createClient(SUPABASE_URL, SUPABASE_SERVICE_KEY);

interface WebhookPayload {
  event: string;
  event_id?: string;
  data: Record<string, unknown>;
  timestamp?: string;
}

// HCP payload shape for these fields was not verified against a real webhook.
// If field names differ in production, adjust here. See:
// docs/superpowers/plans/2026-05-08-supervisor-ticket-discipline-alerts.md
interface HcpJobData {
  id?: string;
  notes?: string | null;
  assigned_employees?: { id: string; assigned_at?: string }[];
}

interface HcpInvoiceData {
  id?: string;
  job_id?: string;
  created_at?: string;
}

// Commission calculation (mirrors src/lib/utils/commission.ts)
function calculateManagerBonus(netRevenue: number): number {
  if (netRevenue < 400) return 0;
  let bonus = 20;
  let threshold = 500;
  while (netRevenue >= threshold) {
    bonus += 10;
    threshold += 100;
  }
  return bonus;
}

Deno.serve(async (req: Request) => {
  if (req.method !== 'POST') {
    return new Response('Method not allowed', { status: 405 });
  }

  try {
    // Verify webhook authenticity
    if (HCP_WEBHOOK_SECRET) {
      const signature = req.headers.get('x-hcp-signature') || req.headers.get('x-webhook-signature');
      // TODO: Implement HMAC verification when HCP provides signature format
    }

    const payload: WebhookPayload = await req.json();

    // Store raw event for debugging and replay
    const { error: rawError } = await supabase.from('raw_events').insert({
      event_type: payload.event,
      source: 'housecallpro',
      payload: payload as unknown as Record<string, unknown>,
      status: 'pending',
    });

    if (rawError) {
      console.error('Failed to store raw event:', rawError);
    }

    // Route to handler based on event type
    const [category, action] = payload.event.split('.');

    switch (category) {
      case 'customer':
        await handleCustomerEvent(action, payload.data);
        break;
      case 'job':
        await handleJobEvent(payload.event, payload.data, payload.timestamp);
        break;
      case 'invoice':
        await handleInvoiceEvent(payload.event, action, payload.data, payload.timestamp);
        break;
      case 'estimate':
        await handleEstimateEvent(action, payload.data);
        break;
      case 'lead':
        await handleLeadEvent(action, payload.data);
        break;
    }

    // Mark raw event as processed
    if (payload.event_id) {
      await supabase.from('raw_events')
        .update({ status: 'processed', processed_at: new Date().toISOString() })
        .eq('payload->>event_id', payload.event_id);
    }

    return new Response(JSON.stringify({ success: true }), {
      status: 200,
      headers: { 'Content-Type': 'application/json' },
    });
  } catch (error) {
    console.error('Webhook processing error:', error);
    return new Response(JSON.stringify({ error: 'Internal server error' }), {
      status: 200, // Return 200 to prevent webhook retries
      headers: { 'Content-Type': 'application/json' },
    });
  }
});

async function handleCustomerEvent(action: string, data: Record<string, unknown>) {
  const hcpId = data.id as string;
  if (!hcpId) return;

  if (action === 'deleted') {
    // Soft delete or handle deletion
    return;
  }

  // Upsert customer (idempotent via hcp_id unique constraint)
  await supabase.from('customers').upsert({
    hcp_id: hcpId,
    name: (data.name as string) || (data.first_name as string) + ' ' + (data.last_name as string),
    email: data.email as string | null,
    phone: data.phone as string | null,
    address: data.address as string | null,
  }, { onConflict: 'hcp_id' });
}

async function handleJobEvent(event: string, data: Record<string, unknown>, eventTimestamp?: string) {
  const hcpId = data.id as string;
  if (!hcpId) return;

  // HCP payload shape for these fields was not verified against a real webhook.
  // If field names differ in production, adjust here. See:
  // docs/superpowers/plans/2026-05-08-supervisor-ticket-discipline-alerts.md
  const jobPayload = data as HcpJobData & Record<string, unknown>;

  const jobData: Record<string, unknown> = {
    hcp_id: hcpId,
    job_type: data.job_type as string || 'Service Call',
    status: mapJobStatus(event, data),
    revenue: Number(data.total || data.amount || 0),
    parts_cost: Number(data.parts_cost || 0),
  };

  // work_notes: always reflect latest if present in payload
  if (jobPayload.notes !== undefined) {
    jobData.work_notes = jobPayload.notes ?? null;
  }

  // started_at: only populate on job.started so we don't clobber a previously set value
  if (event === 'job.started') {
    jobData.started_at = eventTimestamp ?? new Date().toISOString();
  }

  if (data.customer_id) {
    const { data: customer } = await supabase.from('customers')
      .select('id').eq('hcp_id', data.customer_id as string).single();
    if (customer) jobData.customer_id = customer.id;
  }

  if (data.scheduled_start) jobData.scheduled_at = data.scheduled_start;
  if (event === 'job.completed' && data.completed_at) jobData.completed_at = data.completed_at;

  // Upsert job
  const { data: job } = await supabase.from('jobs')
    .upsert(jobData, { onConflict: 'hcp_id' })
    .select()
    .single();

  // Populate job_technicians junction from assigned_employees, if provided.
  // Requires users.hcp_id to map HCP employee ids to internal user ids.
  // If users.hcp_id does not exist (current schema), gracefully skip and warn.
  const assignedEmployees = jobPayload.assigned_employees ?? [];
  if (job && assignedEmployees.length > 0) {
    try {
      // NOTE: users.hcp_id does not exist in the current schema. The query below
      // will fail at runtime with a Postgres error; we catch and log so processing
      // continues. When users.hcp_id is added, the warning will go away.
      const { data: techs, error: techsError } = await supabase
        .from('users')
        .select('id, hcp_id')
        .in('hcp_id', assignedEmployees.map((e: { id: string }) => e.id));

      if (techsError) {
        console.warn('Skipping job_technicians populate (users.hcp_id lookup failed):', techsError.message);
      } else if (techs && techs.length > 0) {
        const techRows = techs
          .map((t: { id: string; hcp_id?: string }) => {
            const matched = assignedEmployees.find((e) => e.id === t.hcp_id);
            return matched
              ? {
                  job_id: (job as { id: string }).id,
                  technician_id: t.id,
                  assigned_at: matched.assigned_at ?? new Date().toISOString(),
                }
              : null;
          })
          .filter((r): r is { job_id: string; technician_id: string; assigned_at: string } => r !== null);

        if (techRows.length > 0) {
          // Replace all existing assignments for this job
          await supabase.from('job_technicians').delete().eq('job_id', (job as { id: string }).id);
          await supabase.from('job_technicians').insert(techRows);
        }
      }
    } catch (e) {
      console.warn(`Skipping job_technicians populate for job ${(job as { id: string }).id}:`, e);
    }
  }

  // Recalculate commission if job completed
  if (event === 'job.completed' && job) {
    await recalculateCommission(job);
  }
}

async function handleInvoiceEvent(
  event: string,
  action: string,
  data: Record<string, unknown>,
  eventTimestamp?: string,
) {
  const hcpId = data.id as string;
  if (!hcpId) return;

  // HCP payload shape for these fields was not verified against a real webhook.
  // If field names differ in production, adjust here.
  const invoicePayload = data as HcpInvoiceData & Record<string, unknown>;

  const invoiceData: Record<string, unknown> = {
    hcp_id: hcpId,
    amount: Number(data.total || data.amount || 0),
    status: action === 'paid' ? 'paid' : action === 'canceled' ? 'canceled' : action === 'voided' ? 'voided' : 'created',
  };

  if (action === 'paid' || action === 'payment.succeeded') {
    invoiceData.paid_at = new Date().toISOString();
  }

  if (data.job_id) {
    const { data: job } = await supabase.from('jobs')
      .select('id').eq('hcp_id', data.job_id as string).single();
    if (job) invoiceData.job_id = job.id;
  }

  await supabase.from('invoices').upsert(invoiceData, { onConflict: 'hcp_id' });

  // Stamp jobs.invoiced_at when an invoice is first created. Don't overwrite if already set.
  if (event === 'invoice.created' && invoicePayload.job_id) {
    const invoicedAt = invoicePayload.created_at ?? eventTimestamp ?? new Date().toISOString();
    await supabase
      .from('jobs')
      .update({ invoiced_at: invoicedAt })
      .eq('hcp_id', invoicePayload.job_id)
      .is('invoiced_at', null);
  }
}

async function handleEstimateEvent(action: string, data: Record<string, unknown>) {
  const hcpId = data.id as string;
  if (!hcpId) return;

  await supabase.from('estimates').upsert({
    hcp_id: hcpId,
    status: action,
    amount: Number(data.total || data.amount || 0),
  }, { onConflict: 'hcp_id' });
}

async function handleLeadEvent(action: string, data: Record<string, unknown>) {
  await supabase.from('leads').insert({
    source: (data.source as string) || 'unknown',
    channel: (data.channel as string) || 'unknown',
    status: action === 'converted' ? 'converted' : action === 'lost' ? 'lost' : 'new',
    customer_name: data.customer_name as string | null,
    customer_phone: data.phone as string | null,
    customer_email: data.email as string | null,
  });
}

function mapJobStatus(event: string, _data: Record<string, unknown>): string {
  if (event.includes('completed')) return 'completed';
  if (event.includes('canceled')) return 'canceled';
  if (event.includes('started')) return 'in_progress';
  if (event.includes('scheduled')) return 'scheduled';
  if (event.includes('on_my_way')) return 'on_my_way';
  return 'created';
}

async function recalculateCommission(job: Record<string, unknown>) {
  if (!job.technician_id || Number(job.revenue) === 0) return;

  const techId = job.technician_id as string;

  // Get technician's current commission tier
  const { data: tier } = await supabase.from('commission_tiers')
    .select('rate')
    .eq('user_id', techId)
    .order('effective_date', { ascending: false })
    .limit(1)
    .single();

  const tierRate = tier?.rate || 0.16;
  const partsCost = Number(job.parts_cost_override ?? job.parts_cost ?? 0);
  const netRevenue = Math.max(0, Number(job.revenue) - partsCost);
  const commissionAmount = Math.round(netRevenue * tierRate * 100) / 100;
  const managerOverride = Math.round(netRevenue * 0.02 * 100) / 100;
  const managerBonus = calculateManagerBonus(netRevenue);

  // Get manager
  const { data: tech } = await supabase.from('users')
    .select('manager_id').eq('id', techId).single();

  // Upsert commission record (idempotent per job)
  await supabase.from('commission_records').upsert({
    job_id: job.id as string,
    technician_id: techId,
    gross_revenue: Number(job.revenue),
    parts_cost: partsCost,
    net_revenue: netRevenue,
    tier_rate: tierRate,
    commission_amount: commissionAmount,
    manager_id: tech?.manager_id || null,
    manager_override: managerOverride,
    manager_bonus: managerBonus,
  }, { onConflict: 'job_id' });
}

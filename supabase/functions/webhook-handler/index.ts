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
    // Multi-segment events like 'estimate.option.created' must keep the
    // full action string ('option.created') so namespace-aware handlers
    // can dispatch correctly.
    const parts = payload.event.split('.');
    const category = parts[0];
    const action = parts.slice(1).join('.');

    switch (category) {
      case 'customer':
        await handleCustomerEvent(action, payload.data);
        break;
      case 'job':
        await handleJobEvent(payload.event, payload.data);
        break;
      case 'invoice':
        await handleInvoiceEvent(action, payload.data);
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

async function handleJobEvent(event: string, data: Record<string, unknown>) {
  const hcpId = data.id as string;
  if (!hcpId) return;

  const jobData: Record<string, unknown> = {
    hcp_id: hcpId,
    job_type: data.job_type as string || 'Service Call',
    status: mapJobStatus(event, data),
    revenue: Number(data.total || data.amount || 0),
    parts_cost: Number(data.parts_cost || 0),
  };

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

  // Recalculate commission if job completed
  if (event === 'job.completed' && job) {
    await recalculateCommission(job);
  }
}

async function handleInvoiceEvent(action: string, data: Record<string, unknown>) {
  const hcpId = data.id as string;
  if (!hcpId) return;

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
}

async function handleEstimateEvent(action: string, data: Record<string, unknown>) {
  if (action.startsWith('option.')) {
    await handleEstimateOptionEvent(action.slice('option.'.length), data);
    return;
  }

  const hcpId = data.id as string;
  if (!hcpId) return;

  const estimateData: Record<string, unknown> = {
    hcp_id: hcpId,
    status: action,
    amount: Number(data.total || data.amount || 0),
  };

  // Resolve parent job FK if HCP attached this estimate to a job.
  if (data.job_id) {
    const { data: job } = await supabase.from('jobs')
      .select('id').eq('hcp_id', data.job_id as string).single();
    if (job) estimateData.job_id = job.id;
  }

  await supabase.from('estimates').upsert(estimateData, { onConflict: 'hcp_id' });
}

async function handleEstimateOptionEvent(action: string, data: Record<string, unknown>) {
  const hcpId = data.id as string;
  if (!hcpId) return;

  const nestedEstimate = data.estimate as Record<string, unknown> | undefined;
  const estimateHcpId = (data.estimate_id as string) || (nestedEstimate?.id as string);
  if (!estimateHcpId) {
    console.error('estimate option event missing estimate_id', { hcpId, action });
    return;
  }

  // Resolve parent estimate FK if it already exists in our DB.
  let estimateId: string | null = null;
  const { data: estimate } = await supabase.from('estimates')
    .select('id').eq('hcp_id', estimateHcpId).single();
  if (estimate) estimateId = estimate.id;

  if (action === 'approval_status_changed') {
    const status = (data.approval_status as string) || 'created';
    await supabase.from('estimate_options').upsert({
      hcp_id: hcpId,
      estimate_hcp_id: estimateHcpId,
      estimate_id: estimateId,
      name: (data.name as string) ?? null,
      amount: Number(data.total || data.amount || 0),
      status,
    }, { onConflict: 'hcp_id' });
    return;
  }

  // Default: option.created (and any future option.* event we want to capture by default)
  await supabase.from('estimate_options').upsert({
    hcp_id: hcpId,
    estimate_hcp_id: estimateHcpId,
    estimate_id: estimateId,
    name: (data.name as string) ?? null,
    amount: Number(data.total || data.amount || 0),
    status: 'created',
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

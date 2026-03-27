/**
 * HousecallPro Historical Data Import Script
 * Uses curl for HTTP (Node fetch blocked in this env) + Supabase JS client
 *
 * Usage: node scripts/import-hcp-data.mjs
 */

import { createClient } from '@supabase/supabase-js';
import { execSync } from 'child_process';
import { readFileSync } from 'fs';
import { resolve, dirname } from 'path';
import { fileURLToPath } from 'url';

// Load .env.local
const __dirname = dirname(fileURLToPath(import.meta.url));
const envPath = resolve(__dirname, '..', '.env.local');
try {
  const envFile = readFileSync(envPath, 'utf-8');
  for (const line of envFile.split('\n')) {
    const trimmed = line.trim();
    if (!trimmed || trimmed.startsWith('#')) continue;
    const eqIdx = trimmed.indexOf('=');
    if (eqIdx === -1) continue;
    const key = trimmed.slice(0, eqIdx);
    const val = trimmed.slice(eqIdx + 1);
    if (!process.env[key]) process.env[key] = val;
  }
} catch { /* ignore */ }

const HCP_API_KEY = process.env.HCP_API_KEY;
const SUPABASE_URL = process.env.NEXT_PUBLIC_SUPABASE_URL;
const SUPABASE_SERVICE_KEY = process.env.SUPABASE_SERVICE_ROLE_KEY;

if (!HCP_API_KEY || !SUPABASE_URL || !SUPABASE_SERVICE_KEY) {
  console.error('Missing env vars');
  process.exit(1);
}

const supabase = createClient(SUPABASE_URL, SUPABASE_SERVICE_KEY);
const HCP_BASE = 'https://api.housecallpro.com';
const PAGE_SIZE = 200;

function sleep(ms) { return new Promise(r => setTimeout(r, ms)); }

function hcpFetch(url) {
  const result = execSync(
    `curl -s "${url}" -H "Authorization: Token ${HCP_API_KEY}"`,
    { maxBuffer: 50 * 1024 * 1024 }
  ).toString();
  return JSON.parse(result);
}

function fetchAll(endpoint, dataKey) {
  const all = [];
  let page = 1;
  let totalPages = 1;

  console.log(`Fetching ${dataKey}...`);
  while (page <= totalPages) {
    const url = `${HCP_BASE}/${endpoint}?page=${page}&page_size=${PAGE_SIZE}`;
    const data = hcpFetch(url);
    totalPages = data.total_pages || 1;
    const items = data[dataKey] || [];
    all.push(...items);
    console.log(`  Page ${page}/${totalPages}: ${items.length} (total: ${all.length})`);
    page++;
  }
  console.log(`  Done: ${all.length} ${dataKey}\n`);
  return all;
}

function centsToDollars(cents) {
  return typeof cents === 'number' ? cents / 100 : 0;
}

// --- Import ---

async function importEmployees() {
  const employees = fetchAll('employees', 'employees');
  for (const emp of employees) {
    const role = (emp.role || '').toLowerCase();
    let mappedRole = 'technician';
    if (role === 'admin' || emp.permissions?.is_admin) mappedRole = 'owner';
    else if (role === 'office staff') mappedRole = 'csr';

    const { error } = await supabase.from('users').upsert({
      id: emp.id,
      email: emp.email || `${emp.id}@hcp.local`,
      full_name: [emp.first_name, emp.last_name].filter(Boolean).join(' '),
      role: mappedRole,
      avatar_url: emp.avatar_url || null,
      is_active: true,
    }, { onConflict: 'id', ignoreDuplicates: false });
    if (error) console.error(`  Emp error ${emp.id}:`, error.message);
  }
  console.log(`Imported ${employees.length} employees\n`);
  return employees;
}

async function importCustomers() {
  const customers = fetchAll('customers', 'customers');
  const chunks = [];
  for (let i = 0; i < customers.length; i += 100) chunks.push(customers.slice(i, i + 100));

  let ok = 0;
  for (const chunk of chunks) {
    const rows = chunk.map(c => {
      const addr = c.addresses?.[0];
      const addrStr = addr ? [addr.street, addr.street_line_2, addr.city, addr.state, addr.zip].filter(Boolean).join(', ') : null;
      return {
        hcp_id: c.id,
        name: [c.first_name, c.last_name].filter(Boolean).join(' ') || c.company || 'Unknown',
        email: c.email || null,
        phone: c.mobile_number || c.home_number || c.work_number || null,
        address: addrStr,
      };
    });
    const { error } = await supabase.from('customers').upsert(rows, { onConflict: 'hcp_id' });
    if (error) console.error(`  Cust batch error:`, error.message);
    else ok += chunk.length;
  }
  console.log(`Imported ${ok} customers\n`);
}

async function importJobs(employees) {
  const jobs = fetchAll('jobs', 'jobs');
  const empIds = new Set(employees.map(e => e.id));

  const { data: dbCusts } = await supabase.from('customers').select('id, hcp_id');
  const custMap = new Map((dbCusts || []).map(c => [c.hcp_id, c.id]));

  const chunks = [];
  for (let i = 0; i < jobs.length; i += 100) chunks.push(jobs.slice(i, i + 100));

  let ok = 0;
  for (const chunk of chunks) {
    const rows = [];
    for (const job of chunk) {
      const techId = job.assigned_employees?.[0]?.id;
      const custId = job.customer?.id ? custMap.get(job.customer.id) || null : null;

      let status = 'created';
      const ws = (job.work_status || '').toLowerCase();
      if (ws.includes('complete') || ws === 'paid') status = 'completed';
      else if (ws.includes('cancel')) status = 'canceled';
      else if (['scheduled', 'dispatched', 'in progress', 'on my way', 'started'].includes(ws)) status = 'scheduled';

      let jobType = 'Service Call';
      const desc = (job.description || '').toLowerCase();
      const notes = (job.notes || []).map(n => (n.content || '')).join(' ').toLowerCase();
      const txt = desc + ' ' + notes;
      if (txt.includes('door') && txt.includes('opener') && (txt.includes('install') || txt.includes('new'))) jobType = 'Door + Opener Install';
      else if ((txt.includes('door install') || txt.includes('new door') || txt.includes('door replacement'))) jobType = 'Door Install';
      else if (txt.includes('opener install') || txt.includes('new opener') || txt.includes('liftmaster') || txt.includes('chamberlain')) jobType = 'Opener Install';
      else if (txt.includes('opener') && txt.includes('repair')) jobType = 'Opener + Repair';
      else if (txt.includes('spring') || txt.includes('repair') || txt.includes('broken') || txt.includes('cable') || txt.includes('off track') || txt.includes('panel')) jobType = 'Repair';
      else if (txt.includes('maintenance') || txt.includes('tune') || txt.includes('annual')) jobType = 'Maintenance Visit';
      else if (txt.includes('warranty') || txt.includes('callback') || txt.includes('redo')) jobType = 'Warranty Call';

      rows.push({
        hcp_id: job.id,
        customer_id: custId,
        technician_id: techId && empIds.has(techId) ? techId : null,
        job_type: jobType,
        status,
        scheduled_at: job.schedule?.scheduled_start || null,
        completed_at: job.work_timestamps?.completed_at || null,
        revenue: parseFloat(job.total_amount || 0) || 0,
        parts_cost: 0,
        parts_cost_override: null,
        protection_plan_sold: false,
        created_at: job.created_at || new Date().toISOString(),
      });
    }
    const { error } = await supabase.from('jobs').upsert(rows, { onConflict: 'hcp_id' });
    if (error) console.error(`  Jobs batch error:`, error.message);
    else ok += chunk.length;
  }
  console.log(`Imported ${ok} jobs\n`);
  return jobs;
}

async function importInvoices(hcpJobs) {
  const invoices = fetchAll('invoices', 'invoices');

  const { data: dbJobs } = await supabase.from('jobs').select('id, hcp_id');
  const jobMap = new Map((dbJobs || []).map(j => [j.hcp_id, j.id]));
  const { data: dbCusts } = await supabase.from('customers').select('id, hcp_id');
  const custMap = new Map((dbCusts || []).map(c => [c.hcp_id, c.id]));

  const chunks = [];
  for (let i = 0; i < invoices.length; i += 100) chunks.push(invoices.slice(i, i + 100));

  let ok = 0;
  const jobPartsCost = new Map();
  const jobRevenue = new Map();

  for (const chunk of chunks) {
    const rows = chunk.map(inv => {
      const st = (inv.status || '').toLowerCase();
      let status = 'created';
      if (st === 'paid') status = 'paid';
      else if (st === 'void') status = 'voided';

      // Parts cost = sum of material unit_cost * qty
      const matCost = (inv.items || [])
        .filter(item => item.type === 'material')
        .reduce((sum, item) => sum + (item.unit_cost || 0) * ((item.qty_in_hundredths || 100) / 100), 0);

      if (inv.job_id && st !== 'void') {
        jobPartsCost.set(inv.job_id, (jobPartsCost.get(inv.job_id) || 0) + centsToDollars(matCost));
        jobRevenue.set(inv.job_id, (jobRevenue.get(inv.job_id) || 0) + centsToDollars(inv.amount || 0));
      }

      return {
        hcp_id: inv.id,
        job_id: inv.job_id ? jobMap.get(inv.job_id) || null : null,
        customer_id: inv.customer_id ? custMap.get(inv.customer_id) || null : null,
        amount: centsToDollars(inv.amount || 0),
        status,
        paid_at: inv.paid_at || null,
        created_at: inv.created_at || inv.invoice_date || new Date().toISOString(),
      };
    });

    const { error } = await supabase.from('invoices').upsert(rows, { onConflict: 'hcp_id' });
    if (error) console.error(`  Inv batch error:`, error.message);
    else ok += chunk.length;
  }

  // Update jobs with parts cost from invoices
  console.log(`  Updating ${jobPartsCost.size} jobs with parts costs...`);
  let updated = 0;
  for (const [hcpJobId, partsCost] of jobPartsCost) {
    const dbJobId = jobMap.get(hcpJobId);
    if (!dbJobId) continue;
    const rev = jobRevenue.get(hcpJobId) || 0;
    const updateData = { parts_cost: partsCost };
    if (rev > 0) updateData.revenue = rev;

    const { error } = await supabase.from('jobs').update(updateData).eq('id', dbJobId);
    if (!error) updated++;
  }
  console.log(`  Updated ${updated} jobs`);
  console.log(`Imported ${ok} invoices\n`);
}

async function importEstimates() {
  const estimates = fetchAll('estimates', 'estimates');

  const { data: dbCusts } = await supabase.from('customers').select('id, hcp_id');
  const custMap = new Map((dbCusts || []).map(c => [c.hcp_id, c.id]));

  const chunks = [];
  for (let i = 0; i < estimates.length; i += 100) chunks.push(estimates.slice(i, i + 100));

  let ok = 0;
  for (const chunk of chunks) {
    const rows = chunk.map(est => ({
      hcp_id: est.id,
      customer_id: est.customer_id ? custMap.get(est.customer_id) || null : null,
      technician_id: null,
      status: (est.status || 'created').toLowerCase(),
      amount: centsToDollars(est.total || est.amount || 0),
      created_at: est.created_at || new Date().toISOString(),
    }));
    const { error } = await supabase.from('estimates').upsert(rows, { onConflict: 'hcp_id' });
    if (error) console.error(`  Est batch error:`, error.message);
    else ok += chunk.length;
  }
  console.log(`Imported ${ok} estimates\n`);
}

// --- Main ---

async function main() {
  console.log('=== HousecallPro Historical Data Import ===\n');

  const employees = await importEmployees();
  await importCustomers();
  const jobs = await importJobs(employees);
  await importInvoices(jobs);
  await importEstimates();

  console.log('=== Import Complete! ===');
}

main().catch(err => { console.error('Failed:', err); process.exit(1); });

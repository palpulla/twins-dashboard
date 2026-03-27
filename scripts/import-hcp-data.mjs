/**
 * HousecallPro Historical Data Import Script
 * Uses curl for ALL HTTP requests (Node fetch blocked in this env)
 *
 * Usage: node scripts/import-hcp-data.mjs
 */

import { execSync } from 'child_process';
import { readFileSync } from 'fs';
import { resolve, dirname } from 'path';
import { fileURLToPath } from 'url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const envPath = resolve(__dirname, '..', '.env.local');
try {
  const envFile = readFileSync(envPath, 'utf-8');
  for (const line of envFile.split('\n')) {
    const trimmed = line.trim();
    if (!trimmed || trimmed.startsWith('#')) continue;
    const eqIdx = trimmed.indexOf('=');
    if (eqIdx === -1) continue;
    if (!process.env[trimmed.slice(0, eqIdx)]) process.env[trimmed.slice(0, eqIdx)] = trimmed.slice(eqIdx + 1);
  }
} catch { /* ignore */ }

const HCP_API_KEY = process.env.HCP_API_KEY;
const SUPABASE_URL = process.env.NEXT_PUBLIC_SUPABASE_URL;
const SUPABASE_SERVICE_KEY = process.env.SUPABASE_SERVICE_ROLE_KEY;

if (!HCP_API_KEY || !SUPABASE_URL || !SUPABASE_SERVICE_KEY) {
  console.error('Missing env vars');
  process.exit(1);
}

const HCP_BASE = 'https://api.housecallpro.com';
const SB_REST = `${SUPABASE_URL}/rest/v1`;
const PAGE_SIZE = 200;

function curlGet(url, headers = {}) {
  const headerArgs = Object.entries(headers).map(([k, v]) => `-H "${k}: ${v}"`).join(' ');
  const result = execSync(`curl -s "${url}" ${headerArgs}`, { maxBuffer: 50 * 1024 * 1024 }).toString();
  return JSON.parse(result);
}

function sbPost(table, rows, onConflict) {
  const url = `${SB_REST}/${table}?on_conflict=${onConflict}`;
  const json = JSON.stringify(rows);
  // Write body to temp file to avoid shell escaping issues
  const tmpFile = `/tmp/sb_import_${Date.now()}.json`;
  execSync(`cat > ${tmpFile} << 'JSONEOF'\n${json}\nJSONEOF`);
  try {
    const result = execSync(
      `curl -s -o /dev/null -w "%{http_code}" -X POST "${url}" ` +
      `-H "apikey: ${SUPABASE_SERVICE_KEY}" ` +
      `-H "Authorization: Bearer ${SUPABASE_SERVICE_KEY}" ` +
      `-H "Content-Type: application/json" ` +
      `-H "Prefer: resolution=merge-duplicates" ` +
      `-d @${tmpFile}`,
      { maxBuffer: 50 * 1024 * 1024 }
    ).toString().trim();
    return { status: parseInt(result), error: parseInt(result) >= 400 ? result : null };
  } finally {
    try { execSync(`rm -f ${tmpFile}`); } catch {}
  }
}

function sbPatch(table, data, filterCol, filterVal) {
  const url = `${SB_REST}/${table}?${filterCol}=eq.${filterVal}`;
  const json = JSON.stringify(data);
  const result = execSync(
    `curl -s -o /dev/null -w "%{http_code}" -X PATCH "${url}" ` +
    `-H "apikey: ${SUPABASE_SERVICE_KEY}" ` +
    `-H "Authorization: Bearer ${SUPABASE_SERVICE_KEY}" ` +
    `-H "Content-Type: application/json" ` +
    `-d '${json.replace(/'/g, "'\\''")}'`,
    { maxBuffer: 10 * 1024 * 1024 }
  ).toString().trim();
  return { status: parseInt(result) };
}

function sbSelect(table, select = '*', filter = '') {
  const url = `${SB_REST}/${table}?select=${encodeURIComponent(select)}${filter ? '&' + filter : ''}&limit=100000`;
  return curlGet(url, {
    'apikey': SUPABASE_SERVICE_KEY,
    'Authorization': `Bearer ${SUPABASE_SERVICE_KEY}`,
  });
}

function hcpFetchAll(endpoint, dataKey) {
  const all = [];
  let page = 1;
  let totalPages = 1;
  console.log(`Fetching ${dataKey}...`);
  while (page <= totalPages) {
    const data = curlGet(`${HCP_BASE}/${endpoint}?page=${page}&page_size=${PAGE_SIZE}`, {
      'Authorization': `Token ${HCP_API_KEY}`,
    });
    totalPages = data.total_pages || 1;
    const items = data[dataKey] || [];
    all.push(...items);
    console.log(`  Page ${page}/${totalPages}: ${items.length} (total: ${all.length})`);
    page++;
  }
  console.log(`  Done: ${all.length} ${dataKey}`);
  return all;
}

function centsToDollars(c) { return typeof c === 'number' ? c / 100 : 0; }

// --- Import ---

function importEmployees() {
  const employees = hcpFetchAll('employees', 'employees');
  const rows = employees.map(emp => {
    const role = (emp.role || '').toLowerCase();
    let mapped = 'technician';
    if (role === 'admin' || emp.permissions?.is_admin) mapped = 'owner';
    else if (role === 'office staff') mapped = 'csr';
    return {
      id: emp.id,
      email: emp.email || `${emp.id}@hcp.local`,
      full_name: [emp.first_name, emp.last_name].filter(Boolean).join(' '),
      role: mapped,
      avatar_url: emp.avatar_url || null,
      is_active: true,
    };
  });
  const r = sbPost('users', rows, 'id');
  console.log(`  Supabase response: ${r.status}${r.error ? ' ERROR' : ' OK'}\n`);
  return employees;
}

function importCustomers() {
  const customers = hcpFetchAll('customers', 'customers');
  const chunks = [];
  for (let i = 0; i < customers.length; i += 500) chunks.push(customers.slice(i, i + 500));

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
    const r = sbPost('customers', rows, 'hcp_id');
    if (r.status < 400) ok += chunk.length;
    else console.error(`  Batch error: ${r.status}`);
  }
  console.log(`Imported ${ok} customers\n`);
}

function importJobs(employees) {
  const jobs = hcpFetchAll('jobs', 'jobs');
  const empIds = new Set(employees.map(e => e.id));

  const dbCusts = sbSelect('customers', 'id,hcp_id');
  const custMap = new Map(dbCusts.map(c => [c.hcp_id, c.id]));

  const chunks = [];
  for (let i = 0; i < jobs.length; i += 500) chunks.push(jobs.slice(i, i + 500));

  let ok = 0;
  for (const chunk of chunks) {
    const rows = chunk.map(job => {
      const techId = job.assigned_employees?.[0]?.id;
      const custId = job.customer?.id ? custMap.get(job.customer.id) || null : null;

      let status = 'created';
      const ws = (job.work_status || '').toLowerCase();
      if (ws.includes('complete') || ws === 'paid') status = 'completed';
      else if (ws.includes('cancel')) status = 'canceled';
      else if (['scheduled', 'dispatched', 'in progress', 'on my way', 'started'].includes(ws)) status = 'scheduled';

      let jobType = 'Service Call';
      const txt = ((job.description || '') + ' ' + (job.notes || []).map(n => n.content || '').join(' ')).toLowerCase();
      if (txt.includes('door') && txt.includes('opener') && (txt.includes('install') || txt.includes('new'))) jobType = 'Door + Opener Install';
      else if (txt.includes('door install') || txt.includes('new door') || txt.includes('door replacement')) jobType = 'Door Install';
      else if (txt.includes('opener install') || txt.includes('new opener') || txt.includes('liftmaster') || txt.includes('chamberlain')) jobType = 'Opener Install';
      else if (txt.includes('opener') && txt.includes('repair')) jobType = 'Opener + Repair';
      else if (txt.includes('spring') || txt.includes('repair') || txt.includes('broken') || txt.includes('cable') || txt.includes('off track') || txt.includes('panel')) jobType = 'Repair';
      else if (txt.includes('maintenance') || txt.includes('tune') || txt.includes('annual')) jobType = 'Maintenance Visit';
      else if (txt.includes('warranty') || txt.includes('callback') || txt.includes('redo')) jobType = 'Warranty Call';

      return {
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
      };
    });
    const r = sbPost('jobs', rows, 'hcp_id');
    if (r.status < 400) ok += chunk.length;
    else console.error(`  Jobs batch error: ${r.status}`);
  }
  console.log(`Imported ${ok} jobs\n`);
  return jobs;
}

function importInvoices() {
  const invoices = hcpFetchAll('invoices', 'invoices');

  const dbJobs = sbSelect('jobs', 'id,hcp_id');
  const jobMap = new Map(dbJobs.map(j => [j.hcp_id, j.id]));
  const dbCusts = sbSelect('customers', 'id,hcp_id');
  const custMap = new Map(dbCusts.map(c => [c.hcp_id, c.id]));

  const chunks = [];
  for (let i = 0; i < invoices.length; i += 500) chunks.push(invoices.slice(i, i + 500));

  let ok = 0;
  const jobPartsCost = new Map();
  const jobRevenue = new Map();

  for (const chunk of chunks) {
    const rows = chunk.map(inv => {
      const st = (inv.status || '').toLowerCase();
      let status = 'created';
      if (st === 'paid') status = 'paid';
      else if (st === 'void') status = 'voided';

      const matCost = (inv.items || [])
        .filter(i => i.type === 'material')
        .reduce((sum, i) => sum + (i.unit_cost || 0) * ((i.qty_in_hundredths || 100) / 100), 0);

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
    const r = sbPost('invoices', rows, 'hcp_id');
    if (r.status < 400) ok += chunk.length;
    else console.error(`  Inv batch error: ${r.status}`);
  }

  // Update jobs with real revenue and parts cost
  console.log(`  Updating ${jobPartsCost.size} jobs with parts costs & revenue...`);
  let updated = 0;
  for (const [hcpJobId, partsCost] of jobPartsCost) {
    const dbJobId = jobMap.get(hcpJobId);
    if (!dbJobId) continue;
    const rev = jobRevenue.get(hcpJobId) || 0;
    const data = { parts_cost: partsCost };
    if (rev > 0) data.revenue = rev;
    const r = sbPatch('jobs', data, 'id', dbJobId);
    if (r.status < 400) updated++;
  }
  console.log(`  Updated ${updated} jobs`);
  console.log(`Imported ${ok} invoices\n`);
}

function importEstimates() {
  const estimates = hcpFetchAll('estimates', 'estimates');
  const dbCusts = sbSelect('customers', 'id,hcp_id');
  const custMap = new Map(dbCusts.map(c => [c.hcp_id, c.id]));

  const chunks = [];
  for (let i = 0; i < estimates.length; i += 500) chunks.push(estimates.slice(i, i + 500));

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
    const r = sbPost('estimates', rows, 'hcp_id');
    if (r.status < 400) ok += chunk.length;
    else console.error(`  Est batch error: ${r.status}`);
  }
  console.log(`Imported ${ok} estimates\n`);
}

// --- Main ---

console.log('=== HousecallPro Historical Data Import ===\n');
importEmployees();
importCustomers();
const emps = sbSelect('users', 'id,role').filter(u => u.role === 'technician' || u.role === 'owner' || u.role === 'csr');
importJobs(sbSelect('users', 'id'));
importInvoices();
importEstimates();
console.log('=== Import Complete! ===');

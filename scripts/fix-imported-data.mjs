/**
 * Fix imported data:
 * 1. Insert employees with proper UUIDs
 * 2. Convert job revenue from cents to dollars
 * 3. Link jobs to technicians
 */

import { execSync } from 'child_process';
import { readFileSync, writeFileSync, unlinkSync } from 'fs';
import { resolve, dirname } from 'path';
import { fileURLToPath } from 'url';
import { randomUUID } from 'crypto';

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
} catch {}

const HCP_API_KEY = process.env.HCP_API_KEY;
const SUPABASE_URL = process.env.NEXT_PUBLIC_SUPABASE_URL;
const SUPABASE_SERVICE_KEY = process.env.SUPABASE_SERVICE_ROLE_KEY;
const SB_REST = `${SUPABASE_URL}/rest/v1`;

function curlJson(method, url, data, extraHeaders = '') {
  const tmpFile = `/tmp/sb_fix_${Date.now()}_${Math.random().toString(36).slice(2)}.json`;
  if (data) writeFileSync(tmpFile, JSON.stringify(data));
  try {
    const dataArg = data ? `-d @${tmpFile}` : '';
    const result = execSync(
      `curl -s -w "\\n%{http_code}" -X ${method} "${url}" ` +
      `-H "apikey: ${SUPABASE_SERVICE_KEY}" ` +
      `-H "Authorization: Bearer ${SUPABASE_SERVICE_KEY}" ` +
      `-H "Content-Type: application/json" ` +
      `${extraHeaders} ${dataArg}`,
      { maxBuffer: 50 * 1024 * 1024 }
    ).toString().trim();
    const lines = result.split('\n');
    const status = parseInt(lines[lines.length - 1]);
    const body = lines.slice(0, -1).join('\n');
    return { status, body };
  } finally {
    if (data) try { unlinkSync(tmpFile); } catch {}
  }
}

function sbSelect(table, select = '*', filter = '') {
  const url = `${SB_REST}/${table}?select=${encodeURIComponent(select)}${filter ? '&' + filter : ''}&limit=100000`;
  const r = curlJson('GET', url, null);
  return JSON.parse(r.body || '[]');
}

// --- Step 1: Import employees ---
console.log('=== Step 1: Import Employees ===\n');

const hcpEmps = JSON.parse(execSync(
  `curl -s "https://api.housecallpro.com/employees?page=1&page_size=100" -H "Authorization: Token ${HCP_API_KEY}"`,
  { maxBuffer: 10 * 1024 * 1024 }
).toString()).employees || [];

console.log(`Found ${hcpEmps.length} employees`);

const empMap = new Map(); // hcp_id -> uuid

// Insert owner first (others need manager_id)
let ownerUuid = null;
for (const emp of hcpEmps) {
  const role = (emp.role || '').toLowerCase();
  if (role === 'admin' || emp.permissions?.is_admin) {
    ownerUuid = randomUUID();
    empMap.set(emp.id, ownerUuid);
    const r = curlJson('POST', `${SB_REST}/users`, [{
      id: ownerUuid,
      email: emp.email,
      full_name: [emp.first_name, emp.last_name].filter(Boolean).join(' '),
      role: 'owner',
      is_active: true,
    }], '-H "Prefer: resolution=merge-duplicates,return=minimal"');
    console.log(`  Owner: ${emp.first_name} ${emp.last_name} -> ${ownerUuid} (${r.status})`);
  }
}

// Insert rest
for (const emp of hcpEmps) {
  if (empMap.has(emp.id)) continue;
  const uuid = randomUUID();
  empMap.set(emp.id, uuid);

  const role = (emp.role || '').toLowerCase();
  let mapped = 'technician';
  if (role === 'office staff') mapped = 'csr';

  const row = {
    id: uuid,
    email: emp.email || `${emp.id}@hcp.local`,
    full_name: [emp.first_name, emp.last_name].filter(Boolean).join(' '),
    role: mapped,
    is_active: true,
  };
  if (mapped === 'technician' && ownerUuid) row.manager_id = ownerUuid;

  const r = curlJson('POST', `${SB_REST}/users`, [row], '-H "Prefer: resolution=merge-duplicates,return=minimal"');
  console.log(`  ${mapped}: ${emp.first_name} ${emp.last_name} -> ${uuid} (${r.status})`);
}

// Commission tiers
console.log('\nSetting commission tiers...');
const techs = hcpEmps.filter(e => (e.role || '').toLowerCase() === 'field tech');
const tiers = [
  { level: 2, rate: 0.18 },
  { level: 1, rate: 0.16 },
  { level: 3, rate: 0.20 },
];
for (let i = 0; i < techs.length; i++) {
  const uuid = empMap.get(techs[i].id);
  if (!uuid) continue;
  const tier = tiers[i % tiers.length];
  const r = curlJson('POST', `${SB_REST}/commission_tiers`, [{
    user_id: uuid,
    tier_level: tier.level,
    rate: tier.rate,
    effective_date: '2024-01-01',
  }], '-H "Prefer: return=minimal"');
  console.log(`  ${techs[i].first_name}: Tier ${tier.level} (${r.status})`);
}

// --- Step 2: Fix revenue ---
console.log('\n=== Step 2: Fix Revenue ===\n');

// Invoice amounts are already in dollars (converted during import)
// Job revenue from HCP total_amount was NOT converted — it's in cents
// Strategy: for jobs WITH invoices, use invoice total; for others, divide by 100

const invoices = sbSelect('invoices', 'job_id,amount,status');
const jobRevFromInv = new Map();
for (const inv of invoices) {
  if (!inv.job_id || inv.status === 'voided') continue;
  jobRevFromInv.set(inv.job_id, (jobRevFromInv.get(inv.job_id) || 0) + inv.amount);
}
console.log(`Invoice revenue data for ${jobRevFromInv.size} jobs`);

const allJobs = sbSelect('jobs', 'id,revenue');
let fixedFromInv = 0, fixedCents = 0;

for (const job of allJobs) {
  const invRev = jobRevFromInv.get(job.id);
  if (invRev !== undefined && Math.abs(job.revenue - invRev) > 0.5) {
    curlJson('PATCH', `${SB_REST}/jobs?id=eq.${job.id}`, { revenue: invRev });
    fixedFromInv++;
  } else if (invRev === undefined && job.revenue > 200) {
    // Likely in cents
    curlJson('PATCH', `${SB_REST}/jobs?id=eq.${job.id}`, { revenue: job.revenue / 100 });
    fixedCents++;
  }
  if ((fixedFromInv + fixedCents) % 200 === 0 && (fixedFromInv + fixedCents) > 0) {
    console.log(`  Progress: ${fixedFromInv} from invoices, ${fixedCents} cents->dollars`);
  }
}
console.log(`Fixed ${fixedFromInv} from invoices, ${fixedCents} cents->dollars\n`);

// --- Step 3: Link jobs to technicians ---
console.log('=== Step 3: Link Jobs to Technicians ===\n');

const dbJobs = sbSelect('jobs', 'id,hcp_id');
const jobIdMap = new Map(dbJobs.map(j => [j.hcp_id, j.id]));

let linked = 0;
let page = 1;
let totalPages = 1;

while (page <= totalPages) {
  const data = JSON.parse(execSync(
    `curl -s "https://api.housecallpro.com/jobs?page=${page}&page_size=200" -H "Authorization: Token ${HCP_API_KEY}"`,
    { maxBuffer: 50 * 1024 * 1024 }
  ).toString());

  totalPages = data.total_pages || 1;

  for (const job of (data.jobs || [])) {
    const techHcpId = job.assigned_employees?.[0]?.id;
    if (!techHcpId) continue;
    const techUuid = empMap.get(techHcpId);
    if (!techUuid) continue;
    const dbJobId = jobIdMap.get(job.id);
    if (!dbJobId) continue;

    curlJson('PATCH', `${SB_REST}/jobs?id=eq.${dbJobId}`, { technician_id: techUuid });
    linked++;
  }

  console.log(`  Page ${page}/${totalPages}: ${linked} linked`);
  page++;
}

console.log(`\nLinked ${linked} jobs to technicians`);

// Print mapping
console.log('\n=== Complete! Employee Mapping ===\n');
for (const [hcpId, uuid] of empMap) {
  const emp = hcpEmps.find(e => e.id === hcpId);
  console.log(`${emp?.first_name} ${emp?.last_name} (${emp?.role}): ${uuid}`);
}

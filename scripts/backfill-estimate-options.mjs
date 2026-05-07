/**
 * Backfill estimate_options + estimates.job_id from raw_events.
 *
 * The webhook handler used to drop estimate.option.* events because of a
 * parser bug, but raw_events stored every payload verbatim. This script
 * replays them so the Options/Ticket KPI has historical data.
 *
 * Idempotent: estimate_options rows upsert on hcp_id, estimates.job_id
 * updates only fill in nulls.
 *
 * Usage:
 *   node scripts/backfill-estimate-options.mjs
 */

import { execSync } from 'child_process';
import { readFileSync, writeFileSync, unlinkSync } from 'fs';
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
    if (!process.env[trimmed.slice(0, eqIdx)]) {
      process.env[trimmed.slice(0, eqIdx)] = trimmed.slice(eqIdx + 1);
    }
  }
} catch {}

const SUPABASE_URL = process.env.NEXT_PUBLIC_SUPABASE_URL;
const SUPABASE_SERVICE_KEY = process.env.SUPABASE_SERVICE_ROLE_KEY;
if (!SUPABASE_URL || !SUPABASE_SERVICE_KEY) {
  console.error('Missing NEXT_PUBLIC_SUPABASE_URL or SUPABASE_SERVICE_ROLE_KEY in env');
  process.exit(1);
}
const SB_REST = `${SUPABASE_URL}/rest/v1`;

function curlJson(method, url, data, extraHeaders = '') {
  const tmpFile = `/tmp/sb_backfill_${Date.now()}_${Math.random().toString(36).slice(2)}.json`;
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

function sbSelectAll(table, select, filter) {
  // Page through in chunks of 1000.
  const out = [];
  let offset = 0;
  while (true) {
    const url = `${SB_REST}/${table}?select=${encodeURIComponent(select)}` +
      (filter ? `&${filter}` : '') +
      `&order=received_at.asc&limit=1000&offset=${offset}`;
    const r = curlJson('GET', url, null);
    if (r.status >= 300) {
      console.error(`Query failed (${r.status}): ${r.body}`);
      process.exit(1);
    }
    const rows = JSON.parse(r.body || '[]');
    out.push(...rows);
    if (rows.length < 1000) break;
    offset += 1000;
  }
  return out;
}

function sbUpsert(table, rows, conflictKey) {
  if (rows.length === 0) return;
  // Chunk to avoid overlong curl bodies.
  const chunkSize = 500;
  for (let i = 0; i < rows.length; i += chunkSize) {
    const chunk = rows.slice(i, i + chunkSize);
    const url = `${SB_REST}/${table}?on_conflict=${conflictKey}`;
    const r = curlJson('POST', url, chunk, '-H "Prefer: resolution=merge-duplicates,return=minimal"');
    if (r.status >= 300) {
      console.error(`Upsert ${table} failed (${r.status}): ${r.body}`);
      process.exit(1);
    }
    process.stdout.write(`.`);
  }
}

function sbPatch(table, filter, patch) {
  const url = `${SB_REST}/${table}?${filter}`;
  const r = curlJson('PATCH', url, patch, '-H "Prefer: return=minimal"');
  if (r.status >= 300) {
    console.error(`Patch ${table} failed (${r.status}): ${r.body}`);
  }
}

// --- 1. Replay estimate.option.* events into estimate_options ---
console.log('Loading estimate.option.* raw_events...');
const optionRows = sbSelectAll(
  'raw_events',
  'event_type,payload',
  `event_type=like.estimate.option.*`
);
console.log(`Found ${optionRows.length} option events`);

const optionUpserts = [];
for (const row of optionRows) {
  const evt = row.event_type; // 'estimate.option.created' | 'estimate.option.approval_status_changed'
  const data = row.payload?.data;
  if (!data?.id) continue;
  const estimateHcpId = data.estimate_id || data.estimate?.id;
  if (!estimateHcpId) continue;

  optionUpserts.push({
    hcp_id: data.id,
    estimate_hcp_id: estimateHcpId,
    name: data.name ?? null,
    amount: Number(data.total ?? data.amount ?? 0),
    status: evt.endsWith('approval_status_changed')
      ? (data.approval_status || 'created')
      : 'created',
  });
}

console.log(`\nUpserting ${optionUpserts.length} estimate_options rows`);
sbUpsert('estimate_options', optionUpserts, 'hcp_id');
console.log('\nestimate_options backfill complete');

// --- 2. Resolve estimate_options.estimate_id from estimate_hcp_id ---
console.log('Resolving estimate_id FKs...');
const estimates = sbSelectAll('estimates', 'id,hcp_id', '');
const hcpToId = new Map(estimates.map(e => [e.hcp_id, e.id]));
const fkPatches = optionUpserts
  .filter(o => hcpToId.has(o.estimate_hcp_id))
  .map(o => ({ hcp_id: o.hcp_id, estimate_id: hcpToId.get(o.estimate_hcp_id) }));
sbUpsert('estimate_options', fkPatches, 'hcp_id');
console.log(`\nResolved ${fkPatches.length} estimate_id FKs`);

// --- 3. Backfill estimates.job_id from raw_events estimate.* (non-option) ---
console.log('Loading estimate.* raw_events (excluding options)...');
const estimateRows = sbSelectAll(
  'raw_events',
  'event_type,payload',
  `event_type=like.estimate.*&event_type=not.like.estimate.option.*`
);
console.log(`Found ${estimateRows.length} estimate events`);

const jobs = sbSelectAll('jobs', 'id,hcp_id', '');
const jobHcpToId = new Map(jobs.map(j => [j.hcp_id, j.id]));

let patched = 0;
for (const row of estimateRows) {
  const data = row.payload?.data;
  if (!data?.id || !data.job_id) continue;
  const internalJobId = jobHcpToId.get(data.job_id);
  if (!internalJobId) continue;
  sbPatch(
    'estimates',
    `hcp_id=eq.${encodeURIComponent(data.id)}&job_id=is.null`,
    { job_id: internalJobId }
  );
  patched++;
  if (patched % 50 === 0) process.stdout.write(`.`);
}
console.log(`\nPatched ${patched} estimates with job_id`);
console.log('Backfill complete.');

#!/usr/bin/env node
import { execSync } from 'child_process';
import { readFileSync } from 'fs';
import { resolve, dirname } from 'path';
import { fileURLToPath } from 'url';
import { Server } from '@modelcontextprotocol/sdk/server/index.js';
import { StdioServerTransport } from '@modelcontextprotocol/sdk/server/stdio.js';
import { CallToolRequestSchema, ListToolsRequestSchema } from '@modelcontextprotocol/sdk/types.js';

const __dirname = dirname(fileURLToPath(import.meta.url));
const envPath = resolve(__dirname, '..', '..', '.env.local');
try {
  const envFile = readFileSync(envPath, 'utf-8');
  for (const line of envFile.split('\n')) {
    const trimmed = line.trim();
    if (!trimmed || trimmed.startsWith('#')) continue;
    const eqIdx = trimmed.indexOf('=');
    if (eqIdx === -1) continue;
    const key = trimmed.slice(0, eqIdx);
    if (!process.env[key]) process.env[key] = trimmed.slice(eqIdx + 1);
  }
} catch { /* .env.local optional */ }

const HCP_API_KEY = process.env.HCP_API_KEY;
if (!HCP_API_KEY) {
  console.error('HCP_API_KEY missing. Set it in scripts/../.env.local or the environment.');
  process.exit(1);
}

const HCP_BASE = 'https://api.housecallpro.com';

function hcpGet(path, query = {}) {
  const qs = Object.entries(query)
    .filter(([, v]) => v !== undefined && v !== null && v !== '')
    .map(([k, v]) => `${encodeURIComponent(k)}=${encodeURIComponent(v)}`)
    .join('&');
  const url = `${HCP_BASE}${path}${qs ? `?${qs}` : ''}`;
  const raw = execSync(
    `curl -sS "${url}" -H "Authorization: Token ${HCP_API_KEY}" -H "Accept: application/json"`,
    { maxBuffer: 50 * 1024 * 1024 }
  ).toString();
  try { return JSON.parse(raw); }
  catch { return { error: 'non_json_response', body: raw.slice(0, 2000) }; }
}

const tools = [
  {
    name: 'list_jobs',
    description: 'List HousecallPro jobs. Supports paging and optional filters (customer_id, scheduled_start_min/max as ISO dates, work_status).',
    inputSchema: {
      type: 'object',
      properties: {
        page: { type: 'number', default: 1 },
        page_size: { type: 'number', default: 50 },
        customer_id: { type: 'string' },
        scheduled_start_min: { type: 'string', description: 'ISO 8601' },
        scheduled_start_max: { type: 'string', description: 'ISO 8601' },
        work_status: { type: 'string' },
      },
    },
    handler: (a) => hcpGet('/jobs', {
      page: a.page ?? 1,
      page_size: a.page_size ?? 50,
      customer_id: a.customer_id,
      scheduled_start_min: a.scheduled_start_min,
      scheduled_start_max: a.scheduled_start_max,
      work_status: a.work_status,
    }),
  },
  {
    name: 'get_job',
    description: 'Get a single HousecallPro job by id.',
    inputSchema: {
      type: 'object',
      required: ['id'],
      properties: { id: { type: 'string' } },
    },
    handler: (a) => hcpGet(`/jobs/${encodeURIComponent(a.id)}`),
  },
  {
    name: 'list_customers',
    description: 'List HousecallPro customers. Supports paging and free-text query q.',
    inputSchema: {
      type: 'object',
      properties: {
        page: { type: 'number', default: 1 },
        page_size: { type: 'number', default: 50 },
        q: { type: 'string', description: 'Free text search' },
      },
    },
    handler: (a) => hcpGet('/customers', {
      page: a.page ?? 1,
      page_size: a.page_size ?? 50,
      q: a.q,
    }),
  },
  {
    name: 'get_customer',
    description: 'Get a single HousecallPro customer by id.',
    inputSchema: {
      type: 'object',
      required: ['id'],
      properties: { id: { type: 'string' } },
    },
    handler: (a) => hcpGet(`/customers/${encodeURIComponent(a.id)}`),
  },
  {
    name: 'list_employees',
    description: 'List HousecallPro employees (technicians and office staff).',
    inputSchema: {
      type: 'object',
      properties: {
        page: { type: 'number', default: 1 },
        page_size: { type: 'number', default: 100 },
      },
    },
    handler: (a) => hcpGet('/employees', {
      page: a.page ?? 1,
      page_size: a.page_size ?? 100,
    }),
  },
  {
    name: 'list_estimates',
    description: 'List HousecallPro estimates.',
    inputSchema: {
      type: 'object',
      properties: {
        page: { type: 'number', default: 1 },
        page_size: { type: 'number', default: 50 },
      },
    },
    handler: (a) => hcpGet('/estimates', {
      page: a.page ?? 1,
      page_size: a.page_size ?? 50,
    }),
  },
  {
    name: 'get_estimate',
    description: 'Get a single HousecallPro estimate by id.',
    inputSchema: {
      type: 'object',
      required: ['id'],
      properties: { id: { type: 'string' } },
    },
    handler: (a) => hcpGet(`/estimates/${encodeURIComponent(a.id)}`),
  },
  {
    name: 'hcp_get',
    description: 'Escape hatch: GET an arbitrary HousecallPro API path (read-only). Use when no specific tool exists.',
    inputSchema: {
      type: 'object',
      required: ['path'],
      properties: {
        path: { type: 'string', description: 'Path beginning with / e.g. /jobs/abc/line_items' },
        query: { type: 'object', description: 'Query params as string-valued object' },
      },
    },
    handler: (a) => hcpGet(a.path, a.query ?? {}),
  },
];

const toolMap = Object.fromEntries(tools.map((t) => [t.name, t]));

const server = new Server(
  { name: 'hcp-mcp', version: '0.1.0' },
  { capabilities: { tools: {} } }
);

server.setRequestHandler(ListToolsRequestSchema, async () => ({
  tools: tools.map(({ name, description, inputSchema }) => ({ name, description, inputSchema })),
}));

server.setRequestHandler(CallToolRequestSchema, async (req) => {
  const tool = toolMap[req.params.name];
  if (!tool) throw new Error(`Unknown tool: ${req.params.name}`);
  const result = tool.handler(req.params.arguments ?? {});
  return { content: [{ type: 'text', text: JSON.stringify(result, null, 2) }] };
});

await server.connect(new StdioServerTransport());

import type { Tables } from '@/types/database';
import type { UserProfile } from '@/types/roles';

// ==================== USERS ====================
export const SEED_USERS: UserProfile[] = [
  {
    id: 'user-ceo-001',
    email: 'ceo@twinsgarage.com',
    fullName: 'Mike Johnson',
    role: 'owner',
    isActive: true,
    createdAt: '2024-01-01T00:00:00Z',
  },
  {
    id: 'user-mgr-001',
    email: 'manager@twinsgarage.com',
    fullName: 'Sarah Williams',
    role: 'manager',
    isActive: true,
    createdAt: '2024-01-01T00:00:00Z',
  },
  {
    id: 'user-tech-001',
    email: 'jake@twinsgarage.com',
    fullName: 'Jake Martinez',
    role: 'technician',
    managerId: 'user-mgr-001',
    isActive: true,
    createdAt: '2024-01-15T00:00:00Z',
  },
  {
    id: 'user-tech-002',
    email: 'ryan@twinsgarage.com',
    fullName: 'Ryan Cooper',
    role: 'technician',
    managerId: 'user-mgr-001',
    isActive: true,
    createdAt: '2024-02-01T00:00:00Z',
  },
  {
    id: 'user-tech-003',
    email: 'marcus@twinsgarage.com',
    fullName: 'Marcus Thompson',
    role: 'technician',
    managerId: 'user-mgr-001',
    isActive: true,
    createdAt: '2024-03-01T00:00:00Z',
  },
  {
    id: 'user-csr-001',
    email: 'emma@twinsgarage.com',
    fullName: 'Emma Davis',
    role: 'csr',
    isActive: true,
    createdAt: '2024-01-10T00:00:00Z',
  },
];

// ==================== CUSTOMERS ====================
const customerNames = [
  'John Smith', 'Maria Garcia', 'David Wilson', 'Lisa Anderson',
  'Robert Taylor', 'Jennifer Brown', 'Michael Davis', 'Sarah Miller',
  'James Moore', 'Emily Jackson', 'William White', 'Jessica Harris',
  'Daniel Martin', 'Ashley Thompson', 'Matthew Robinson', 'Amanda Clark',
  'Christopher Lewis', 'Stephanie Walker', 'Andrew Hall', 'Nicole Allen',
  'Joshua Young', 'Megan King', 'Kevin Wright', 'Rachel Scott',
  'Brandon Green', 'Lauren Adams', 'Justin Baker', 'Samantha Nelson',
  'Tyler Hill', 'Kayla Rivera', 'Aaron Campbell', 'Brittany Mitchell',
];

export const SEED_CUSTOMERS: Tables<'customers'>[] = customerNames.map((name, i) => ({
  id: `cust-${String(i + 1).padStart(3, '0')}`,
  hcp_id: `hcp-cust-${String(i + 1).padStart(3, '0')}`,
  name,
  email: `${name.toLowerCase().replace(' ', '.')}@example.com`,
  phone: `(608) 555-${String(1000 + i).slice(1)}`,
  address: `${100 + i * 23} Main St, Madison, WI 53703`,
  created_at: '2024-01-01T00:00:00Z',
  updated_at: '2024-01-01T00:00:00Z',
}));

// ==================== JOBS ====================
const jobTypes = [
  'Door Install', 'Repair', 'Opener Install', 'Opener + Repair',
  'Door + Opener Install', 'Service Call', 'Maintenance Visit', 'Warranty Call',
];

const techIds = ['user-tech-001', 'user-tech-002', 'user-tech-003'];

function randomInt(min: number, max: number): number {
  return Math.floor(Math.random() + (max - min + 1)) * 0 + Math.floor(Math.random() * (max - min + 1)) + min;
}

function randomElement<T>(arr: T[]): T {
  return arr[Math.floor(Math.random() * arr.length)];
}

function generateJobs(): Tables<'jobs'>[] {
  const jobs: Tables<'jobs'>[] = [];
  let jobCounter = 1;

  // Generate jobs across the last 6 months
  for (let monthsAgo = 5; monthsAgo >= 0; monthsAgo--) {
    const now = new Date();
    const targetMonth = new Date(now.getFullYear(), now.getMonth() - monthsAgo, 1);

    // ~15-25 jobs per month
    const jobCount = randomInt(15, 25);
    for (let j = 0; j < jobCount; j++) {
      const day = randomInt(1, 28);
      const jobDate = new Date(targetMonth.getFullYear(), targetMonth.getMonth(), day);
      const techId = randomElement(techIds);
      const custIndex = randomInt(0, customerNames.length - 1);
      const jobType = randomElement(jobTypes);

      let revenue = 0;
      let partsCost = 0;
      const isWarranty = jobType === 'Warranty Call';
      const isCompleted = Math.random() > 0.1; // 90% completion

      if (!isWarranty && isCompleted) {
        switch (jobType) {
          case 'Door Install':
            revenue = randomInt(1800, 4500);
            partsCost = randomInt(600, 1500);
            break;
          case 'Door + Opener Install':
            revenue = randomInt(2500, 5500);
            partsCost = randomInt(900, 2000);
            break;
          case 'Opener Install':
            revenue = randomInt(400, 900);
            partsCost = randomInt(150, 350);
            break;
          case 'Repair':
          case 'Service Call':
            revenue = randomInt(150, 800);
            partsCost = randomInt(30, 200);
            break;
          case 'Opener + Repair':
            revenue = randomInt(300, 1200);
            partsCost = randomInt(100, 400);
            break;
          case 'Maintenance Visit':
            revenue = randomInt(100, 250);
            partsCost = randomInt(10, 50);
            break;
        }
      }

      const protectionPlanSold = !isWarranty && isCompleted && Math.random() > 0.8;

      jobs.push({
        id: `job-${String(jobCounter).padStart(3, '0')}`,
        hcp_id: `hcp-job-${String(jobCounter).padStart(3, '0')}`,
        customer_id: SEED_CUSTOMERS[custIndex].id,
        technician_id: techId,
        job_type: jobType,
        status: isCompleted ? 'completed' : (Math.random() > 0.5 ? 'scheduled' : 'canceled'),
        scheduled_at: jobDate.toISOString(),
        completed_at: isCompleted ? new Date(jobDate.getTime() + 3600000 * randomInt(1, 4)).toISOString() : null,
        revenue,
        parts_cost: partsCost,
        parts_cost_override: null,
        protection_plan_sold: protectionPlanSold,
        created_at: jobDate.toISOString(),
        updated_at: jobDate.toISOString(),
      });
      jobCounter++;
    }
  }
  return jobs;
}

export const SEED_JOBS = generateJobs();

// ==================== COMMISSION TIERS ====================
export const SEED_COMMISSION_TIERS: Tables<'commission_tiers'>[] = [
  { id: 'tier-001', user_id: 'user-tech-001', tier_level: 2, rate: 0.18, effective_date: '2024-01-01', created_at: '2024-01-01T00:00:00Z' },
  { id: 'tier-002', user_id: 'user-tech-002', tier_level: 1, rate: 0.16, effective_date: '2024-01-01', created_at: '2024-01-01T00:00:00Z' },
  { id: 'tier-003', user_id: 'user-tech-003', tier_level: 3, rate: 0.20, effective_date: '2024-01-01', created_at: '2024-01-01T00:00:00Z' },
];

// ==================== COMMISSION RECORDS ====================
function generateCommissionRecords(): Tables<'commission_records'>[] {
  const records: Tables<'commission_records'>[] = [];
  const tierMap: Record<string, number> = {
    'user-tech-001': 0.18,
    'user-tech-002': 0.16,
    'user-tech-003': 0.20,
  };

  SEED_JOBS.forEach((job, i) => {
    if (job.status !== 'completed' || job.revenue === 0 || !job.technician_id) return;

    const partsCost = job.parts_cost_override ?? job.parts_cost;
    const netRevenue = Math.max(0, job.revenue - partsCost);
    const tierRate = tierMap[job.technician_id] || 0.16;
    const commissionAmount = Math.round(netRevenue * tierRate * 100) / 100;
    const managerOverride = Math.round(netRevenue * 0.02 * 100) / 100;

    let managerBonus = 0;
    if (netRevenue >= 400) {
      managerBonus = 20;
      let threshold = 500;
      while (netRevenue >= threshold) {
        managerBonus += 10;
        threshold += 100;
      }
    }

    records.push({
      id: `comm-${String(i + 1).padStart(3, '0')}`,
      job_id: job.id,
      technician_id: job.technician_id,
      gross_revenue: job.revenue,
      parts_cost: partsCost,
      net_revenue: netRevenue,
      tier_rate: tierRate,
      commission_amount: commissionAmount,
      manager_id: 'user-mgr-001',
      manager_override: managerOverride,
      manager_bonus: managerBonus,
      created_at: job.completed_at || job.created_at,
    });
  });

  return records;
}

export const SEED_COMMISSION_RECORDS = generateCommissionRecords();

// ==================== REVIEWS ====================
function generateReviews(): Tables<'reviews'>[] {
  const reviews: Tables<'reviews'>[] = [];
  let reviewCount = 1;

  SEED_JOBS.forEach((job) => {
    if (job.status !== 'completed' || job.job_type === 'Warranty Call') return;
    if (Math.random() > 0.3) return; // 30% of jobs get reviews

    const customer = SEED_CUSTOMERS.find(c => c.id === job.customer_id);
    const rating = Math.random() > 0.15 ? 5 : randomInt(3, 4); // 85% are 5-star

    reviews.push({
      id: `review-${String(reviewCount).padStart(3, '0')}`,
      google_review_id: `goog-rev-${String(reviewCount).padStart(3, '0')}`,
      reviewer_name: customer?.name || 'Anonymous',
      rating,
      review_text: rating === 5 ? 'Great service! Very professional.' : 'Good work, showed up on time.',
      technician_id: job.technician_id,
      review_date: job.completed_at || job.created_at,
      created_at: job.completed_at || job.created_at,
    });
    reviewCount++;
  });

  return reviews;
}

export const SEED_REVIEWS = generateReviews();

// ==================== CALL RECORDS ====================
const callSources = ['google_ads', 'google_lsa', 'organic', 'referral', 'website_contact_form', 'website_chat'];
const callOutcomes = ['booked', 'not_booked', 'voicemail'];

function generateCallRecords(): Tables<'call_records'>[] {
  const records: Tables<'call_records'>[] = [];
  let callCount = 1;

  for (let monthsAgo = 5; monthsAgo >= 0; monthsAgo--) {
    const now = new Date();
    const targetMonth = new Date(now.getFullYear(), now.getMonth() - monthsAgo, 1);
    const callsThisMonth = randomInt(40, 80);

    for (let i = 0; i < callsThisMonth; i++) {
      const day = randomInt(1, 28);
      const hour = randomInt(8, 17);
      const callDate = new Date(targetMonth.getFullYear(), targetMonth.getMonth(), day, hour);
      const source = randomElement(callSources);
      const outcome = Math.random() > 0.3 ? 'booked' : randomElement(['not_booked', 'voicemail']);

      records.push({
        id: `call-${String(callCount).padStart(3, '0')}`,
        caller_name: randomElement(customerNames),
        caller_phone: `(608) 555-${String(randomInt(1000, 9999))}`,
        source,
        channel: source,
        duration_seconds: randomInt(30, 600),
        outcome,
        notes: outcome === 'booked' ? 'Appointment scheduled' : null,
        csr_id: 'user-csr-001',
        ghl_agency: 'agency_a',
        created_at: callDate.toISOString(),
      });
      callCount++;
    }
  }

  return records;
}

export const SEED_CALL_RECORDS = generateCallRecords();

// ==================== MARKETING SPEND ====================
function generateMarketingSpend(): Tables<'marketing_spend'>[] {
  const records: Tables<'marketing_spend'>[] = [];
  let spendCount = 1;
  const channels = ['google_ads', 'google_lsa', 'meta_ads'];
  const campaigns: Record<string, string[]> = {
    google_ads: ['Garage Door Repair Madison', 'Emergency Garage Door', 'New Garage Door Install'],
    google_lsa: ['Local Services'],
    meta_ads: ['Madison Homeowners', 'Garage Door Special Offer'],
  };

  for (let monthsAgo = 5; monthsAgo >= 0; monthsAgo--) {
    const now = new Date();
    const targetMonth = new Date(now.getFullYear(), now.getMonth() - monthsAgo, 1);

    channels.forEach(channel => {
      const campaignList = campaigns[channel];
      campaignList.forEach(campaign => {
        // Weekly spend records
        for (let week = 0; week < 4; week++) {
          const date = new Date(targetMonth.getFullYear(), targetMonth.getMonth(), 1 + week * 7);
          const spend = channel === 'google_ads' ? randomInt(500, 1500) : channel === 'google_lsa' ? randomInt(300, 800) : randomInt(200, 600);
          const impressions = randomInt(5000, 30000);
          const clicks = randomInt(100, 800);
          const conversions = randomInt(5, 30);

          records.push({
            id: `spend-${String(spendCount).padStart(3, '0')}`,
            channel,
            campaign,
            spend,
            impressions,
            clicks,
            conversions,
            date: date.toISOString().split('T')[0],
            created_at: date.toISOString(),
          });
          spendCount++;
        }
      });
    });
  }

  return records;
}

export const SEED_MARKETING_SPEND = generateMarketingSpend();

// ==================== INVOICES (derived from jobs) ====================
export const SEED_INVOICES: Tables<'invoices'>[] = SEED_JOBS
  .filter(j => j.status === 'completed' && j.revenue > 0)
  .map((job, i) => ({
    id: `inv-${String(i + 1).padStart(3, '0')}`,
    hcp_id: `hcp-inv-${String(i + 1).padStart(3, '0')}`,
    job_id: job.id,
    customer_id: job.customer_id,
    amount: job.revenue,
    status: 'paid',
    paid_at: job.completed_at,
    created_at: job.created_at,
    updated_at: job.updated_at,
  }));

'use client';

import { useQuery } from '@tanstack/react-query';
import { createClient } from '@/lib/supabase/client';
import type { Tables } from '@/types/database';

const supabase = createClient();

function isDemo(): boolean {
  const url = process.env.NEXT_PUBLIC_SUPABASE_URL;
  return !url || url === 'https://demo.supabase.co' || url === 'https://your-project.supabase.co';
}

export function useSupabaseJobs(from: Date, to: Date, technicianId?: string) {
  return useQuery({
    queryKey: ['jobs', from.toISOString(), to.toISOString(), technicianId],
    queryFn: async () => {
      if (isDemo()) return null;
      let query = supabase
        .from('jobs')
        .select('*')
        .gte('created_at', from.toISOString())
        .lte('created_at', to.toISOString());
      if (technicianId) query = query.eq('technician_id', technicianId);
      const { data, error } = await query;
      if (error) { console.error('Jobs query error:', error); return null; }
      return data as Tables<'jobs'>[] | null;
    },
    staleTime: 30_000,
  });
}

export function useSupabaseCommissionRecords(from: Date, to: Date, technicianId?: string) {
  return useQuery({
    queryKey: ['commission_records', from.toISOString(), to.toISOString(), technicianId],
    queryFn: async () => {
      if (isDemo()) return null;
      let query = supabase
        .from('commission_records')
        .select('*')
        .gte('created_at', from.toISOString())
        .lte('created_at', to.toISOString());
      if (technicianId) query = query.eq('technician_id', technicianId);
      const { data, error } = await query;
      if (error) { console.error('Commission query error:', error); return null; }
      return data as Tables<'commission_records'>[] | null;
    },
    staleTime: 30_000,
  });
}

export function useSupabaseReviews(from: Date, to: Date, technicianId?: string) {
  return useQuery({
    queryKey: ['reviews', from.toISOString(), to.toISOString(), technicianId],
    queryFn: async () => {
      if (isDemo()) return null;
      let query = supabase
        .from('reviews')
        .select('*')
        .gte('created_at', from.toISOString())
        .lte('created_at', to.toISOString());
      if (technicianId) query = query.eq('technician_id', technicianId);
      const { data, error } = await query;
      if (error) { console.error('Reviews query error:', error); return null; }
      return data as Tables<'reviews'>[] | null;
    },
    staleTime: 30_000,
  });
}

export function useSupabaseCallRecords(from: Date, to: Date, csrId?: string) {
  return useQuery({
    queryKey: ['call_records', from.toISOString(), to.toISOString(), csrId],
    queryFn: async () => {
      if (isDemo()) return null;
      let query = supabase
        .from('call_records')
        .select('*')
        .gte('created_at', from.toISOString())
        .lte('created_at', to.toISOString());
      if (csrId) query = query.eq('csr_id', csrId);
      const { data, error } = await query;
      if (error) { console.error('Call records query error:', error); return null; }
      return data as Tables<'call_records'>[] | null;
    },
    staleTime: 30_000,
  });
}

export function useSupabaseMarketingSpend(from: Date, to: Date) {
  return useQuery({
    queryKey: ['marketing_spend', from.toISOString(), to.toISOString()],
    queryFn: async () => {
      if (isDemo()) return null;
      const { data, error } = await supabase
        .from('marketing_spend')
        .select('*')
        .gte('created_at', from.toISOString())
        .lte('created_at', to.toISOString());
      if (error) { console.error('Marketing spend query error:', error); return null; }
      return data as Tables<'marketing_spend'>[] | null;
    },
    staleTime: 30_000,
  });
}

export function useSupabaseUsers() {
  return useQuery({
    queryKey: ['users'],
    queryFn: async () => {
      if (isDemo()) return null;
      const { data, error } = await supabase
        .from('users')
        .select('*')
        .eq('is_active', true);
      if (error) { console.error('Users query error:', error); return null; }
      return data as Tables<'users'>[] | null;
    },
    staleTime: 60_000,
  });
}

export function useSupabaseCustomers() {
  return useQuery({
    queryKey: ['customers'],
    queryFn: async () => {
      if (isDemo()) return null;
      const { data, error } = await supabase
        .from('customers')
        .select('*');
      if (error) { console.error('Customers query error:', error); return null; }
      return data as Tables<'customers'>[] | null;
    },
    staleTime: 60_000,
  });
}

export function useSupabaseCommissionTiers() {
  return useQuery({
    queryKey: ['commission_tiers'],
    queryFn: async () => {
      if (isDemo()) return null;
      const { data, error } = await supabase
        .from('commission_tiers')
        .select('*');
      if (error) { console.error('Commission tiers query error:', error); return null; }
      return data as Tables<'commission_tiers'>[] | null;
    },
    staleTime: 60_000,
  });
}

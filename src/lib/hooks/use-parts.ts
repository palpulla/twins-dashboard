'use client';

import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { createClient } from '@/lib/supabase/client';
import type { Tables } from '@/types/database';

const supabase = createClient();
// Supabase v2 narrows write args to `never` without Relationships entries on
// every Database table. Until those are filled in, bypass typing on writes
// only — reads stay typed via Tables<>.
// eslint-disable-next-line @typescript-eslint/no-explicit-any
const writeDb = supabase as unknown as { from: (table: string) => any };

const PARTS_KEY = ['parts'];
const CATEGORIES_KEY = ['parts_categories'];

function isDemo(): boolean {
  const url = process.env.NEXT_PUBLIC_SUPABASE_URL;
  return !url || url === 'https://demo.supabase.co' || url === 'https://your-project.supabase.co';
}

export type Part = Tables<'parts'>;
export type PartCategory = Tables<'parts_categories'>;
export type PriceField = 'retail_price' | 'unit_cost';

export function useParts() {
  return useQuery({
    queryKey: PARTS_KEY,
    queryFn: async (): Promise<Part[]> => {
      if (isDemo()) return [];
      const { data, error } = await supabase
        .from('parts')
        .select('*')
        .order('category', { ascending: true })
        .order('name', { ascending: true });
      if (error) {
        console.error('Parts query error:', error);
        return [];
      }
      return (data ?? []) as Part[];
    },
    staleTime: 30_000,
  });
}

export function usePartCategories() {
  return useQuery({
    queryKey: CATEGORIES_KEY,
    queryFn: async (): Promise<PartCategory[]> => {
      if (isDemo()) return [];
      const { data, error } = await supabase
        .from('parts_categories')
        .select('*')
        .order('sort_order', { ascending: true });
      if (error) {
        console.error('Parts categories query error:', error);
        return [];
      }
      return (data ?? []) as PartCategory[];
    },
    staleTime: 5 * 60_000,
  });
}

export function useUpsertPart() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (
      payload: Partial<Part> & { id?: string },
    ): Promise<Part | null> => {
      if (isDemo()) return null;
      const row = {
        sku: payload.sku ?? null,
        name: (payload.name ?? '').trim(),
        category: (payload.category ?? 'Hardware').trim(),
        unit_cost: payload.unit_cost ?? 0,
        retail_price: payload.retail_price ?? 0,
        is_active: payload.is_active ?? true,
      };
      if (payload.id) {
        const { data, error } = await writeDb
          .from('parts')
          .update(row)
          .eq('id', payload.id)
          .select('*')
          .single();
        if (error) throw error;
        return data as Part;
      }
      const { data, error } = await writeDb
        .from('parts')
        .insert(row)
        .select('*')
        .single();
      if (error) throw error;
      return data as Part;
    },
    onSuccess: () => qc.invalidateQueries({ queryKey: PARTS_KEY }),
  });
}

export function useDeletePart() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (id: string): Promise<void> => {
      if (isDemo()) return;
      const { error } = await writeDb.from('parts').delete().eq('id', id);
      if (error) throw error;
    },
    onSuccess: () => qc.invalidateQueries({ queryKey: PARTS_KEY }),
  });
}

export type BulkAdjustment = {
  ids: string[];
  field: PriceField;
  mode: 'amount' | 'percent';
  /** Positive value increases, negative decreases. */
  delta: number;
};

/**
 * Apply the same $ or % change to many parts in one round-trip-per-row.
 * Reads each row's current price, computes the new price client-side
 * (floored at $0, rounded to cents), and updates by id.
 */
export function useBulkAdjustPrices() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async ({ ids, field, mode, delta }: BulkAdjustment): Promise<number> => {
      if (ids.length === 0) return 0;
      if (isDemo()) return 0;

      const { data: rows, error: readErr } = await writeDb
        .from('parts')
        .select(`id, ${field}`)
        .in('id', ids);
      if (readErr) throw readErr;
      if (!rows) return 0;

      const updates = (rows as Array<Record<string, unknown>>).map(r => {
        const current = Number(r[field] ?? 0);
        const next = mode === 'amount'
          ? current + delta
          : current * (1 + delta / 100);
        const rounded = Math.max(0, Math.round(next * 100) / 100);
        return { id: r.id as string, value: rounded };
      });

      let updated = 0;
      for (const u of updates) {
        const { error } = await writeDb
          .from('parts')
          .update({ [field]: u.value })
          .eq('id', u.id);
        if (error) throw error;
        updated += 1;
      }
      return updated;
    },
    onSuccess: () => qc.invalidateQueries({ queryKey: PARTS_KEY }),
  });
}

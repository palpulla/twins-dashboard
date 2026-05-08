'use client';

import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { createClient } from '@/lib/supabase/client';
import type { Tables } from '@/types/database';

const supabase = createClient();
// Supabase v2 typings narrow .insert/.update args to `never` unless Database
// has Relationships entries on every table. Until those are filled in, we
// bypass typing on writes only — reads stay typed via Tables<>.
// eslint-disable-next-line @typescript-eslint/no-explicit-any
const writeDb = supabase as unknown as { from: (table: string) => any };
const QUERY_KEY = ['parts_catalog'];

function isDemo(): boolean {
  const url = process.env.NEXT_PUBLIC_SUPABASE_URL;
  return !url || url === 'https://demo.supabase.co' || url === 'https://your-project.supabase.co';
}

export type Part = Tables<'parts_catalog'>;

export function useParts() {
  return useQuery({
    queryKey: QUERY_KEY,
    queryFn: async (): Promise<Part[]> => {
      if (isDemo()) return [];
      const { data, error } = await supabase
        .from('parts_catalog')
        .select('*')
        .order('category', { ascending: true })
        .order('name', { ascending: true });
      if (error) {
        console.error('Parts catalog query error:', error);
        return [];
      }
      return (data ?? []) as Part[];
    },
    staleTime: 30_000,
  });
}

export function useUpsertPart() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (
      payload: Partial<Part> & { id?: string },
    ): Promise<Part | null> => {
      if (isDemo()) return null;
      if (payload.id) {
        const update = {
          sku: payload.sku ?? null,
          name: payload.name,
          category: payload.category,
          price: payload.price,
          description: payload.description ?? null,
          is_active: payload.is_active,
        };
        const { data, error } = await writeDb
          .from('parts_catalog')
          .update(update)
          .eq('id', payload.id)
          .select('*')
          .single();
        if (error) throw error;
        return data as Part;
      }
      const insert = {
        sku: payload.sku ?? null,
        name: payload.name ?? '',
        category: payload.category ?? 'Uncategorized',
        price: payload.price ?? 0,
        description: payload.description ?? null,
        is_active: payload.is_active ?? true,
      };
      const { data, error } = await writeDb
        .from('parts_catalog')
        .insert(insert)
        .select('*')
        .single();
      if (error) throw error;
      return data as Part;
    },
    onSuccess: () => qc.invalidateQueries({ queryKey: QUERY_KEY }),
  });
}

export function useDeletePart() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async (id: string): Promise<void> => {
      if (isDemo()) return;
      const { error } = await writeDb.from('parts_catalog').delete().eq('id', id);
      if (error) throw error;
    },
    onSuccess: () => qc.invalidateQueries({ queryKey: QUERY_KEY }),
  });
}

export type BulkAdjustment = {
  ids: string[];
  mode: 'amount' | 'percent';
  /** Positive value increases, negative decreases. */
  delta: number;
};

/**
 * Apply the same $ or % change to many parts in one round-trip-per-row.
 * We compute new prices on the client (need each row's current price) and
 * issue one UPDATE per id. Prices are floored at $0.
 */
export function useBulkAdjustPrices() {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: async ({ ids, mode, delta }: BulkAdjustment): Promise<number> => {
      if (ids.length === 0) return 0;
      if (isDemo()) return 0;
      const { data: rows, error: readErr } = await writeDb
        .from('parts_catalog')
        .select('id, price')
        .in('id', ids);
      if (readErr) throw readErr;
      if (!rows) return 0;

      const updates = (rows as Array<{ id: string; price: number }>).map(r => {
        const current = Number(r.price);
        const next = mode === 'amount'
          ? current + delta
          : current * (1 + delta / 100);
        return { id: r.id, price: Math.max(0, Math.round(next * 100) / 100) };
      });

      let updated = 0;
      for (const u of updates) {
        const { error } = await writeDb
          .from('parts_catalog')
          .update({ price: u.price })
          .eq('id', u.id);
        if (error) throw error;
        updated += 1;
      }
      return updated;
    },
    onSuccess: () => qc.invalidateQueries({ queryKey: QUERY_KEY }),
  });
}

'use client';

import { useMemo, useState } from 'react';
import { Header } from '@/components/layout/header';
import { Button } from '@/components/ui/button';
import { Modal } from '@/components/ui/modal';
import { formatCurrencyDollars } from '@/lib/utils/format';
import {
  useParts, usePartCategories, useUpsertPart, useDeletePart, useBulkAdjustPrices,
  type Part, type PriceField,
} from '@/lib/hooks/use-parts';

type BulkMode = 'amount' | 'percent';
type BulkDirection = 'increase' | 'decrease';

interface PartFormState {
  id?: string;
  sku: string;
  name: string;
  category: string;
  unit_cost: string;
  retail_price: string;
  is_active: boolean;
}

const EMPTY_FORM: PartFormState = {
  sku: '',
  name: '',
  category: 'Hardware',
  unit_cost: '',
  retail_price: '',
  is_active: true,
};

function partToForm(p: Part): PartFormState {
  return {
    id: p.id,
    sku: p.sku ?? '',
    name: p.name,
    category: p.category,
    unit_cost: String(p.unit_cost ?? 0),
    retail_price: String(p.retail_price ?? 0),
    is_active: p.is_active,
  };
}

const FIELD_LABEL: Record<PriceField, string> = {
  retail_price: 'Retail price',
  unit_cost: 'Unit cost',
};

export default function PartsLibraryPage() {
  const { data: parts = [], isLoading } = useParts();
  const { data: categoryRows = [] } = usePartCategories();
  const upsert = useUpsertPart();
  const remove = useDeletePart();
  const bulkAdjust = useBulkAdjustPrices();

  // Filters
  const [search, setSearch] = useState('');
  const [categoryFilter, setCategoryFilter] = useState<string>('all');
  const [showInactive, setShowInactive] = useState(false);

  // Selection
  const [selectedIds, setSelectedIds] = useState<Set<string>>(new Set());

  // Modal
  const [editing, setEditing] = useState<PartFormState | null>(null);

  // Bulk controls
  const [bulkField, setBulkField] = useState<PriceField>('retail_price');
  const [bulkMode, setBulkMode] = useState<BulkMode>('percent');
  const [bulkDirection, setBulkDirection] = useState<BulkDirection>('increase');
  const [bulkValue, setBulkValue] = useState<string>('');
  const [scope, setScope] = useState<'selected' | 'category'>('selected');
  const [bulkCategory, setBulkCategory] = useState<string>('');

  const categoryNames = useMemo(() => {
    if (categoryRows.length > 0) return categoryRows.map(c => c.name);
    // Fallback for demo / DB unreachable: derive from rows
    const set = new Set<string>();
    parts.forEach(p => p.category && set.add(p.category));
    return Array.from(set).sort();
  }, [categoryRows, parts]);

  const filtered = useMemo(() => {
    const q = search.trim().toLowerCase();
    return parts.filter(p => {
      if (!showInactive && !p.is_active) return false;
      if (categoryFilter !== 'all' && p.category !== categoryFilter) return false;
      if (q) {
        const blob = `${p.name} ${p.sku ?? ''} ${p.category}`.toLowerCase();
        if (!blob.includes(q)) return false;
      }
      return true;
    });
  }, [parts, search, categoryFilter, showInactive]);

  const allFilteredSelected = filtered.length > 0
    && filtered.every(p => selectedIds.has(p.id));

  function toggleAll() {
    setSelectedIds(prev => {
      const next = new Set(prev);
      if (allFilteredSelected) filtered.forEach(p => next.delete(p.id));
      else filtered.forEach(p => next.add(p.id));
      return next;
    });
  }

  function toggleOne(id: string) {
    setSelectedIds(prev => {
      const next = new Set(prev);
      if (next.has(id)) next.delete(id);
      else next.add(id);
      return next;
    });
  }

  // Resolve which IDs the bulk action will touch
  const targetIds = useMemo(() => {
    if (scope === 'selected') return Array.from(selectedIds);
    if (!bulkCategory) return [];
    return parts
      .filter(p => p.category === bulkCategory && (showInactive || p.is_active))
      .map(p => p.id);
  }, [scope, selectedIds, bulkCategory, parts, showInactive]);

  const previewRows = useMemo(() => {
    if (targetIds.length === 0) return [];
    const value = parseFloat(bulkValue);
    if (!Number.isFinite(value) || value <= 0) return [];
    const sign = bulkDirection === 'increase' ? 1 : -1;
    const delta = sign * value;
    const idSet = new Set(targetIds);
    return parts
      .filter(p => idSet.has(p.id))
      .slice(0, 8)
      .map(p => {
        const current = p[bulkField] ?? 0;
        const next = bulkMode === 'amount'
          ? current + delta
          : current * (1 + delta / 100);
        return { part: p, current, next: Math.max(0, Math.round(next * 100) / 100) };
      });
  }, [targetIds, parts, bulkField, bulkMode, bulkDirection, bulkValue]);

  async function applyBulk() {
    const value = parseFloat(bulkValue);
    if (!Number.isFinite(value) || value <= 0 || targetIds.length === 0) return;
    const sign = bulkDirection === 'increase' ? 1 : -1;
    await bulkAdjust.mutateAsync({
      ids: targetIds,
      field: bulkField,
      mode: bulkMode,
      delta: sign * value,
    });
    setBulkValue('');
    setSelectedIds(new Set());
  }

  async function savePart(form: PartFormState) {
    const unitCost = parseFloat(form.unit_cost);
    const retail = parseFloat(form.retail_price);
    await upsert.mutateAsync({
      id: form.id,
      sku: form.sku.trim() || null,
      name: form.name.trim(),
      category: form.category.trim() || 'Hardware',
      unit_cost: Number.isFinite(unitCost) ? Math.max(0, unitCost) : 0,
      retail_price: Number.isFinite(retail) ? Math.max(0, retail) : 0,
      is_active: form.is_active,
    });
    setEditing(null);
  }

  async function deletePart(p: Part) {
    if (!confirm(`Delete "${p.name}"? This cannot be undone.`)) return;
    await remove.mutateAsync(p.id);
    setSelectedIds(prev => {
      const next = new Set(prev);
      next.delete(p.id);
      return next;
    });
  }

  const selectedCount = selectedIds.size;
  const targetCount = targetIds.length;

  return (
    <div className="bg-surface-container-low min-h-screen">
      <Header
        title="Parts Library"
        subtitle="Edit prices individually, or update many at once by $ amount or %."
        showDatePicker={false}
        actions={
          <Button onClick={() => setEditing({ ...EMPTY_FORM })}>
            Add part
          </Button>
        }
      />

      <div className="p-6 md:p-8 max-w-[1400px] mx-auto space-y-6">
        {/* Filters */}
        <div className="bg-surface-container-lowest rounded-2xl card-shadow p-4 md:p-6 flex flex-col md:flex-row md:items-end gap-4">
          <div className="flex-1">
            <label className="text-[11px] uppercase font-bold tracking-wider text-on-surface-variant">Search</label>
            <input
              type="text"
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              placeholder="Name, SKU, category…"
              className="mt-1 w-full bg-surface-container px-4 py-2 rounded-lg border border-outline-variant/30 text-sm focus:outline-none focus:ring-2 focus:ring-primary"
            />
          </div>
          <div className="md:w-56">
            <label className="text-[11px] uppercase font-bold tracking-wider text-on-surface-variant">Category</label>
            <select
              value={categoryFilter}
              onChange={(e) => setCategoryFilter(e.target.value)}
              className="mt-1 w-full bg-surface-container px-4 py-2 rounded-lg border border-outline-variant/30 text-sm focus:outline-none focus:ring-2 focus:ring-primary"
            >
              <option value="all">All categories</option>
              {categoryNames.map(c => (
                <option key={c} value={c}>{c}</option>
              ))}
            </select>
          </div>
          <label className="flex items-center gap-2 text-sm font-medium text-on-surface select-none">
            <input
              type="checkbox"
              checked={showInactive}
              onChange={(e) => setShowInactive(e.target.checked)}
              className="w-4 h-4 accent-primary"
            />
            Show inactive
          </label>
        </div>

        {/* Bulk update panel */}
        <div className="bg-surface-container-lowest rounded-2xl card-shadow p-4 md:p-6">
          <div className="flex items-baseline justify-between mb-4 flex-wrap gap-2">
            <div>
              <p className="text-secondary font-semibold tracking-wider text-xs uppercase">Bulk price update</p>
              <h3 className="font-headline font-bold text-xl text-primary">
                Increase or decrease prices by $ amount or %
              </h3>
            </div>
            <div className="text-sm text-on-surface-variant">
              {targetCount} part{targetCount === 1 ? '' : 's'} will be affected
            </div>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
            {/* Which price column */}
            <div className="md:col-span-3">
              <label className="text-[11px] uppercase font-bold tracking-wider text-on-surface-variant">Update</label>
              <div className="mt-1 inline-flex w-full rounded-lg overflow-hidden border border-outline-variant/30">
                <button
                  type="button"
                  onClick={() => setBulkField('retail_price')}
                  className={`flex-1 px-3 py-2 text-sm font-semibold ${
                    bulkField === 'retail_price' ? 'bg-primary text-white' : 'bg-surface-container text-on-surface'
                  }`}
                >
                  Retail price
                </button>
                <button
                  type="button"
                  onClick={() => setBulkField('unit_cost')}
                  className={`flex-1 px-3 py-2 text-sm font-semibold ${
                    bulkField === 'unit_cost' ? 'bg-primary text-white' : 'bg-surface-container text-on-surface'
                  }`}
                >
                  Unit cost
                </button>
              </div>
            </div>

            {/* Scope */}
            <div className="md:col-span-3">
              <label className="text-[11px] uppercase font-bold tracking-wider text-on-surface-variant">Apply to</label>
              <div className="mt-1 inline-flex w-full rounded-lg overflow-hidden border border-outline-variant/30">
                <button
                  type="button"
                  onClick={() => setScope('selected')}
                  className={`flex-1 px-3 py-2 text-sm font-semibold ${
                    scope === 'selected' ? 'bg-primary text-white' : 'bg-surface-container text-on-surface'
                  }`}
                >
                  Selected ({selectedCount})
                </button>
                <button
                  type="button"
                  onClick={() => setScope('category')}
                  className={`flex-1 px-3 py-2 text-sm font-semibold ${
                    scope === 'category' ? 'bg-primary text-white' : 'bg-surface-container text-on-surface'
                  }`}
                >
                  Category
                </button>
              </div>
            </div>

            {/* Category dropdown when scope=category */}
            {scope === 'category' && (
              <div className="md:col-span-3">
                <label className="text-[11px] uppercase font-bold tracking-wider text-on-surface-variant">Category</label>
                <select
                  value={bulkCategory}
                  onChange={(e) => setBulkCategory(e.target.value)}
                  className="mt-1 w-full bg-surface-container px-4 py-2 rounded-lg border border-outline-variant/30 text-sm focus:outline-none focus:ring-2 focus:ring-primary"
                >
                  <option value="">— Pick category —</option>
                  {categoryNames.map(c => (
                    <option key={c} value={c}>{c}</option>
                  ))}
                </select>
              </div>
            )}

            {/* Direction */}
            <div className="md:col-span-2">
              <label className="text-[11px] uppercase font-bold tracking-wider text-on-surface-variant">Direction</label>
              <div className="mt-1 inline-flex w-full rounded-lg overflow-hidden border border-outline-variant/30">
                <button
                  type="button"
                  onClick={() => setBulkDirection('increase')}
                  className={`flex-1 px-3 py-2 text-sm font-semibold ${
                    bulkDirection === 'increase' ? 'bg-primary text-white' : 'bg-surface-container text-on-surface'
                  }`}
                >
                  Increase
                </button>
                <button
                  type="button"
                  onClick={() => setBulkDirection('decrease')}
                  className={`flex-1 px-3 py-2 text-sm font-semibold ${
                    bulkDirection === 'decrease' ? 'bg-primary text-white' : 'bg-surface-container text-on-surface'
                  }`}
                >
                  Decrease
                </button>
              </div>
            </div>

            {/* Mode */}
            <div className={scope === 'category' ? 'md:col-span-2' : 'md:col-span-2'}>
              <label className="text-[11px] uppercase font-bold tracking-wider text-on-surface-variant">By</label>
              <div className="mt-1 inline-flex w-full rounded-lg overflow-hidden border border-outline-variant/30">
                <button
                  type="button"
                  onClick={() => setBulkMode('amount')}
                  className={`flex-1 px-3 py-2 text-sm font-semibold ${
                    bulkMode === 'amount' ? 'bg-primary text-white' : 'bg-surface-container text-on-surface'
                  }`}
                >
                  $ amount
                </button>
                <button
                  type="button"
                  onClick={() => setBulkMode('percent')}
                  className={`flex-1 px-3 py-2 text-sm font-semibold ${
                    bulkMode === 'percent' ? 'bg-primary text-white' : 'bg-surface-container text-on-surface'
                  }`}
                >
                  %
                </button>
              </div>
            </div>

            {/* Value */}
            <div className={scope === 'category' ? 'md:col-span-2' : 'md:col-span-3'}>
              <label className="text-[11px] uppercase font-bold tracking-wider text-on-surface-variant">
                {bulkMode === 'amount' ? 'Dollar change' : 'Percent change'}
              </label>
              <div className="mt-1 relative">
                <input
                  type="number"
                  min={0}
                  step={bulkMode === 'amount' ? '1' : '0.5'}
                  value={bulkValue}
                  onChange={(e) => setBulkValue(e.target.value)}
                  placeholder={bulkMode === 'amount' ? '25' : '10'}
                  className="w-full bg-surface-container px-4 py-2 pr-9 rounded-lg border border-outline-variant/30 text-sm focus:outline-none focus:ring-2 focus:ring-primary"
                />
                <span className="absolute right-3 top-1/2 -translate-y-1/2 text-on-surface-variant text-sm">
                  {bulkMode === 'amount' ? '$' : '%'}
                </span>
              </div>
            </div>

            {/* Apply */}
            <div className={scope === 'category' ? 'md:col-span-12' : 'md:col-span-2'}>
              <Button
                onClick={applyBulk}
                disabled={
                  bulkAdjust.isPending
                  || targetCount === 0
                  || !bulkValue
                  || parseFloat(bulkValue) <= 0
                }
                className="w-full"
              >
                {bulkAdjust.isPending
                  ? 'Applying…'
                  : `Apply to ${targetCount} ${FIELD_LABEL[bulkField].toLowerCase()}${targetCount === 1 ? '' : 's'}`}
              </Button>
            </div>
          </div>

          {/* Preview */}
          {previewRows.length > 0 && (
            <div className="mt-4 p-3 rounded-lg bg-surface-container border border-outline-variant/20">
              <p className="text-[11px] uppercase font-bold tracking-wider text-on-surface-variant mb-2">
                Preview · {FIELD_LABEL[bulkField]}
                {previewRows.length < targetCount && ` (first ${previewRows.length} of ${targetCount})`}
              </p>
              <div className="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-1 text-sm">
                {previewRows.map(row => (
                  <div key={row.part.id} className="flex justify-between gap-3">
                    <span className="truncate text-on-surface">{row.part.name}</span>
                    <span className="font-mono whitespace-nowrap">
                      <span className="text-on-surface-variant">{formatCurrencyDollars(row.current)}</span>
                      <span className="text-on-surface-variant mx-1">→</span>
                      <span className="font-bold text-primary">{formatCurrencyDollars(row.next)}</span>
                    </span>
                  </div>
                ))}
              </div>
            </div>
          )}
        </div>

        {/* Parts table */}
        <div className="bg-surface-container-lowest rounded-2xl card-shadow overflow-hidden">
          <div className="px-4 md:px-6 py-3 border-b border-outline-variant/20 flex items-center justify-between">
            <p className="font-semibold text-on-surface">
              {filtered.length} part{filtered.length === 1 ? '' : 's'}
              {parts.length !== filtered.length && (
                <span className="text-on-surface-variant font-normal"> · filtered from {parts.length}</span>
              )}
            </p>
            {selectedCount > 0 && (
              <button
                onClick={() => setSelectedIds(new Set())}
                className="text-sm text-primary hover:underline font-medium"
              >
                Clear selection ({selectedCount})
              </button>
            )}
          </div>

          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead className="bg-surface-container">
                <tr className="text-left text-[11px] uppercase tracking-wider text-on-surface-variant">
                  <th className="px-4 py-3 w-10">
                    <input
                      type="checkbox"
                      checked={allFilteredSelected}
                      onChange={toggleAll}
                      className="w-4 h-4 accent-primary"
                      aria-label="Select all filtered"
                    />
                  </th>
                  <th className="px-4 py-3 font-bold">SKU</th>
                  <th className="px-4 py-3 font-bold">Name</th>
                  <th className="px-4 py-3 font-bold">Category</th>
                  <th className="px-4 py-3 font-bold text-right">Unit cost</th>
                  <th className="px-4 py-3 font-bold text-right">Retail price</th>
                  <th className="px-4 py-3 font-bold">Status</th>
                  <th className="px-4 py-3 font-bold text-right">Actions</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-outline-variant/20">
                {isLoading ? (
                  <tr>
                    <td colSpan={8} className="px-4 py-8 text-center text-on-surface-variant">Loading parts…</td>
                  </tr>
                ) : filtered.length === 0 ? (
                  <tr>
                    <td colSpan={8} className="px-4 py-12 text-center">
                      <p className="text-on-surface-variant mb-3">
                        {parts.length === 0
                          ? 'No parts in the library yet.'
                          : 'No parts match the current filters.'}
                      </p>
                      {parts.length === 0 && (
                        <Button onClick={() => setEditing({ ...EMPTY_FORM })}>Add your first part</Button>
                      )}
                    </td>
                  </tr>
                ) : (
                  filtered.map(p => (
                    <tr key={p.id} className={selectedIds.has(p.id) ? 'bg-primary/5' : 'hover:bg-surface-container/40'}>
                      <td className="px-4 py-3">
                        <input
                          type="checkbox"
                          checked={selectedIds.has(p.id)}
                          onChange={() => toggleOne(p.id)}
                          className="w-4 h-4 accent-primary"
                          aria-label={`Select ${p.name}`}
                        />
                      </td>
                      <td className="px-4 py-3 font-mono text-xs text-on-surface-variant">{p.sku ?? '—'}</td>
                      <td className="px-4 py-3 font-medium text-on-surface">{p.name}</td>
                      <td className="px-4 py-3 text-on-surface-variant">{p.category}</td>
                      <td className="px-4 py-3 text-right font-mono text-on-surface">
                        {formatCurrencyDollars(p.unit_cost)}
                      </td>
                      <td className="px-4 py-3 text-right font-mono font-semibold text-primary">
                        {formatCurrencyDollars(p.retail_price)}
                      </td>
                      <td className="px-4 py-3">
                        <span className={`inline-flex px-2 py-0.5 rounded-full text-xs font-semibold ${
                          p.is_active
                            ? 'bg-success/10 text-success'
                            : 'bg-on-surface-variant/10 text-on-surface-variant'
                        }`}>
                          {p.is_active ? 'Active' : 'Inactive'}
                        </span>
                      </td>
                      <td className="px-4 py-3 text-right whitespace-nowrap">
                        <Button variant="ghost" size="sm" onClick={() => setEditing(partToForm(p))}>
                          Edit
                        </Button>
                        <Button variant="ghost" size="sm" onClick={() => deletePart(p)}>
                          Delete
                        </Button>
                      </td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </div>
        </div>
      </div>

      {/* Edit / add modal */}
      {editing && (
        <PartEditModal
          form={editing}
          onChange={setEditing}
          onClose={() => setEditing(null)}
          onSave={savePart}
          saving={upsert.isPending}
          categoryOptions={categoryNames}
        />
      )}
    </div>
  );
}

interface EditModalProps {
  form: PartFormState;
  onChange: (next: PartFormState) => void;
  onClose: () => void;
  onSave: (form: PartFormState) => void | Promise<void>;
  saving: boolean;
  categoryOptions: string[];
}

function PartEditModal({ form, onChange, onClose, onSave, saving, categoryOptions }: EditModalProps) {
  const isNew = !form.id;
  return (
    <Modal isOpen onClose={onClose} title={isNew ? 'Add part' : 'Edit part'} size="lg">
      <form
        onSubmit={(e) => { e.preventDefault(); onSave(form); }}
        className="space-y-4"
      >
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          <Field label="Name" required>
            <input
              type="text"
              required
              value={form.name}
              onChange={(e) => onChange({ ...form, name: e.target.value })}
              className="input"
            />
          </Field>
          <Field label="SKU">
            <input
              type="text"
              value={form.sku}
              onChange={(e) => onChange({ ...form, sku: e.target.value })}
              className="input"
              placeholder="optional"
            />
          </Field>
          <Field label="Category">
            <select
              value={form.category}
              onChange={(e) => onChange({ ...form, category: e.target.value })}
              className="input"
            >
              {categoryOptions.length === 0 && <option value="Hardware">Hardware</option>}
              {categoryOptions.map(c => (
                <option key={c} value={c}>{c}</option>
              ))}
            </select>
          </Field>
          <div />
          <Field label="Unit cost (USD)">
            <input
              type="number"
              min={0}
              step="0.01"
              value={form.unit_cost}
              onChange={(e) => onChange({ ...form, unit_cost: e.target.value })}
              className="input"
              placeholder="0.00"
            />
          </Field>
          <Field label="Retail price (USD)">
            <input
              type="number"
              min={0}
              step="0.01"
              value={form.retail_price}
              onChange={(e) => onChange({ ...form, retail_price: e.target.value })}
              className="input"
              placeholder="0.00"
            />
          </Field>
        </div>
        <label className="flex items-center gap-2 text-sm font-medium text-on-surface select-none">
          <input
            type="checkbox"
            checked={form.is_active}
            onChange={(e) => onChange({ ...form, is_active: e.target.checked })}
            className="w-4 h-4 accent-primary"
          />
          Active (available on jobs)
        </label>
        <div className="flex justify-end gap-2 pt-2 border-t border-outline-variant/20">
          <Button type="button" variant="outline" onClick={onClose} disabled={saving}>Cancel</Button>
          <Button type="submit" disabled={saving || !form.name.trim()}>
            {saving ? 'Saving…' : isNew ? 'Add part' : 'Save changes'}
          </Button>
        </div>
        <style jsx>{`
          .input {
            width: 100%;
            background-color: #F5F6FA;
            border: 1px solid rgba(0,0,0,0.08);
            border-radius: 8px;
            padding: 8px 12px;
            font-size: 14px;
            color: #012650;
          }
          .input:focus {
            outline: none;
            border-color: #012650;
            box-shadow: 0 0 0 2px rgba(1,38,80,0.15);
          }
        `}</style>
      </form>
    </Modal>
  );
}

function Field({ label, required, children }: { label: string; required?: boolean; children: React.ReactNode }) {
  return (
    <label className="block">
      <span className="text-[11px] uppercase font-bold tracking-wider text-on-surface-variant">
        {label}{required && <span className="text-danger ml-0.5">*</span>}
      </span>
      <div className="mt-1">{children}</div>
    </label>
  );
}

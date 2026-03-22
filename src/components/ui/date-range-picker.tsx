'use client';

import { useState, useRef, useEffect } from 'react';
import { format } from 'date-fns';
import { useDashboardStore } from '@/lib/store/dashboard-store';
import { DATE_PRESET_LABELS } from '@/lib/utils/date-utils';
import type { DatePreset } from '@/types/kpi';

const PRESETS: DatePreset[] = [
  'today', 'yesterday', 'this_week', 'last_week',
  'this_month', 'last_month', 'this_quarter', 'last_quarter',
  'this_year', 'last_year', 'all_time', 'custom',
];

export function DateRangePicker() {
  const { dateRange, setDatePreset, setCustomDateRange } = useDashboardStore();
  const [isOpen, setIsOpen] = useState(false);
  const [showCustom, setShowCustom] = useState(false);
  const [customFrom, setCustomFrom] = useState('');
  const [customTo, setCustomTo] = useState('');
  const ref = useRef<HTMLDivElement>(null);

  useEffect(() => {
    function handleClickOutside(event: MouseEvent) {
      if (ref.current && !ref.current.contains(event.target as Node)) {
        setIsOpen(false);
        setShowCustom(false);
      }
    }
    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, []);

  const handlePresetClick = (preset: DatePreset) => {
    if (preset === 'custom') {
      setShowCustom(true);
      return;
    }
    setDatePreset(preset);
    setIsOpen(false);
    setShowCustom(false);
  };

  const handleCustomApply = () => {
    if (customFrom && customTo) {
      setCustomDateRange(new Date(customFrom), new Date(customTo));
      setIsOpen(false);
      setShowCustom(false);
    }
  };

  const displayLabel = dateRange.preset === 'custom'
    ? `${format(dateRange.from, 'MMM d, yyyy')} - ${format(dateRange.to, 'MMM d, yyyy')}`
    : DATE_PRESET_LABELS[dateRange.preset];

  return (
    <div className="relative" ref={ref}>
      <button
        onClick={() => setIsOpen(!isOpen)}
        className="flex items-center gap-2 px-4 py-2 bg-white border border-gray-200 rounded-lg text-sm font-medium text-[#012650] hover:border-[#FBBC03] transition-colors shadow-[0_1px_3px_rgba(0,0,0,0.1)]"
      >
        <svg className="w-4 h-4 text-[#3B445C]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
        </svg>
        {displayLabel}
        <svg className={`w-4 h-4 text-[#3B445C] transition-transform ${isOpen ? 'rotate-180' : ''}`} fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" />
        </svg>
      </button>

      {isOpen && (
        <div className="absolute right-0 top-full mt-2 bg-white rounded-lg shadow-xl border border-gray-200 z-50 min-w-[200px]">
          {!showCustom ? (
            <div className="py-2">
              {PRESETS.map((preset) => (
                <button
                  key={preset}
                  onClick={() => handlePresetClick(preset)}
                  className={`w-full text-left px-4 py-2 text-sm hover:bg-[#F5F6FA] transition-colors ${
                    dateRange.preset === preset ? 'text-[#FBBC03] font-semibold bg-[#012650]/5' : 'text-[#012650]'
                  }`}
                >
                  {DATE_PRESET_LABELS[preset]}
                </button>
              ))}
            </div>
          ) : (
            <div className="p-4 space-y-3">
              <div>
                <label className="block text-xs font-medium text-[#3B445C] mb-1">From</label>
                <input
                  type="date"
                  value={customFrom}
                  onChange={(e) => setCustomFrom(e.target.value)}
                  className="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-[#FBBC03] focus:border-transparent"
                />
              </div>
              <div>
                <label className="block text-xs font-medium text-[#3B445C] mb-1">To</label>
                <input
                  type="date"
                  value={customTo}
                  onChange={(e) => setCustomTo(e.target.value)}
                  className="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-[#FBBC03] focus:border-transparent"
                />
              </div>
              <div className="flex gap-2">
                <button
                  onClick={() => setShowCustom(false)}
                  className="flex-1 px-3 py-2 text-sm border border-gray-200 rounded-lg hover:bg-gray-50"
                >
                  Back
                </button>
                <button
                  onClick={handleCustomApply}
                  disabled={!customFrom || !customTo}
                  className="flex-1 px-3 py-2 text-sm bg-[#012650] text-white rounded-lg hover:bg-[#012650]/90 disabled:opacity-50"
                >
                  Apply
                </button>
              </div>
            </div>
          )}
        </div>
      )}
    </div>
  );
}

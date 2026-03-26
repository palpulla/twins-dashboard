'use client';

import { useState } from 'react';
import { formatCurrencyDollars, formatPercentage, formatCount } from '@/lib/utils/format';

interface TechLeaderboardEntry {
  id: string;
  fullName: string;
  totalRevenue: number;
  avgTicket: number;
  conversionRate: number;
  fiveStarReviews: number;
  doorsInstalled: number;
  protectionPlans: number;
  totalJobs: number;
  completedJobs: number;
  totalCommission: number;
}

interface LeaderboardTableProps {
  data: TechLeaderboardEntry[];
  onTechClick?: (techId: string) => void;
}

type SortKey = 'totalRevenue' | 'avgTicket' | 'conversionRate' | 'fiveStarReviews' | 'doorsInstalled' | 'protectionPlans';

const SORT_OPTIONS: { key: SortKey; label: string }[] = [
  { key: 'totalRevenue', label: 'Revenue' },
  { key: 'avgTicket', label: 'Avg Ticket' },
  { key: 'conversionRate', label: 'Conversion' },
  { key: 'doorsInstalled', label: 'Doors' },
  { key: 'fiveStarReviews', label: 'Reviews' },
  { key: 'protectionPlans', label: 'Protection Plans' },
];

export function LeaderboardTable({ data, onTechClick }: LeaderboardTableProps) {
  const [sortBy, setSortBy] = useState<SortKey>('totalRevenue');

  const sorted = [...data].sort((a, b) => b[sortBy] - a[sortBy]);

  return (
    <div>
      <div className="flex items-center justify-between mb-6">
        <h2 className="font-headline font-bold text-2xl text-primary">Technician Leaderboard</h2>
        <div className="flex gap-2">
          {SORT_OPTIONS.map(opt => (
            <button
              key={opt.key}
              onClick={() => setSortBy(opt.key)}
              className={`px-4 py-2 text-xs font-semibold rounded transition-colors ${
                sortBy === opt.key
                  ? 'bg-primary text-white'
                  : 'bg-surface-container-low text-on-surface hover:bg-surface-container-high'
              }`}
            >
              {opt.label}
            </button>
          ))}
        </div>
      </div>

      <div className="bg-surface-container-lowest rounded-xl overflow-hidden card-shadow">
        <table className="w-full text-left border-collapse">
          <thead>
            <tr className="bg-surface-container-low text-on-surface-variant">
              <th className="p-5 font-bold text-[11px] uppercase tracking-widest">Rank</th>
              <th className="p-5 font-bold text-[11px] uppercase tracking-widest">Technician</th>
              <th className="p-5 font-bold text-[11px] uppercase tracking-widest text-center">Jobs</th>
              <th className="p-5 font-bold text-[11px] uppercase tracking-widest text-right">Revenue</th>
              <th className="p-5 font-bold text-[11px] uppercase tracking-widest text-right">Avg Ticket</th>
              <th className="p-5 font-bold text-[11px] uppercase tracking-widest text-right">Conv. Rate</th>
              <th className="p-5 font-bold text-[11px] uppercase tracking-widest text-right">Reviews</th>
              <th className="p-5 font-bold text-[11px] uppercase tracking-widest text-right">Commission</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-surface-container">
            {sorted.map((tech, index) => (
              <tr
                key={tech.id}
                className={`group hover:bg-surface-container-low/50 transition-colors ${onTechClick ? 'cursor-pointer' : ''}`}
                onClick={onTechClick ? () => onTechClick(tech.id) : undefined}
              >
                <td className="p-5">
                  <span className={`w-8 h-8 rounded-full font-mono font-bold flex items-center justify-center text-xs ${
                    index === 0 ? 'bg-secondary-container text-on-secondary-container' :
                    index === 1 ? 'bg-surface-container-high text-on-surface-variant' :
                    index === 2 ? 'bg-amber-700/20 text-amber-800' :
                    'bg-surface-container text-on-surface-variant'
                  }`}>
                    {String(index + 1).padStart(2, '0')}
                  </span>
                </td>
                <td className="p-5">
                  <div className="flex items-center gap-3">
                    <div className="w-8 h-8 rounded-full bg-primary flex items-center justify-center text-white text-xs font-bold">
                      {tech.fullName.split(' ').map(n => n[0]).join('')}
                    </div>
                    <div>
                      <p className="font-bold text-primary">{tech.fullName}</p>
                      <p className="text-xs text-on-surface-variant font-medium">{tech.completedJobs} completed</p>
                    </div>
                  </div>
                </td>
                <td className="p-5 text-center font-mono font-medium">{tech.totalJobs}</td>
                <td className="p-5 text-right font-mono font-bold text-primary">{formatCurrencyDollars(tech.totalRevenue)}</td>
                <td className="p-5 text-right font-mono text-on-surface-variant">{formatCurrencyDollars(tech.avgTicket)}</td>
                <td className="p-5 text-right font-mono text-on-surface-variant">{formatPercentage(tech.conversionRate)}</td>
                <td className="p-5 text-right font-mono text-on-surface-variant">{tech.fiveStarReviews}</td>
                <td className="p-5 text-right font-mono font-bold text-success">{formatCurrencyDollars(tech.totalCommission)}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}

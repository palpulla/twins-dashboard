'use client';

import { useState } from 'react';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
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

function formatValue(key: SortKey, value: number): string {
  switch (key) {
    case 'totalRevenue':
    case 'avgTicket':
      return formatCurrencyDollars(value);
    case 'conversionRate':
      return formatPercentage(value);
    default:
      return formatCount(value);
  }
}

export function LeaderboardTable({ data, onTechClick }: LeaderboardTableProps) {
  const [sortBy, setSortBy] = useState<SortKey>('totalRevenue');

  const sorted = [...data].sort((a, b) => b[sortBy] - a[sortBy]);

  return (
    <Card>
      <CardHeader className="flex flex-row items-center justify-between">
        <CardTitle>Technician Leaderboard</CardTitle>
        <div className="flex gap-1">
          {SORT_OPTIONS.map(opt => (
            <button
              key={opt.key}
              onClick={() => setSortBy(opt.key)}
              className={`px-3 py-1 text-xs font-medium rounded-full transition-colors ${
                sortBy === opt.key
                  ? 'bg-[#012650] text-white'
                  : 'bg-gray-100 text-[#3B445C] hover:bg-gray-200'
              }`}
            >
              {opt.label}
            </button>
          ))}
        </div>
      </CardHeader>
      <CardContent className="p-0">
        <div className="overflow-x-auto">
          <table className="min-w-full divide-y divide-gray-200">
            <thead className="bg-gray-50">
              <tr>
                <th className="px-6 py-3 text-left text-xs font-medium text-[#3B445C] uppercase tracking-wider">Rank</th>
                <th className="px-6 py-3 text-left text-xs font-medium text-[#3B445C] uppercase tracking-wider">Technician</th>
                <th className="px-6 py-3 text-right text-xs font-medium text-[#3B445C] uppercase tracking-wider">Revenue</th>
                <th className="px-6 py-3 text-right text-xs font-medium text-[#3B445C] uppercase tracking-wider">Avg Ticket</th>
                <th className="px-6 py-3 text-right text-xs font-medium text-[#3B445C] uppercase tracking-wider">Conv. Rate</th>
                <th className="px-6 py-3 text-right text-xs font-medium text-[#3B445C] uppercase tracking-wider">Doors</th>
                <th className="px-6 py-3 text-right text-xs font-medium text-[#3B445C] uppercase tracking-wider">Reviews</th>
                <th className="px-6 py-3 text-right text-xs font-medium text-[#3B445C] uppercase tracking-wider">Commission</th>
              </tr>
            </thead>
            <tbody className="bg-white divide-y divide-gray-200">
              {sorted.map((tech, index) => (
                <tr
                  key={tech.id}
                  className={onTechClick ? 'cursor-pointer hover:bg-gray-50 transition-colors' : ''}
                  onClick={onTechClick ? () => onTechClick(tech.id) : undefined}
                >
                  <td className="px-6 py-4 whitespace-nowrap">
                    <span className={`inline-flex items-center justify-center w-7 h-7 rounded-full text-xs font-bold ${
                      index === 0 ? 'bg-[#FBBC03] text-[#012650]' :
                      index === 1 ? 'bg-gray-300 text-gray-700' :
                      index === 2 ? 'bg-amber-700/20 text-amber-800' :
                      'bg-gray-100 text-[#3B445C]'
                    }`}>
                      {index + 1}
                    </span>
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap">
                    <div className="flex items-center gap-3">
                      <div className="w-8 h-8 rounded-full bg-[#012650] flex items-center justify-center text-white text-xs font-medium">
                        {tech.fullName.split(' ').map(n => n[0]).join('')}
                      </div>
                      <span className="font-medium text-[#012650]">{tech.fullName}</span>
                    </div>
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap text-right font-mono font-medium text-[#012650]">
                    {formatCurrencyDollars(tech.totalRevenue)}
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap text-right font-mono text-[#3B445C]">
                    {formatCurrencyDollars(tech.avgTicket)}
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap text-right font-mono text-[#3B445C]">
                    {formatPercentage(tech.conversionRate)}
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap text-right font-mono text-[#3B445C]">
                    {tech.doorsInstalled}
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap text-right font-mono text-[#3B445C]">
                    {tech.fiveStarReviews}
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap text-right font-mono font-medium text-[#22C55E]">
                    {formatCurrencyDollars(tech.totalCommission)}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </CardContent>
    </Card>
  );
}

'use client';

import { type ReactNode } from 'react';

interface CardProps {
  children: ReactNode;
  className?: string;
  statusColor?: 'success' | 'warning' | 'danger' | null;
  onClick?: () => void;
}

const STATUS_BORDER_COLORS = {
  success: 'border-l-[#22C55E]',
  warning: 'border-l-[#F59E0B]',
  danger: 'border-l-[#EF4444]',
} as const;

export function Card({ children, className = '', statusColor, onClick }: CardProps) {
  const borderClass = statusColor ? `border-l-4 ${STATUS_BORDER_COLORS[statusColor]}` : '';
  const clickClass = onClick ? 'cursor-pointer hover:shadow-md transition-shadow' : '';

  return (
    <div
      className={`bg-white rounded-lg shadow-[0_1px_3px_rgba(0,0,0,0.1)] ${borderClass} ${clickClass} ${className}`}
      onClick={onClick}
      role={onClick ? 'button' : undefined}
      tabIndex={onClick ? 0 : undefined}
    >
      {children}
    </div>
  );
}

export function CardHeader({ children, className = '' }: { children: ReactNode; className?: string }) {
  return <div className={`px-6 py-4 border-b border-gray-100 ${className}`}>{children}</div>;
}

export function CardContent({ children, className = '' }: { children: ReactNode; className?: string }) {
  return <div className={`px-6 py-4 ${className}`}>{children}</div>;
}

export function CardTitle({ children, className = '' }: { children: ReactNode; className?: string }) {
  return <h3 className={`text-sm font-medium uppercase tracking-wider text-[#3B445C] ${className}`}>{children}</h3>;
}

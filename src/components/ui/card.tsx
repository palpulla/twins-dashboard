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
  const clickClass = onClick ? 'cursor-pointer hover:scale-[0.98] transition-all duration-200' : '';

  return (
    <div
      className={`bg-surface-container-lowest rounded-xl card-shadow ${borderClass} ${clickClass} ${className}`}
      onClick={onClick}
      role={onClick ? 'button' : undefined}
      tabIndex={onClick ? 0 : undefined}
    >
      {children}
    </div>
  );
}

export function CardHeader({ children, className = '' }: { children: ReactNode; className?: string }) {
  return <div className={`px-6 py-4 border-b border-surface-container ${className}`}>{children}</div>;
}

export function CardContent({ children, className = '' }: { children: ReactNode; className?: string }) {
  return <div className={`px-6 py-4 ${className}`}>{children}</div>;
}

export function CardTitle({ children, className = '' }: { children: ReactNode; className?: string }) {
  return <h3 className={`text-sm font-bold uppercase tracking-wider text-on-surface-variant ${className}`}>{children}</h3>;
}

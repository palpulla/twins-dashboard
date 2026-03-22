'use client';

import { type ReactNode } from 'react';

type BadgeVariant = 'default' | 'success' | 'warning' | 'danger' | 'info';

interface BadgeProps {
  variant?: BadgeVariant;
  children: ReactNode;
  className?: string;
}

const VARIANT_CLASSES: Record<BadgeVariant, string> = {
  default: 'bg-gray-100 text-[#3B445C]',
  success: 'bg-[#22C55E]/10 text-[#22C55E]',
  warning: 'bg-[#F59E0B]/10 text-[#F59E0B]',
  danger: 'bg-[#EF4444]/10 text-[#EF4444]',
  info: 'bg-[#012650]/10 text-[#012650]',
};

export function Badge({ variant = 'default', children, className = '' }: BadgeProps) {
  return (
    <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${VARIANT_CLASSES[variant]} ${className}`}>
      {children}
    </span>
  );
}

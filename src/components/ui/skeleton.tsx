'use client';

interface SkeletonProps {
  className?: string;
  width?: string | number;
  height?: string | number;
}

export function Skeleton({ className = '', width, height }: SkeletonProps) {
  return (
    <div
      className={`skeleton ${className}`}
      style={{ width, height }}
    />
  );
}

export function KpiCardSkeleton() {
  return (
    <div className="bg-white rounded-lg shadow-[0_1px_3px_rgba(0,0,0,0.1)] border-l-4 border-l-gray-200 p-6">
      <Skeleton className="mb-2" width="60%" height={14} />
      <Skeleton className="mb-3" width="40%" height={36} />
      <div className="flex items-center justify-between">
        <Skeleton width="30%" height={12} />
        <Skeleton width={60} height={24} />
      </div>
    </div>
  );
}

export function TableSkeleton({ rows = 5 }: { rows?: number }) {
  return (
    <div className="space-y-3">
      <Skeleton width="100%" height={40} />
      {Array.from({ length: rows }).map((_, i) => (
        <Skeleton key={i} width="100%" height={48} />
      ))}
    </div>
  );
}

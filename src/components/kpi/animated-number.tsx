'use client';

import { useEffect, useRef, useState } from 'react';
import { formatKpiValue } from '@/lib/utils/format';
import type { DisplayFormat } from '@/types/kpi';

interface AnimatedNumberProps {
  value: number;
  format: DisplayFormat;
  duration?: number;
  className?: string;
}

export function AnimatedNumber({ value, format, duration = 800, className = '' }: AnimatedNumberProps) {
  const [displayValue, setDisplayValue] = useState(0);
  const prevValue = useRef(0);
  const animationRef = useRef<number>(0);

  useEffect(() => {
    const start = prevValue.current;
    const end = value;
    const startTime = performance.now();

    const animate = (currentTime: number) => {
      const elapsed = currentTime - startTime;
      const progress = Math.min(elapsed / duration, 1);
      // Ease-out cubic
      const eased = 1 - Math.pow(1 - progress, 3);
      const current = start + (end - start) * eased;

      setDisplayValue(current);

      if (progress < 1) {
        animationRef.current = requestAnimationFrame(animate);
      } else {
        prevValue.current = end;
      }
    };

    animationRef.current = requestAnimationFrame(animate);

    return () => {
      if (animationRef.current) {
        cancelAnimationFrame(animationRef.current);
      }
    };
  }, [value, duration]);

  return (
    <span className={`font-mono font-bold animate-count-up ${className}`}>
      {formatKpiValue(displayValue, format)}
    </span>
  );
}

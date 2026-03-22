export const colors = {
  primary: '#012650',
  secondary: '#FBBC03',
  accent: '#FFBA41',
  darkAccent: '#3B445C',
  background: '#F5F6FA',
  surface: '#FFFFFF',
  success: '#22C55E',
  warning: '#F59E0B',
  danger: '#EF4444',
} as const;

export const fonts = {
  sans: 'Inter, system-ui, -apple-system, sans-serif',
  mono: 'JetBrains Mono, ui-monospace, monospace',
} as const;

export const statusColor = (value: number, target: number): keyof typeof colors => {
  if (target === 0) return 'success';
  const ratio = value / target;
  if (ratio >= 1) return 'success';
  if (ratio >= 0.9) return 'warning';
  return 'danger';
};

export const statusColorInverse = (value: number, target: number): keyof typeof colors => {
  if (target === 0) return 'success';
  const ratio = value / target;
  if (ratio <= 1) return 'success';
  if (ratio <= 1.1) return 'warning';
  return 'danger';
};

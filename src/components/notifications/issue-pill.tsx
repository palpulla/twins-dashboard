type Variant = 'red' | 'amber' | 'orange'
const STYLES: Record<Variant, string> = {
  red: 'bg-red-100 text-red-800',
  amber: 'bg-amber-100 text-amber-800',
  orange: 'bg-orange-100 text-orange-800',
}

export function IssuePill({ label, variant = 'red' }: { label: string; variant?: Variant }) {
  return (
    <span className={`inline-flex items-center text-[11px] font-semibold uppercase tracking-wide px-2 py-0.5 rounded-full ${STYLES[variant]}`}>
      {label}
    </span>
  )
}

import { Link } from 'react-router-dom'
import Icon from './Icon'

const tones = {
  ink: 'bg-ink text-paper hover:bg-stone-800',
  accent: 'bg-accent text-white hover:bg-orange-900',
  ghost: 'bg-white text-ink border border-line hover:bg-paper-2',
  danger: 'bg-red-700 text-white hover:bg-red-800',
}

export function Button({
  as = 'button',
  tone = 'ink',
  type = 'button',
  className = '',
  children,
  ...props
}) {
  const classes = `inline-flex items-center justify-center gap-2 rounded-sm px-4 py-2.5 text-sm font-medium transition disabled:cursor-not-allowed disabled:opacity-50 ${tones[tone]} ${className}`
  if (as === 'link') {
    return (
      <Link className={classes} {...props}>
        {children}
      </Link>
    )
  }
  return (
    <button type={type} className={classes} {...props}>
      {children}
    </button>
  )
}

export function Field({ label, error, hint, children }) {
  return (
    <label className="block">
      <span className="mb-1.5 block text-sm font-medium text-ink-soft">{label}</span>
      {children}
      {error ? (
        <span className="mt-1 block text-sm text-red-700">{error}</span>
      ) : hint ? (
        <span className="mt-1 block text-xs text-muted">{hint}</span>
      ) : null}
    </label>
  )
}

export const inputClass =
  'w-full rounded-sm border border-line bg-white px-3 py-2.5 text-sm text-ink outline-none transition placeholder:text-stone-400 focus:border-stone-400'

export function Alert({ children, tone = 'error' }) {
  const palette =
    tone === 'error'
      ? 'border-red-200 bg-red-50 text-red-800'
      : 'border-emerald-200 bg-emerald-50 text-emerald-800'
  return (
    <div className={`flex items-start gap-2 rounded-sm border px-3 py-2.5 text-sm ${palette}`}>
      <Icon name={tone === 'error' ? 'alert' : 'check'} className="mt-0.5 h-4 w-4 shrink-0" />
      <span>{children}</span>
    </div>
  )
}

export function Empty({ title, body, action }) {
  return (
    <div className="rounded-sm border border-dashed border-line bg-white px-6 py-14 text-center">
      <p className="font-display text-xl text-ink">{title}</p>
      {body ? <p className="mx-auto mt-2 max-w-md text-sm text-muted">{body}</p> : null}
      {action ? <div className="mt-5">{action}</div> : null}
    </div>
  )
}

export function Spinner({ label = 'Memuat' }) {
  return (
    <div className="flex items-center justify-center gap-3 py-16 text-sm text-muted" role="status">
      <span className="h-4 w-4 animate-spin rounded-full border border-stone-300 border-t-ink" />
      {label}
    </div>
  )
}

export function StatusPill({ status, label }) {
  const map = {
    published: 'bg-emerald-50 text-emerald-800',
    active: 'bg-emerald-50 text-emerald-800',
    paid: 'bg-emerald-50 text-emerald-800',
    completed: 'bg-emerald-50 text-emerald-800',
    pending: 'bg-amber-50 text-amber-800',
    pending_payment: 'bg-amber-50 text-amber-800',
    processing: 'bg-amber-50 text-amber-800',
    draft: 'bg-stone-100 text-stone-600',
    rejected: 'bg-red-50 text-red-800',
    failed: 'bg-red-50 text-red-800',
    cancelled: 'bg-red-50 text-red-800',
    expired: 'bg-stone-100 text-stone-600',
    refunded: 'bg-stone-100 text-stone-700',
    suspended: 'bg-red-50 text-red-800',
    archived: 'bg-stone-100 text-stone-600',
  }
  return (
    <span className={`inline-block rounded-sm px-2 py-0.5 text-xs font-medium ${map[status] ?? 'bg-stone-100 text-stone-600'}`}>
      {label ?? status}
    </span>
  )
}

export function Price({ value, compare, className = '' }) {
  return (
    <span className={`inline-flex items-baseline gap-2 ${className}`}>
      <span className="tabular-nums">{value}</span>
      {compare ? (
        <span className="text-xs text-muted line-through tabular-nums">{compare}</span>
      ) : null}
    </span>
  )
}

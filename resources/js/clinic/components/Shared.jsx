import React, { useEffect, useRef, useState } from 'react'
import { Link, NavLink, useLocation } from 'react-router-dom'
import {
  Phone, Menu, MapPin, ChevronDown, ChevronLeft, ChevronRight, Star, ArrowRight, X, Search, Globe, Check, Mail,
  Instagram, Music2, Ghost,
  Sparkles, MessageCircle, Flower2, Stethoscope, Syringe, Zap, Activity, Scissors, Eye, Droplets, Hourglass,
  Crown, Apple, Waves, Camera, FlaskConical
} from 'lucide-react'
import { GULF_CODES, getLocale } from '../api'
import { CLINIC, S, tr } from '../brand'

/**
 * Language toggle. Hits the server route /language/{locale} (sets session lang
 * + returns back), so the whole page reloads in the new locale + direction.
 * Must be a real <a> (full nav), not a router Link.
 */
export function LangSwitcher({ solid = true, className = '' }) {
  const loc = getLocale()
  const target = loc === 'ar' ? 'en' : 'ar'
  const label = loc === 'ar' ? 'EN' : 'العربية'
  return (
    <a
      href={`/language/${target}`}
      aria-label={loc === 'ar' ? 'Switch to English' : 'التبديل إلى العربية'}
      className={`inline-flex items-center gap-1.5 px-2 py-1.5 text-[12px] font-semibold uppercase tracking-[0.16em] transition-colors ${solid ? 'text-mauve hover:text-plum' : 'text-ivory/70 hover:text-ivory'} ${className}`}
    >
      <Globe size={13} strokeWidth={1.8} /> {label}
    </a>
  )
}

// --- Atoms ---

export function Container({ children, className = '' }) {
  return <div className={`max-w-[1240px] mx-auto px-5 sm:px-8 lg:px-12 ${className}`}>{children}</div>
}

/**
 * Small-caps label, prefixed with a short rose rule. The rule is the section
 * mark throughout the site — deliberately not a numeral, which reads as a
 * technical index rather than a beauty-counter label.
 */
export function Eyebrow({ children, tone = 'dark', className = '' }) {
  const c = tone === 'light' ? 'text-ivory/60' : 'text-mauve'
  return (
    <div className={`flex items-center gap-3 text-[11px] font-medium uppercase tracking-[0.24em] ${c} ${className}`}>
      <span className="h-px w-7 bg-rose shrink-0" aria-hidden="true" />
      <span>{children}</span>
    </div>
  )
}

/* ---------------------------------------------------------------------------
 * Treatment-category icons.
 *
 * The `icon` column used to hold an emoji, which rendered as a different
 * (often cartoonish) glyph on every OS — 🍔/💃/👰 next to a KD 300 laser
 * course. It now holds a semantic key, drawn here as a line icon in the site
 * palette. Unknown/legacy values (including any leftover emoji) fall through
 * to the slug + name keyword rules, so nothing has to be backfilled for a
 * category to get a sensible mark.
 * ------------------------------------------------------------------------ */
const ICON_BY_KEY = {
  consultation: MessageCircle,
  facial: Flower2,
  derm: Stethoscope,
  injectable: Syringe,
  laser: Zap,
  body: Activity,
  hair: Scissors,
  lashes: Eye,
  skin: Sparkles,
  iv: Droplets,
  antiaging: Hourglass,
  bridal: Crown,
  nutrition: Apple,
  massage: Waves,
  imaging: Camera,
  lab: FlaskConical,
}

// Matched against the slug and both name translations, first hit wins.
const ICON_RULES = [
  [/consult|استشار/i, MessageCircle],
  [/laser|ليزر/i, Zap],
  [/inject|filler|botox|حقن|فيلر/i, Syringe],
  [/lash|brow|رموش|حواجب/i, Eye],
  [/hair|شعر/i, Scissors],
  [/facial|skincare|بشرة|عناية/i, Flower2],
  [/derma|جلد/i, Stethoscope],
  [/contour|slim|body|نحت|تنحيف/i, Activity],
  [/massage|مساج|تدليك/i, Waves],
  [/drip|iv|wellness|مغذي|عافية/i, Droplets],
  [/aging|شيخوخ/i, Hourglass],
  [/bridal|wedding|عروس|مناسب/i, Crown],
  [/nutrition|diet|تغذية/i, Apple],
  [/imaging|visia|scan|تصوير/i, Camera],
  [/lab|diagnos|مختبر|تحاليل/i, FlaskConical],
]

export function serviceIconFor(service = {}) {
  const byKey = ICON_BY_KEY[String(service.icon || '').toLowerCase().trim()]
  if (byKey) return byKey

  const name = service.name
  const haystack = [service.slug, typeof name === 'string' ? name : `${name?.en || ''} ${name?.ar || ''}`]
    .filter(Boolean).join(' ')

  return (ICON_RULES.find(([re]) => re.test(haystack)) || [])[1] || Sparkles
}

/** Line icon for a treatment category. */
export function ServiceIcon({ service, size = 26, className = '' }) {
  const Icon = serviceIconFor(service)
  return <Icon size={size} strokeWidth={1.25} className={className} aria-hidden="true" />
}

/* ---------------------------------------------------------------------------
 * "Book Now" — the one CTA that has to land on the booking form itself.
 *
 * It used to point at /clinic/clinics (the browse list), so the primary button
 * on the site never actually opened a booking. The widget lives in the landing
 * hero under #book, so every Book Now link goes there and scrolls to it.
 * ------------------------------------------------------------------------ */
export const BOOKING_ANCHOR = 'book'

export function scrollToBooking() {
  // We may have just triggered a route change, so poll briefly until the
  // widget has mounted rather than assuming it is already in the DOM.
  let tries = 0
  const tick = () => {
    const el = document.getElementById(BOOKING_ANCHOR)
    if (el) {
      requestAnimationFrame(() => el.scrollIntoView({ behavior: 'smooth', block: 'start' }))
      return
    }
    if (tries++ < 25) setTimeout(tick, 60)
  }
  tick()
}

/**
 * @param context optional { partner, branch, doctor } ids already chosen by
 *                the visitor — the widget opens on the first step still to
 *                answer instead of restarting the whole flow.
 */
export function BookNowLink({ children, className = '', onClick, context = null, ...rest }) {
  const qs = new URLSearchParams(
    Object.entries(context || {}).filter(([, v]) => v != null && v !== '')
  ).toString()

  return (
    <Link
      to={qs ? `/clinic/book?${qs}` : '/clinic/book'}
      onClick={(e) => { onClick?.(e); scrollToBooking() }}
      {...rest}
      className={className}
    >
      {children}
    </Link>
  )
}

/**
 * Two-letter monogram from a name. Used wherever artwork is missing so the
 * fallback reads as a designed mark instead of a broken asset. Strips doctor
 * honorifics and parenthesised suffixes ("(6 sessions)") first, and indexes
 * by code point so Arabic and accented names don't get sliced mid-character.
 */
const GENERIC_WORD = /^(باقة|باقه|دورة|دوره|جلسة|جلسات|عرض|package|packages|course|session|sessions|bundle|offer|treatment)$/i

export function monogramOf(name = '', max = 2) {
  const cleaned = String(name)
    .replace(/\([^)]*\)/g, ' ')
    .replace(/^\s*(dr\.?|prof\.?|د\.?|الدكتورة|الدكتور)\s+/i, ' ')
    .replace(/[–—\-_/,.&]+/g, ' ')
    .trim()

  const words = cleaned.split(/\s+/)
    .filter(w => [...w].length > 1)
    // "Package"/"باقة" leads most names — a monogram built from it says nothing.
    .filter(w => !GENERIC_WORD.test(w))
    // Arabic: strip the definite article, otherwise almost every initial is "ا"
    // and every package collapses to the same two letters.
    .map(w => (/^ال./.test(w) && [...w].length > 3 ? w.slice(2) : w))

  if (!words.length) return '—'
  return words.slice(0, max).map(w => [...w][0]).join('').toUpperCase()
}

/** Hairline rule. The workhorse separator between sections. */
export function Rule({ tone = 'dark', className = '' }) {
  return <div className={`h-px w-full ${tone === 'light' ? 'bg-ivory/15' : 'bg-line'} ${className}`} />
}

/**
 * Underline-on-hover text link. Uses a scaling pseudo-border rather than
 * text-decoration so the rule animates from the leading edge in both
 * directions (LTR and RTL).
 */
export function TextLink({ children, className = '' }) {
  return (
    <span className={`relative inline-flex items-center gap-2 group/link ${className}`}>
      {children}
      <span className="absolute -bottom-1 start-0 h-px w-full bg-current origin-left rtl:origin-right scale-x-0 group-hover/link:scale-x-100 group-hover:scale-x-100 transition-transform duration-300" />
    </span>
  )
}

/**
 * Primary action. Solid aubergine pill that warms to rose on hover.
 * `tone="light"` inverts it for use on the dark concierge band.
 */
export function Action({ as: As = 'span', children, className = '', tone = 'dark', ...rest }) {
  const base = tone === 'light'
    ? 'bg-ivory text-plum hover:bg-white'
    : 'bg-plum text-ivory hover:bg-rose-deep'
  return (
    <As
      {...rest}
      className={`group inline-flex items-center justify-center gap-2.5 rounded-full px-8 py-3.5 text-sm font-medium tracking-[0.04em] transition-colors duration-300 ${base} ${className}`}
    >
      {children}
    </As>
  )
}

/** Ghost action — hairline box, fills on hover. */
export function GhostAction({ as: As = 'span', children, className = '', tone = 'dark', ...rest }) {
  const base = tone === 'light'
    ? 'border-ivory/25 text-ivory hover:bg-ivory hover:text-plum'
    : 'border-plum/20 text-plum hover:bg-plum hover:text-ivory'
  return (
    <As
      {...rest}
      className={`group inline-flex items-center justify-center gap-2.5 rounded-full border px-8 py-3.5 text-sm font-medium tracking-[0.04em] transition-colors duration-300 ${base} ${className}`}
    >
      {children}
    </As>
  )
}

/**
 * Fade-and-rise on first scroll into view. Deliberately subtle — one shot,
 * no parallax. Honours prefers-reduced-motion via the .reveal CSS in the
 * blade shell.
 */
export function Reveal({ children, delay = 0, className = '' }) {
  const ref = useRef(null)
  const [shown, setShown] = useState(false)

  useEffect(() => {
    const el = ref.current
    if (!el || typeof IntersectionObserver === 'undefined') { setShown(true); return }

    const io = new IntersectionObserver(
      ([e]) => { if (e.isIntersecting) { setShown(true); io.disconnect() } },
      { rootMargin: '0px 0px -10% 0px' }
    )
    io.observe(el)

    // Fail-safe: content must never be permanently invisible. If the observer
    // hasn't fired by now (print, headless capture, odd scroll container,
    // pre-hydration scroll position) just show it.
    const t = setTimeout(() => { setShown(true); io.disconnect() }, 1200)

    return () => { clearTimeout(t); io.disconnect() }
  }, [])

  return (
    <div
      ref={ref}
      className={`reveal transition-all duration-[900ms] ease-out ${shown ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-5'} ${className}`}
      style={{ transitionDelay: `${delay}ms` }}
    >
      {children}
    </div>
  )
}

/** Section header: eyebrow + serif display title + optional lede. */
export function SectionHeading({ kicker, title, subtitle, align = 'start', tone = 'dark', className = '' }) {
  const centered = align === 'center'
  return (
    <div className={`${centered ? 'text-center max-w-2xl mx-auto' : 'max-w-2xl'} ${className}`}>
      {kicker && <Eyebrow tone={tone} className={centered ? 'justify-center' : ''}>{kicker}</Eyebrow>}
      <h2 className={`font-display text-[clamp(2.4rem,5.5vw,4rem)] leading-[1.04] mt-5 ${tone === 'light' ? 'text-ivory' : 'text-plum'}`}>
        {title}
      </h2>
      {subtitle && (
        <p className={`mt-5 text-base md:text-lg leading-relaxed ${tone === 'light' ? 'text-ivory/65' : 'text-mauve'}`}>
          {subtitle}
        </p>
      )}
    </div>
  )
}

export function PageShell({ title, subtitle, children }) {
  return (
    <div className="pt-40 pb-24 bg-ivory min-h-screen">
      <Container>
        <div className="mb-16">
          <h1 className="font-display text-[clamp(2.6rem,6vw,4.5rem)] leading-[1.03] text-plum">{title}</h1>
          {subtitle && <p className="mt-5 max-w-2xl text-lg text-mauve leading-relaxed">{subtitle}</p>}
        </div>
        {children}
      </Container>
    </div>
  )
}

export function Pill({ children }) {
  return (
    <span className="inline-flex items-center rounded-full border border-line bg-petal/50 px-3.5 py-1 text-[11px] font-medium uppercase tracking-[0.14em] text-mauve">
      {children}
    </span>
  )
}

export function StarRating({ avg = 0, count = 0 }) {
  const a = Number(avg || 0)
  return (
    <div className="flex items-center gap-2 text-sm">
      <span className="inline-flex items-center gap-1.5 font-semibold text-plum">
        <Star size={13} fill="currentColor" className="text-rose" />
        <span className="tabular-nums">{a.toFixed(1)}</span>
      </span>
      <span className="text-mauve/70 tabular-nums">({count})</span>
    </div>
  )
}

/**
 * Brand lockup. Wordmark-led rather than icon-led: the name is set in the
 * display serif, with the discipline line as a small-caps subtitle. Only
 * falls back to a mark when a logo image is configured.
 */
export function BrandLogo({ light = false }) {
  return (
    <Link to="/clinic/book" className="flex items-center gap-3 group shrink-0">
      {CLINIC.logo && (
        <img src={CLINIC.logo} alt="" className="w-10 h-10 object-cover" />
      )}
      <span className="leading-none">
        <span className={`block font-display text-[26px] leading-none transition-colors ${light ? 'text-ivory' : 'text-plum'}`}>
          {tr(CLINIC.name)}
        </span>
        <span className={`block text-[9px] font-semibold uppercase tracking-[0.3em] mt-1.5 ${light ? 'text-ivory/50' : 'text-mauve/70'}`}>
          {tr(CLINIC.kicker)}
        </span>
      </span>
    </Link>
  )
}

/* ---------------------------------------------------------------------------
 * Calendar
 *
 * Replaces <input type="date">. The native control renders the browser's own
 * calendar — system font, blue selection, English weekday names even in Arabic
 * — and none of it is styleable, so on the booking step it was the one piece
 * of UI that looked like a different website.
 *
 * Values are plain 'YYYY-MM-DD' strings (what the booking API expects) and all
 * arithmetic is local-time: toISOString() would shift the date across the UTC
 * boundary and offer yesterday as a bookable day in Kuwait.
 * ------------------------------------------------------------------------ */
export function toDateValue(d) {
  const p = (n) => String(n).padStart(2, '0')
  return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())}`
}

function parseDateValue(v) {
  const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(String(v || ''))
  return m ? new Date(+m[1], +m[2] - 1, +m[3]) : null
}

const startOfDay = (d) => new Date(d.getFullYear(), d.getMonth(), d.getDate())

/**
 * @param value      'YYYY-MM-DD' | ''
 * @param onChange   (value: string) => void
 * @param maxDays    how far ahead booking is allowed (default 90)
 */
export function Calendar({ value, onChange, maxDays = 90 }) {
  const locale = getLocale() === 'ar' ? 'ar-KW-u-nu-latn' : 'en-GB'
  const today = startOfDay(new Date())
  const last = new Date(today.getFullYear(), today.getMonth(), today.getDate() + maxDays)
  const selected = parseDateValue(value)

  const [cursor, setCursor] = useState(() => {
    const base = selected || today
    return new Date(base.getFullYear(), base.getMonth(), 1)
  })

  // Follow the selection when it is set from outside (e.g. going back a step).
  useEffect(() => {
    if (selected) setCursor(new Date(selected.getFullYear(), selected.getMonth(), 1))
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [value])

  const monthLabel = new Intl.DateTimeFormat(locale, { month: 'long', year: 'numeric' }).format(cursor)

  // Weekday labels, generated from a known Sunday so the row always matches
  // the grid. Arabic's "short" form is the full word (الأربعاء), which will not
  // fit a 7-column grid on a phone, so it gets a hand-written short list.
  const weekdays = getLocale() === 'ar'
    ? ['أحد', 'إثنين', 'ثلاثاء', 'أربعاء', 'خميس', 'جمعة', 'سبت']
    : Array.from({ length: 7 }, (_, i) =>
        new Intl.DateTimeFormat(locale, { weekday: 'short' }).format(new Date(2024, 8, 1 + i))
      )

  const firstOfMonth = new Date(cursor.getFullYear(), cursor.getMonth(), 1)
  const daysInMonth = new Date(cursor.getFullYear(), cursor.getMonth() + 1, 0).getDate()
  const cells = [
    ...Array.from({ length: firstOfMonth.getDay() }, () => null),
    ...Array.from({ length: daysInMonth }, (_, i) => new Date(cursor.getFullYear(), cursor.getMonth(), i + 1)),
  ]

  const prevDisabled = cursor <= new Date(today.getFullYear(), today.getMonth(), 1)
  const nextDisabled = new Date(cursor.getFullYear(), cursor.getMonth() + 1, 1) > last
  const step = (n) => setCursor(c => new Date(c.getFullYear(), c.getMonth() + n, 1))

  const navBtn = 'w-9 h-9 rounded-full flex items-center justify-center border border-line text-mauve transition-colors hover:border-rose hover:text-rose-deep disabled:opacity-30 disabled:pointer-events-none'

  return (
    <div className="bg-white rounded-2xl border border-line p-4">
      <div className="flex items-center justify-between mb-4">
        <button type="button" onClick={() => step(-1)} disabled={prevDisabled} aria-label="Previous month" className={navBtn}>
          <ChevronLeft size={17} strokeWidth={1.8} className="rtl:rotate-180" />
        </button>
        <div className="font-display text-2xl text-plum leading-none">{monthLabel}</div>
        <button type="button" onClick={() => step(1)} disabled={nextDisabled} aria-label="Next month" className={navBtn}>
          <ChevronRight size={17} strokeWidth={1.8} className="rtl:rotate-180" />
        </button>
      </div>

      <div className="grid grid-cols-7 gap-1 mb-1">
        {weekdays.map((w, i) => (
          <div key={i} className="h-8 flex items-center justify-center text-[10px] font-medium uppercase tracking-[0.12em] text-mauve/60">
            {w}
          </div>
        ))}
      </div>

      <div className="grid grid-cols-7 gap-1">
        {cells.map((d, i) => {
          if (!d) return <div key={`x${i}`} />

          const v = toDateValue(d)
          const isSelected = v === value
          const isToday = v === toDateValue(today)
          const disabled = d < today || d > last

          return (
            <button
              key={v}
              type="button"
              disabled={disabled}
              onClick={() => onChange(v)}
              aria-pressed={isSelected}
              className={`h-10 rounded-full text-sm tabular-nums transition-colors duration-200 ${
                disabled
                  ? 'text-mauve/25 cursor-not-allowed'
                  : isSelected
                    ? 'bg-plum text-ivory font-medium'
                    : isToday
                      ? 'text-rose-deep font-medium ring-1 ring-rose/50 hover:bg-blush'
                      : 'text-plum hover:bg-blush'
              }`}
              dir="ltr"
            >
              {d.getDate()}
            </button>
          )
        })}
      </div>
    </div>
  )
}

/**
 * Styled, optionally-searchable dropdown. Drop-in replacement for native
 * <select>. options = [{ value, label }]. Closes on outside-click / Escape.
 */
export function SearchableSelect({ value, onChange, options = [], placeholder = '', icon = null, searchable = true }) {
  const [open, setOpen] = useState(false)
  const [q, setQ] = useState('')
  const ref = useRef(null)
  const inputRef = useRef(null)

  useEffect(() => {
    const onDoc = (e) => { if (ref.current && !ref.current.contains(e.target)) setOpen(false) }
    const onKey = (e) => { if (e.key === 'Escape') setOpen(false) }
    document.addEventListener('mousedown', onDoc)
    document.addEventListener('keydown', onKey)
    return () => { document.removeEventListener('mousedown', onDoc); document.removeEventListener('keydown', onKey) }
  }, [])

  useEffect(() => { if (open && searchable) setTimeout(() => inputRef.current?.focus(), 30) }, [open, searchable])

  const selected = options.find(o => String(o.value) === String(value))
  const needle = q.trim().toLowerCase()
  const filtered = needle ? options.filter(o => String(o.label).toLowerCase().includes(needle)) : options

  return (
    <div className="relative" ref={ref}>
      <button
        type="button"
        onClick={() => setOpen(o => !o)}
        className={`w-full h-14 bg-white border rounded-2xl px-5 flex items-center gap-2.5 font-medium text-start transition-colors outline-none ${open ? 'border-rose-deep' : 'border-line hover:border-rose'}`}
      >
        {icon && <span className="text-rose shrink-0">{icon}</span>}
        <span className={`flex-1 truncate ${selected ? 'text-plum' : 'text-mauve/60'}`}>
          {selected ? selected.label : placeholder}
        </span>
        <ChevronDown size={16} strokeWidth={1.6} className={`text-mauve shrink-0 transition-transform duration-300 ${open ? 'rotate-180' : ''}`} />
      </button>

      {open && (
        <div className="absolute z-50 mt-2 w-full bg-white border border-line rounded-2xl shadow-[0_18px_40px_-24px_rgba(42,20,32,0.45)] overflow-hidden animate-in fade-in slide-in-from-top-1 duration-150">
          {searchable && (
            <div className="p-2 border-b border-line">
              <div className="relative">
                <Search size={15} strokeWidth={1.6} className="absolute top-1/2 -translate-y-1/2 start-3 text-mauve/60" />
                <input
                  ref={inputRef}
                  value={q}
                  onChange={e => setQ(e.target.value)}
                  placeholder={tr(S.select.search)}
                  className="w-full h-10 bg-ivory border border-line rounded-full ps-9 pe-3 text-sm outline-none focus:border-rose-deep transition-colors"
                />
              </div>
            </div>
          )}
          <div className="max-h-64 overflow-y-auto">
            {filtered.length === 0 && (
              <div className="px-4 py-6 text-center text-sm text-mauve/70">{tr(S.select.none)}</div>
            )}
            {filtered.map(o => {
              const isSel = String(o.value) === String(value)
              return (
                <button
                  type="button"
                  key={`${o.value}`}
                  onClick={() => { onChange(o.value); setOpen(false); setQ('') }}
                  className={`w-full text-start px-4 py-3 flex items-center gap-2 text-sm transition-colors ${isSel ? 'bg-petal/60 text-plum font-semibold' : 'text-mauve hover:bg-ivory'}`}
                >
                  <span className="flex-1 truncate">{o.label}</span>
                  {isSel && <Check size={15} strokeWidth={2} className="text-rose-deep shrink-0" />}
                </button>
              )
            })}
          </div>
        </div>
      )}
    </div>
  )
}

export function PhoneInput({ value, onChange, code, onCodeChange }) {
  // The country code used to be a transparent native <select> layered over the
  // flag: tapping it opened the OS picker, the one control on the form that
  // belonged to the browser rather than to us. This is our own popover.
  const [open, setOpen] = useState(false)
  const ref = useRef(null)

  useEffect(() => {
    const onDoc = (e) => { if (ref.current && !ref.current.contains(e.target)) setOpen(false) }
    const onKey = (e) => { if (e.key === 'Escape') setOpen(false) }
    document.addEventListener('mousedown', onDoc)
    document.addEventListener('keydown', onKey)
    return () => { document.removeEventListener('mousedown', onDoc); document.removeEventListener('keydown', onKey) }
  }, [])

  return (
    <div className="flex bg-white border border-line rounded-2xl focus-within:border-rose-deep transition-colors h-14">
      <div className="relative" ref={ref}>
        <button
          type="button"
          onClick={() => setOpen(o => !o)}
          aria-expanded={open}
          aria-label="Country code"
          className="h-full flex items-center bg-ivory rounded-s-2xl px-4 hover:bg-petal/60 transition-colors group border-e border-line min-w-[112px]"
        >
          <span className="text-lg me-2">{GULF_CODES.find(c => c.code === code)?.flag}</span>
          <span className="text-sm font-semibold text-plum tabular-nums" dir="ltr">{code}</span>
          <ChevronDown size={13} strokeWidth={1.6} className={`text-mauve/60 group-hover:text-plum ms-auto transition-transform duration-200 ${open ? 'rotate-180' : ''}`} />
        </button>

        {open && (
          // Opens upward: the phone field sits at the bottom of the booking
          // card, which clips its own overflow, so a downward menu is invisible.
          <div className="absolute z-50 bottom-full mb-2 start-0 min-w-[240px] max-h-64 overflow-y-auto custom-scrollbar bg-white border border-line rounded-2xl shadow-[0_18px_40px_-24px_rgba(42,20,32,0.45)] animate-in fade-in slide-in-from-bottom-1 duration-150">
            {GULF_CODES.map(c => {
              const isSel = c.code === code
              return (
                <button
                  key={c.code}
                  type="button"
                  onClick={() => { onCodeChange(c.code); setOpen(false) }}
                  className={`w-full text-start px-4 py-3 flex items-center gap-3 text-sm transition-colors ${isSel ? 'bg-petal/60 text-plum font-semibold' : 'text-mauve hover:bg-blush'}`}
                >
                  <span className="text-lg">{c.flag}</span>
                  <span className="flex-1 truncate">{c.name}</span>
                  <span className="tabular-nums text-mauve/70" dir="ltr">{c.code}</span>
                  {isSel && <Check size={15} strokeWidth={2} className="text-rose-deep shrink-0" />}
                </button>
              )
            })}
          </div>
        )}
      </div>
      <input
        type="tel"
        inputMode="numeric"
        value={value}
        onChange={onChange}
        placeholder="5000 0000"
        dir="ltr"
        className="flex-1 w-full bg-transparent px-4 outline-none font-medium text-plum placeholder:text-mauve/40 text-lg tabular-nums"
      />
    </div>
  )
}

// --- Layout ---

export function Navbar() {
  const [scrolled, setScrolled] = useState(false)
  const [mobileMenuOpen, setMobileMenuOpen] = useState(false)
  const location = useLocation();

  useEffect(() => {
    const handler = () => setScrolled(window.scrollY > 20)
    window.addEventListener('scroll', handler)
    return () => window.removeEventListener('scroll', handler)
  }, [])

  useEffect(() => { setMobileMenuOpen(false) }, [location.pathname])

  // The hero is a light ivory canvas, so the bar stays dark-on-light throughout.
  // `solid` only drives the backdrop + density change once you scroll off it.
  const solid = scrolled || mobileMenuOpen

  const linkTone = 'text-mauve hover:text-plum'
  const linkClass = ({ isActive }) =>
    `text-[12px] font-semibold uppercase tracking-[0.16em] transition-colors ${linkTone} ${isActive ? '!text-plum' : ''}`

  return (
    <>
      <nav className={`fixed top-0 left-0 right-0 z-50 transition-all duration-500 ${solid ? 'bg-ivory/92 backdrop-blur-md py-4 border-b border-line' : 'bg-transparent py-7 border-b border-transparent'}`}>
        <Container className="flex items-center justify-between gap-6">
          <BrandLogo />

          <div className="hidden md:flex items-center gap-9">
            <NavLink to="/clinic/clinics" className={linkClass}>{tr(S.nav.clinics)}</NavLink>
            <NavLink to="/clinic/services" className={linkClass}>{tr(S.nav.services)}</NavLink>
            <NavLink to="/clinic/gallery" className={linkClass}>{tr(S.nav.results)}</NavLink>
            <NavLink to="/clinic/offers" className={linkClass}>
              <span className="inline-flex items-center gap-2">
                {tr(S.nav.offers)}
                <span className="w-1 h-1 rounded-full bg-rose" aria-hidden="true" />
              </span>
            </NavLink>
            <a href="#contact" className={`text-[12px] font-semibold uppercase tracking-[0.16em] transition-colors ${linkTone}`}>
              {tr(S.nav.contact)}
            </a>
          </div>

          <div className="flex items-center gap-3 sm:gap-5">
            <LangSwitcher />

            {/* The phone is a secondary link, never the primary CTA — on a
                desktop a tel: button just looks broken. */}
            {CLINIC.phone && (
              <a
                href={`tel:${CLINIC.phone}`}
                dir="ltr"
                className="hidden lg:inline-flex items-center gap-2 text-[12px] font-medium tracking-[0.08em] text-mauve hover:text-plum transition-colors tabular-nums"
              >
                <Phone size={13} strokeWidth={1.8} /> {CLINIC.phone}
              </a>
            )}

            <BookNowLink className="hidden md:inline-flex items-center gap-2 rounded-full px-6 py-3 text-[12px] font-medium uppercase tracking-[0.16em] bg-plum text-ivory hover:bg-rose-deep transition-colors duration-300">
              {tr(S.nav.bookNow)} <ArrowRight size={13} strokeWidth={1.8} className="rtl:rotate-180" />
            </BookNowLink>
            <button
              onClick={() => setMobileMenuOpen(!mobileMenuOpen)}
              aria-label="Menu"
              aria-expanded={mobileMenuOpen}
              className="md:hidden p-1.5 text-plum transition-colors"
            >
              {mobileMenuOpen ? <X size={22} strokeWidth={1.6} /> : <Menu size={22} strokeWidth={1.6} />}
            </button>
          </div>
        </Container>
      </nav>

      {mobileMenuOpen && (
        <div className="fixed inset-0 z-40 bg-ivory pt-28 px-6 md:hidden animate-in fade-in duration-200">
          <div className="flex flex-col">
            {[
              { to: '/clinic/clinics', label: tr(S.nav.clinics), dot: false },
              { to: '/clinic/services', label: tr(S.nav.services), dot: false },
              { to: '/clinic/gallery', label: tr(S.nav.results), dot: false },
              { to: '/clinic/offers', label: tr(S.nav.offers), dot: true },
            ].map((l, i) => (
              <NavLink key={l.to} to={l.to} className="flex items-baseline justify-between border-b border-line py-5 group">
                <span className="flex items-baseline gap-4">
                  <span className="font-display text-3xl text-plum">{l.label}</span>
                  {l.dot && <span className="w-1.5 h-1.5 rounded-full bg-rose" aria-hidden="true" />}
                </span>
                <ArrowRight size={18} strokeWidth={1.5} className="text-mauve/50 rtl:rotate-180" />
              </NavLink>
            ))}
            <a href="#contact" onClick={() => setMobileMenuOpen(false)} className="flex items-baseline justify-between border-b border-line py-5">
              <span className="flex items-baseline gap-4">
                <span className="font-display text-3xl text-plum">{tr(S.nav.contact)}</span>
              </span>
              <ArrowRight size={18} strokeWidth={1.5} className="text-mauve/50 rtl:rotate-180" />
            </a>
          </div>

          <BookNowLink
            onClick={() => setMobileMenuOpen(false)}
            className="w-full mt-10 rounded-full bg-plum text-ivory py-4 text-[12px] font-medium uppercase tracking-[0.16em] flex items-center justify-center gap-2"
          >
            {tr(S.nav.bookNow)} <ArrowRight size={15} strokeWidth={1.8} className="rtl:rotate-180" />
          </BookNowLink>

          {/* Calling is genuinely useful on a phone, so it stays — just under
              the booking button rather than in place of it. */}
          {CLINIC.phone && (
            <a
              href={`tel:${CLINIC.phone}`}
              dir="ltr"
              className="w-full mt-3 rounded-full border border-line text-plum py-4 text-[12px] font-medium uppercase tracking-[0.16em] flex items-center justify-center gap-2 tabular-nums"
            >
              <Phone size={15} strokeWidth={1.8} /> {CLINIC.phone}
            </a>
          )}
        </div>
      )}
    </>
  )
}

export function Footer() {
  const socials = [
    { url: CLINIC.social.instagram, Icon: Instagram, label: 'Instagram' },
    { url: CLINIC.social.tiktok, Icon: Music2, label: 'TikTok' },
    { url: CLINIC.social.snapchat, Icon: Ghost, label: 'Snapchat' },
  ].filter(s => s.url)

  return (
    <footer id="contact" className="bg-plum text-ivory pt-24 pb-10">
      <Container>
        {/* Oversized wordmark as the closing statement. */}
        <div className="pb-16 border-b border-ivory/12">
          <div className="font-display text-[clamp(3rem,9vw,7.5rem)] leading-[0.95] text-ivory">
            {tr(CLINIC.name)}
          </div>
          <p className="mt-6 max-w-lg text-ivory/55 leading-relaxed">
            {tr(S.footer.tagline)}
          </p>
        </div>

        <div className="grid md:grid-cols-12 gap-12 md:gap-8 py-16">
          <div className="md:col-span-4">
            <Eyebrow tone="light">{tr(S.footer.exploreTitle)}</Eyebrow>
            <ul className="mt-6 space-y-3.5">
              {[
                { to: '/clinic/clinics', label: tr(S.nav.clinics) },
                { to: '/clinic/services', label: tr(S.nav.services) },
                { to: '/clinic/gallery', label: tr(S.nav.results) },
                { to: '/clinic/offers', label: tr(S.nav.offers) },
                { to: '/clinic/book', label: tr(S.nav.bookNow) },
              ].map(l => (
                <li key={l.to}>
                  <Link to={l.to} className="text-ivory/70 hover:text-ivory transition-colors">
                    <TextLink>{l.label}</TextLink>
                  </Link>
                </li>
              ))}
            </ul>
          </div>

          <div className="md:col-span-5">
            <Eyebrow tone="light">{tr(S.footer.contactTitle)}</Eyebrow>
            <ul className="mt-6 space-y-4 text-ivory/70">
              <li className="flex items-start gap-3.5">
                <MapPin size={17} strokeWidth={1.5} className="mt-0.5 text-rose shrink-0" />
                <span className="leading-relaxed">{tr(CLINIC.address)}</span>
              </li>
              {CLINIC.phone && (
                <li className="flex items-center gap-3.5">
                  <Phone size={17} strokeWidth={1.5} className="text-rose shrink-0" />
                  <a href={`tel:${CLINIC.phone}`} className="hover:text-ivory transition-colors tabular-nums" dir="ltr">{CLINIC.phone}</a>
                </li>
              )}
              {CLINIC.email && (
                <li className="flex items-center gap-3.5">
                  <Mail size={17} strokeWidth={1.5} className="text-rose shrink-0" />
                  <a href={`mailto:${CLINIC.email}`} className="hover:text-ivory transition-colors" dir="ltr">{CLINIC.email}</a>
                </li>
              )}
              {CLINIC.website && (
                <li className="flex items-center gap-3.5">
                  <Globe size={17} strokeWidth={1.5} className="text-rose shrink-0" />
                  <a href={CLINIC.websiteUrl} target="_blank" rel="noreferrer" className="hover:text-ivory transition-colors" dir="ltr">{CLINIC.website}</a>
                </li>
              )}
            </ul>
          </div>

          {socials.length > 0 && (
            <div className="md:col-span-3">
              <Eyebrow tone="light">Social</Eyebrow>
              <div className="mt-6 flex gap-2.5">
                {socials.map(({ url, Icon, label }) => (
                  <a
                    key={label}
                    href={url}
                    target="_blank"
                    rel="noreferrer"
                    aria-label={label}
                    className="w-11 h-11 rounded-full border border-ivory/20 flex items-center justify-center text-ivory/60 hover:text-plum hover:bg-ivory hover:border-ivory transition-colors duration-300"
                  >
                    <Icon size={17} strokeWidth={1.5} />
                  </a>
                ))}
              </div>
            </div>
          )}
        </div>

        <div className="pt-8 border-t border-ivory/12 flex flex-col sm:flex-row justify-between items-center text-ivory/45 text-[13px] gap-4">
          <div>© {new Date().getFullYear()} {tr(CLINIC.name)}. {tr(S.footer.rights)}</div>
          <div className="flex gap-7">
            <a href="#" className="hover:text-ivory/80 transition-colors">{tr(S.footer.privacy)}</a>
            <a href="#" className="hover:text-ivory/80 transition-colors">{tr(S.footer.terms)}</a>
          </div>
        </div>
      </Container>
    </footer>
  )
}

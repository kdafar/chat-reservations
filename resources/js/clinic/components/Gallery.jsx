import React, { useEffect, useMemo, useRef, useState } from 'react'
import { Link } from 'react-router-dom'
import { ArrowRight, Loader2, MoveHorizontal } from 'lucide-react'
import { Container, Eyebrow, SectionHeading, Action, Reveal, TextLink, ServiceIcon, BookNowLink } from './Shared'
import { Api } from '../api'
import { S, tr } from '../brand'

/**
 * Before / after results.
 *
 * Cases come from v2 (Clinic → Results Gallery) and only appear here when they
 * are published *and* have consent recorded against them — the API enforces
 * both, this page never sees the rest.
 */

/* ---------------------------------------------------------------------------
 * Comparison slider.
 *
 * The "after" image is the base layer; the "before" is clipped to the handle
 * position. Pointer events cover mouse, touch and pen in one path, and the
 * handle is a real range input underneath so it is keyboard operable and
 * announced properly — a purely mouse-driven divider would lock out anyone on
 * a keyboard.
 * ------------------------------------------------------------------------ */
function CompareSlider({ before, after, alt = '' }) {
  const [pos, setPos] = useState(50)
  const frame = useRef(null)
  const dragging = useRef(false)

  const setFromClientX = (clientX) => {
    const el = frame.current
    if (!el) return
    const r = el.getBoundingClientRect()
    if (!r.width) return
    const raw = ((clientX - r.left) / r.width) * 100
    // In RTL the visual left edge is still the geometric left edge, so no
    // mirroring is needed here — the clip is computed in the same space.
    setPos(Math.min(100, Math.max(0, raw)))
  }

  useEffect(() => {
    const move = (e) => { if (dragging.current) setFromClientX(e.clientX) }
    const up = () => { dragging.current = false }
    window.addEventListener('pointermove', move)
    window.addEventListener('pointerup', up)
    window.addEventListener('pointercancel', up)
    return () => {
      window.removeEventListener('pointermove', move)
      window.removeEventListener('pointerup', up)
      window.removeEventListener('pointercancel', up)
    }
  }, [])

  return (
    <div
      ref={frame}
      className="relative w-full aspect-[4/5] overflow-hidden rounded-[1.5rem] bg-petal select-none touch-none cursor-ew-resize"
      onPointerDown={(e) => { dragging.current = true; setFromClientX(e.clientX) }}
    >
      <img src={after} alt={alt} loading="lazy" draggable="false" className="absolute inset-0 w-full h-full object-cover" />

      <div className="absolute inset-0 overflow-hidden" style={{ clipPath: `inset(0 ${100 - pos}% 0 0)` }}>
        <img src={before} alt="" loading="lazy" draggable="false" className="absolute inset-0 w-full h-full object-cover" />
      </div>

      {/* Labels sit on the side each image occupies. */}
      <span className="absolute top-4 left-4 rounded-full bg-plum/80 text-ivory px-3 py-1 text-[10px] font-medium uppercase tracking-[0.16em] pointer-events-none">
        {tr(S.gallery.before)}
      </span>
      <span className="absolute top-4 right-4 rounded-full bg-ivory/85 text-plum px-3 py-1 text-[10px] font-medium uppercase tracking-[0.16em] pointer-events-none">
        {tr(S.gallery.after)}
      </span>

      <div className="absolute inset-y-0 w-px bg-ivory/90 pointer-events-none" style={{ left: `${pos}%` }}>
        <span className="absolute top-1/2 -translate-y-1/2 -translate-x-1/2 w-11 h-11 rounded-full bg-ivory text-plum shadow-[0_8px_24px_-8px_rgba(42,20,32,0.6)] flex items-center justify-center">
          <MoveHorizontal size={17} strokeWidth={1.6} />
        </span>
      </div>

      <input
        type="range"
        min="0"
        max="100"
        value={Math.round(pos)}
        onChange={(e) => setPos(Number(e.target.value))}
        aria-label={tr(S.gallery.dragHint)}
        className="absolute inset-x-0 bottom-0 w-full opacity-0 h-11 cursor-ew-resize"
      />
    </div>
  )
}

function CaseCard({ item }) {
  return (
    <article className="flex flex-col h-full rounded-[1.75rem] border border-line bg-white p-4">
      <CompareSlider before={item.before_image_url} after={item.after_image_url} alt={item.title} />

      <div className="flex flex-col flex-1 px-3 pt-6 pb-2">
        {item.service && (
          <div className="flex items-center gap-2 text-[11px] font-medium uppercase tracking-[0.16em] text-rose-deep mb-3">
            <ServiceIcon service={item.service} size={15} />
            {item.service.name}
          </div>
        )}

        <h3 className="font-display text-2xl leading-tight text-plum">{item.title}</h3>
        {item.summary && <p className="mt-3 text-sm leading-relaxed text-mauve">{item.summary}</p>}

        <div className="mt-auto pt-5 flex flex-wrap items-center gap-x-4 gap-y-2 text-xs text-mauve/70">
          {item.protocol && (
            <span className="rounded-full bg-blush px-3 py-1 font-medium text-rose-deep">{item.protocol}</span>
          )}
          {item.doctor && <span>{tr(S.gallery.withDoctor)} {item.doctor}</span>}
        </div>
      </div>
    </article>
  )
}

function useGallery() {
  const [cases, setCases] = useState([])
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    let alive = true
    Api.getGallery()
      .then(d => { if (alive) setCases(d?.cases || []) })
      .catch(() => { if (alive) setCases([]) })
      .finally(() => { if (alive) setLoading(false) })
    return () => { alive = false }
  }, [])

  return { cases, loading }
}

/** Landing teaser — three cases, no filters. */
export function GallerySection() {
  const { cases, loading } = useGallery()

  // Nothing published: keep the landing page clean rather than advertising an
  // empty gallery.
  if (!loading && cases.length === 0) return null

  return (
    <section className="bg-blush py-24 md:py-32">
      <Container>
        <div className="flex flex-col md:flex-row md:items-end md:justify-between gap-8 mb-14">
          <SectionHeading
            kicker={tr(S.gallery.kicker)}
            title={tr(S.gallery.teaserTitle)}
            subtitle={tr(S.gallery.teaserSub)}
          />
          <Link to="/clinic/gallery" className="hidden md:inline-flex shrink-0 text-plum">
            <TextLink>
              <span className="text-[12px] font-semibold uppercase tracking-[0.16em]">{tr(S.gallery.viewAll)}</span>
              <ArrowRight size={14} strokeWidth={1.8} className="rtl:rotate-180" />
            </TextLink>
          </Link>
        </div>

        {loading ? (
          <div className="flex justify-center py-20">
            <Loader2 className="animate-spin text-rose" size={26} strokeWidth={1.5} />
          </div>
        ) : (
          <div className="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
            {cases.slice(0, 3).map((c, i) => (
              <Reveal key={c.id} delay={i * 90} className="h-full">
                <CaseCard item={c} />
              </Reveal>
            ))}
          </div>
        )}

        <div className="mt-12 md:hidden">
          <Action as={Link} to="/clinic/gallery" className="w-full">
            {tr(S.gallery.viewAll)}
            <ArrowRight size={15} strokeWidth={1.8} className="rtl:rotate-180" />
          </Action>
        </div>
      </Container>
    </section>
  )
}

/** Full gallery at /clinic/gallery, filterable by treatment category. */
export function GalleryPage() {
  const { cases, loading } = useGallery()
  const [serviceId, setServiceId] = useState('')

  const services = useMemo(() => {
    const seen = new Map()
    cases.forEach(c => { if (c.service && !seen.has(c.service.id)) seen.set(c.service.id, c.service) })
    return [...seen.values()]
  }, [cases])

  const shown = serviceId ? cases.filter(c => String(c.service?.id) === String(serviceId)) : cases

  return (
    <div className="pt-40 pb-24 bg-ivory min-h-screen">
      <Container>
        <div className="mb-12">
          <Eyebrow>{tr(S.gallery.kicker)}</Eyebrow>
          <h1 className="font-display text-[clamp(2.6rem,6vw,4.5rem)] leading-[1.03] text-plum mt-6">
            {tr(S.gallery.title)}
          </h1>
          <p className="mt-5 max-w-2xl text-lg text-mauve leading-relaxed">{tr(S.gallery.subtitle)}</p>
        </div>

        {services.length > 1 && (
          <div className="flex flex-wrap gap-2.5 mb-12">
            {[{ id: '', name: tr(S.gallery.allTreatments) }, ...services].map(s => {
              const active = String(serviceId) === String(s.id)
              return (
                <button
                  key={s.id || 'all'}
                  onClick={() => setServiceId(s.id)}
                  className={`rounded-full px-5 py-2.5 text-[11px] font-medium uppercase tracking-[0.14em] border transition-colors ${
                    active
                      ? 'bg-plum text-ivory border-plum'
                      : 'bg-white text-mauve border-line hover:border-rose hover:text-rose-deep'
                  }`}
                >
                  {s.name}
                </button>
              )
            })}
          </div>
        )}

        {loading ? (
          <div className="flex justify-center py-28">
            <Loader2 className="animate-spin text-rose" size={30} strokeWidth={1.5} />
          </div>
        ) : shown.length === 0 ? (
          <div className="text-center py-24 px-6 rounded-[2rem] border border-line bg-blush/50">
            <div className="font-display text-5xl text-rose/40 mb-6">◆</div>
            <div className="font-display text-3xl text-plum mb-3">{tr(S.gallery.empty)}</div>
            <p className="text-mauve max-w-md mx-auto mb-9 leading-relaxed">{tr(S.gallery.emptyHint)}</p>
            <Action as={BookNowLink}>
              {tr(S.nav.bookNow)}
              <ArrowRight size={15} strokeWidth={1.8} className="rtl:rotate-180" />
            </Action>
          </div>
        ) : (
          <>
            <div className="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
              {shown.map((c, i) => (
                <Reveal key={c.id} delay={(i % 3) * 90} className="h-full">
                  <CaseCard item={c} />
                </Reveal>
              ))}
            </div>
            <p className="mt-12 text-xs text-mauve/60">{tr(S.gallery.consentNote)}</p>
          </>
        )}
      </Container>
    </div>
  )
}

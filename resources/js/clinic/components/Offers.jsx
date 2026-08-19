import React, { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { ArrowRight, Check, Clock, MapPin, Loader2 } from 'lucide-react'
import { Container, Eyebrow, SectionHeading, Action, Reveal, TextLink, BookNowLink, monogramOf } from './Shared'
import { Api, formatPrice } from '../api'
import { CLINIC, S, tr } from '../brand'

/**
 * Public offers = clinic packages published from v2 (Setup → Clinic Packages,
 * "Show this package as an offer on the website").
 *
 * The saving is the thing patients are here for, so every card leads with it:
 * a SAVE mark on the plate, the old price struck through, and an explicit
 * "You save X KWD" line under the new price. All three numbers come from the
 * server so they can never disagree with what the visit is actually billed.
 */

const money = (v) => `${formatPrice(v)} ${tr(S.offers.currency)}`

function SaveBadge({ percent, className = '' }) {
  if (!percent) return null
  return (
    <span className={`inline-flex items-center gap-1.5 rounded-full bg-plum text-ivory px-4 py-1.5 text-[10px] font-medium uppercase tracking-[0.18em] ${className}`}>
      {tr(S.offers.save)} <span className="tabular-nums" dir="ltr">{percent}%</span>
    </span>
  )
}

/* ---------------------------------------------------------------------------
 * Cover plate for a package with no photograph of its own.
 *
 * Most packages ship without artwork (the image is an optional URL set in v2 →
 * Clinic Packages), and a card with a hole where the photo should be reads as
 * broken. This draws a proper cover instead: a petal wash, a monogram and a
 * botanical arc motif, tinted by a hash of the package name so no two cards in
 * a row look identical. Pure SVG — nothing to download, no licensing, and it
 * stays on-palette in both locales.
 * ------------------------------------------------------------------------ */
const PLATE_TINTS = [
  { from: '#f8eeee', to: '#f0dfe0', ink: '#9a4a63' }, // rose
  { from: '#f6efe8', to: '#efdfd2', ink: '#a9743f' }, // champagne
  { from: '#f2eef2', to: '#e6dbe6', ink: '#6d4a71' }, // orchid
  { from: '#edf1ef', to: '#dee7e2', ink: '#4a6e5f' }, // eucalyptus
]

function hashOf(name = '') {
  let h = 0
  for (const ch of String(name)) h = (h * 31 + ch.codePointAt(0)) % 9973
  return h
}

export function OfferPlate({ name }) {
  // Arabic package names would make an unusable gradient id, so key it off the
  // hash rather than the monogram.
  const h = hashOf(name)
  const t = PLATE_TINTS[h % PLATE_TINTS.length]
  const gid = `pg-${h}`
  const mark = monogramOf(name)

  return (
    <svg viewBox="0 0 320 200" className="w-full h-full" role="img" aria-hidden="true" preserveAspectRatio="xMidYMid slice">
      <defs>
        <linearGradient id={gid} x1="0" y1="0" x2="1" y2="1">
          <stop offset="0%" stopColor={t.from} />
          <stop offset="100%" stopColor={t.to} />
        </linearGradient>
      </defs>
      <rect width="320" height="200" fill={`url(#${gid})`} />

      {/* Botanical arcs — off-centre so the monogram sits in clean space. */}
      <g fill="none" stroke={t.ink} strokeOpacity="0.18" strokeWidth="1">
        <circle cx="268" cy="42" r="58" />
        <circle cx="268" cy="42" r="78" />
        <path d="M32 176 C 72 176, 96 152, 96 116 C 60 116, 32 140, 32 176 Z" strokeOpacity="0.28" />
        <path d="M96 116 L 32 176" strokeOpacity="0.28" />
      </g>

      <text
        x="160" y="104" textAnchor="middle"
        fontFamily="'Cormorant Garamond', Georgia, serif" fontSize="56" fontWeight="300"
        fill={t.ink} fillOpacity="0.85"
      >
        {mark}
      </text>
      <text
        x="160" y="132" textAnchor="middle"
        fontFamily="'Jost', system-ui, sans-serif" fontSize="9" letterSpacing="4"
        fill={t.ink} fillOpacity="0.6"
      >
        {String(tr(CLINIC.name)).toUpperCase()}
      </text>
    </svg>
  )
}

/** Was / Now / You save — the full story, not just the final number. */
function PriceBlock({ offer, size = 'md' }) {
  const big = size === 'lg'
  const priceClass = `font-display leading-none text-plum tabular-nums ${big ? 'text-5xl' : 'text-4xl'}`

  if (!offer.has_discount) {
    return <div className={priceClass} dir="ltr">{money(offer.offer_price)}</div>
  }

  return (
    <div>
      <div className="flex items-baseline gap-3 flex-wrap">
        <span className={priceClass} dir="ltr">{money(offer.offer_price)}</span>
        <span className="text-sm text-mauve/60 line-through tabular-nums" dir="ltr">
          {money(offer.price)}
        </span>
      </div>
      <div className="mt-3 text-[11px] font-semibold uppercase tracking-[0.16em] text-rose-deep">
        {tr(S.offers.youSave)} <span className="tabular-nums" dir="ltr">{money(offer.savings_amount)}</span>
      </div>
    </div>
  )
}

export function OfferCard({ offer }) {
  const includes = offer.includes || []
  const shown = includes.slice(0, 4)
  const rest = includes.length - shown.length

  return (
    <article className="group flex flex-col h-full rounded-[1.75rem] overflow-hidden bg-white border border-line hover:border-rose hover:shadow-[0_28px_60px_-34px_rgba(42,20,32,0.4)] transition-all duration-300">
      <div className="relative aspect-[16/10] overflow-hidden bg-petal">
        {offer.image_url ? (
          <img
            src={offer.image_url}
            alt=""
            loading="lazy"
            className="w-full h-full object-cover group-hover:scale-[1.03] transition-transform duration-700 ease-out"
            style={{ filter: 'saturate(0.85)' }}
          />
        ) : (
          // No artwork set on the package: draw a designed cover rather than
          // leave an "image failed to load" hole.
          <OfferPlate name={offer.name} />
        )}
        {offer.savings_percent > 0 && (
          <div className="absolute top-4 start-4">
            <SaveBadge percent={offer.savings_percent} />
          </div>
        )}
      </div>

      <div className="flex flex-col flex-1 p-7">
        <h3 className="font-display text-2xl leading-tight text-plum">{offer.name}</h3>

        {offer.description && (
          <p className="mt-3 text-sm leading-relaxed text-mauve">{offer.description}</p>
        )}

        {shown.length > 0 && (
          <div className="mt-6">
            <Eyebrow>{tr(S.offers.includes)}</Eyebrow>
            <ul className="mt-4 space-y-2.5">
              {shown.map((line, i) => (
                <li key={i} className="flex items-start gap-2.5 text-sm text-mauve">
                  <Check size={14} strokeWidth={2} className="text-rose shrink-0 mt-1" />
                  <span>
                    {line.name}
                    {line.qty > 1 && (
                      <span className="ms-1.5 font-semibold text-plum tabular-nums" dir="ltr">×{line.qty}</span>
                    )}
                  </span>
                </li>
              ))}
              {rest > 0 && (
                <li className="text-sm text-mauve/60 ps-6 tabular-nums">
                  +{rest} {tr(S.offers.more)}
                </li>
              )}
            </ul>
          </div>
        )}

        <div className="mt-auto pt-7">
          <div className="pt-6 border-t border-line">
            <PriceBlock offer={offer} />

            {(offer.branch || offer.ends_at) && (
              <div className="flex flex-wrap gap-x-5 gap-y-2 mt-5 text-xs text-mauve/70">
                {offer.branch && (
                  <span className="inline-flex items-center gap-1.5">
                    <MapPin size={12} strokeWidth={1.6} /> {tr(S.offers.atBranch)} {offer.branch}
                  </span>
                )}
                {offer.ends_at && (
                  <span className="inline-flex items-center gap-1.5 text-rose-deep">
                    <Clock size={12} strokeWidth={1.6} /> {tr(S.offers.endsOn)} {offer.ends_at}
                  </span>
                )}
              </div>
            )}

            <Action
              as={BookNowLink}
              context={{ partner: offer.partner_id, branch: offer.branch_id, package: offer.id }}
              className="mt-6 w-full"
            >
              {tr(S.offers.book)}
              <ArrowRight size={15} strokeWidth={1.8} className="rtl:rotate-180" />
            </Action>
          </div>
        </div>
      </div>
    </article>
  )
}

/** Shared loader for the landing section and the full page. */
function useOffers() {
  const [data, setData] = useState(null)
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    let alive = true
    Api.getOffers()
      .then(d => { if (alive) setData(d) })
      .catch(() => { if (alive) setData({ offers: [], max_savings: 0 }) })
      .finally(() => { if (alive) setLoading(false) })
    return () => { alive = false }
  }, [])

  return { offers: data?.offers || [], maxSavings: data?.max_savings || 0, loading }
}

function EmptyOffers() {
  return (
    <div className="text-center py-24 px-6 rounded-[2rem] border border-line bg-blush/50">
      <div className="font-display text-5xl text-rose/40 mb-6">◆</div>
      <div className="font-display text-3xl text-plum mb-3">{tr(S.offers.empty)}</div>
      <p className="text-mauve max-w-md mx-auto mb-9 leading-relaxed">{tr(S.offers.emptyHint)}</p>
      <Action as={Link} to="/clinic/services">
        {tr(S.offers.browseServices)}
        <ArrowRight size={15} strokeWidth={1.8} className="rtl:rotate-180" />
      </Action>
    </div>
  )
}

/** "Save up to X KWD" — the headline number, taken from the best live offer. */
function SavingsHeadline({ amount, className = '' }) {
  if (!amount) return null
  return (
    <div className={`inline-flex flex-wrap items-baseline gap-x-3 gap-y-1 border-s-2 border-rose ps-5 py-1 ${className}`}>
      <span className="text-[11px] font-semibold uppercase tracking-[0.2em] text-mauve">
        {tr(S.offers.saveUpTo)}
      </span>
      <span className="font-display text-3xl text-plum tabular-nums leading-none" dir="ltr">
        {money(amount)}
      </span>
      <span className="text-[11px] font-semibold uppercase tracking-[0.2em] text-mauve">
        {tr(S.offers.onPackages)}
      </span>
    </div>
  )
}

/** Landing-page teaser: the three best-value offers. */
export function OffersSection() {
  const { offers, maxSavings, loading } = useOffers()

  // Nothing published yet — keep the landing page clean rather than showing
  // an empty state to every visitor.
  if (!loading && offers.length === 0) return null

  const top = [...offers]
    .sort((a, b) => (b.savings_amount || 0) - (a.savings_amount || 0))
    .slice(0, 3)

  return (
    <section className="bg-blush py-24 md:py-32">
      <Container>
        <div className="flex flex-col md:flex-row md:items-end md:justify-between gap-8 mb-14">
          <SectionHeading
            kicker={tr(S.offers.kicker)}
            title={tr(S.offers.title)}
            subtitle={tr(S.offers.subtitle)}
          />
          <SavingsHeadline amount={maxSavings} className="shrink-0" />
        </div>

        {loading ? (
          <div className="flex justify-center py-20">
            <Loader2 className="animate-spin text-rose" size={26} strokeWidth={1.5} />
          </div>
        ) : (
          <div className="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
            {top.map((o, i) => (
              <Reveal key={o.id} delay={i * 90} className="h-full">
                <OfferCard offer={o} />
              </Reveal>
            ))}
          </div>
        )}

        {offers.length > top.length && (
          <div className="mt-14 flex justify-center">
            <Link to="/clinic/offers" className="text-plum">
              <TextLink>
                <span className="text-[12px] font-semibold uppercase tracking-[0.16em]">
                  {tr(S.offers.viewAll)}
                </span>
                <ArrowRight size={14} strokeWidth={1.8} className="rtl:rotate-180" />
              </TextLink>
            </Link>
          </div>
        )}
      </Container>
    </section>
  )
}

/** Full offers page at /clinic/offers. */
export function OffersPage() {
  const { offers, maxSavings, loading } = useOffers()

  return (
    <div className="pt-40 pb-24 bg-ivory min-h-screen">
      <Container>
        <div className="mb-16">
          <Eyebrow>{tr(S.offers.kicker)}</Eyebrow>
          <h1 className="font-display text-[clamp(2.6rem,6vw,4.5rem)] leading-[1.03] text-plum mt-6">
            {tr(S.offers.pageTitle)}
          </h1>
          <p className="mt-5 max-w-2xl text-lg text-mauve leading-relaxed">
            {tr(S.offers.pageSubtitle)}
          </p>
          <SavingsHeadline amount={maxSavings} className="mt-10" />
        </div>

        {loading ? (
          <div className="flex justify-center py-28">
            <Loader2 className="animate-spin text-rose" size={30} strokeWidth={1.5} />
          </div>
        ) : offers.length === 0 ? (
          <EmptyOffers />
        ) : (
          <div className="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
            {offers.map((o, i) => (
              <Reveal key={o.id} delay={(i % 3) * 90} className="h-full">
                <OfferCard offer={o} />
              </Reveal>
            ))}
          </div>
        )}
      </Container>
    </div>
  )
}

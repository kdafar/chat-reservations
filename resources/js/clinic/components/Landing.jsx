import React, { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { ArrowRight, ArrowUpRight, Loader2 } from 'lucide-react'
import { Container, Eyebrow, SectionHeading, Action, GhostAction, Reveal, TextLink, ServiceIcon, BOOKING_ANCHOR } from './Shared'
import { Api } from '../api'
import { CLINIC, S, tr } from '../brand'
import ClinicBookingWidget from './BookingWidget'

// Editorial hero plate. Warm, close-cropped treatment work — sits in a
// full-bleed band under the headline rather than as a scrimmed background,
// so the type never has to fight the photograph for contrast.
const HERO_IMG = 'https://images.unsplash.com/photo-1570172619644-dfd03ed5d881?q=80&w=2940&auto=format&fit=crop'

/* ---------------------------------------------------------------------------
 * Hero
 *
 * Two movements: an airy type-led opening with the booking widget as the only
 * card on the page, then a full-bleed image band that closes the section.
 * ------------------------------------------------------------------------ */
export function Hero({ stats }) {
  const figures = [
    { value: stats?.doctors, label: tr(S.hero.stat1) },
    { value: stats?.treatments, label: tr(S.hero.stat2), plus: true },
  ]

  return (
    <section className="bg-ivory">
      <Container className="pt-36 lg:pt-44 pb-16 lg:pb-24">
        <div className="grid lg:grid-cols-12 gap-12 lg:gap-16 items-start">
          {/* --- Editorial column --- */}
          <div className="lg:col-span-6 xl:col-span-6">
            <Reveal>
              <Eyebrow>{tr(S.hero.badge)}</Eyebrow>
            </Reveal>

            <Reveal delay={80}>
              <h1 className="font-display text-[clamp(3rem,7.5vw,6.25rem)] leading-[0.98] text-plum mt-7">
                {tr(S.hero.titleA)}{' '}
                <span className="ltr:italic text-rose-deep">{tr(S.hero.titleB)}</span>
              </h1>
            </Reveal>

            <Reveal delay={160}>
              <p className="mt-8 max-w-md text-base md:text-lg leading-relaxed text-mauve">
                {tr(S.hero.subtitle)}
              </p>
            </Reveal>

            {/* Figures — hairline-separated, no boxes. */}
            <Reveal delay={240}>
              <div className="mt-12 pt-8 border-t border-line flex items-start gap-10 sm:gap-14">
                {figures.map((f, i) => (
                  <div key={i} className={i > 0 ? 'ps-10 sm:ps-14 border-s border-line' : ''}>
                    <div className="font-display text-5xl leading-none text-plum tabular-nums" dir="ltr">
                      {f.value != null
                        ? <>{f.value}{f.plus && <span className="text-rose">+</span>}</>
                        : <span className="inline-block w-14 h-8 bg-petal animate-pulse align-middle" />}
                    </div>
                    <div className="mt-3 text-[11px] font-semibold uppercase tracking-[0.2em] text-mauve">
                      {f.label}
                    </div>
                  </div>
                ))}
              </div>
            </Reveal>
          </div>

          {/* --- Booking widget --- */}
          {/* Every "Book Now" on the site scrolls here; scroll-mt clears the
              fixed header so the widget's own heading stays visible. */}
          <div id={BOOKING_ANCHOR} className="lg:col-span-6 xl:col-span-5 xl:col-start-8 w-full scroll-mt-28">
            <Reveal delay={200}>
              <ClinicBookingWidget />
            </Reveal>
          </div>
        </div>
      </Container>

      {/* Full-bleed plate. */}
      <Reveal>
        <figure className="relative w-full h-[380px] md:h-[520px] overflow-hidden bg-petal rounded-t-[3rem]">
          <img
            src={HERO_IMG}
            alt=""
            className="w-full h-full object-cover"
            style={{ filter: 'saturate(0.82) contrast(1.02)' }}
          />
          {/* Warm wash keeps the plate inside the palette instead of reading
              as a cool stock photo. */}
          <div className="absolute inset-0 bg-rose/15 mix-blend-multiply pointer-events-none" />
          <div className="absolute inset-x-0 bottom-0 h-24 bg-gradient-to-t from-ivory/70 to-transparent pointer-events-none" />
          <figcaption className="absolute bottom-6 start-6 md:bottom-8 md:start-12 text-[11px] font-semibold uppercase tracking-[0.22em] text-plum/70">
            {tr(CLINIC.city)}
          </figcaption>
        </figure>
      </Reveal>
    </section>
  )
}

/* ---------------------------------------------------------------------------
 * Credentials index — four figures, hairline-divided.
 * ------------------------------------------------------------------------ */
export function StatsSection({ stats }) {
  const items = [
    { value: stats?.treatments != null ? `${stats.treatments}+` : null, label: tr(S.stats.s1), sub: tr(S.stats.s1sub) },
    { value: stats?.doctors != null ? String(stats.doctors) : null, label: tr(S.stats.s2), sub: tr(S.stats.s2sub) },
    { value: stats?.branches != null ? String(stats.branches) : null, label: tr(S.stats.s3), sub: tr(S.stats.s3sub) },
    { value: stats?.categories != null ? String(stats.categories) : null, label: tr(S.stats.s4), sub: tr(S.stats.s4sub) },
  ]

  return (
    <section className="bg-blush py-20 md:py-28">
      <Container>
        <div className="grid grid-cols-2 lg:grid-cols-4 gap-y-12 gap-x-8">
          {items.map((s, i) => (
            <Reveal key={i} delay={i * 80}>
              <div className={`lg:ps-10 ${i > 0 ? 'lg:border-s border-line' : ''}`}>
                <div className="font-display text-[2.75rem] leading-none text-plum tabular-nums" dir="ltr">
                  {s.value ?? <span className="inline-block w-16 h-8 bg-petal animate-pulse align-middle" />}
                </div>
                <div className="mt-4 text-[11px] font-semibold uppercase tracking-[0.2em] text-plum">
                  {s.label}
                </div>
                <div className="mt-2 text-sm text-mauve leading-relaxed">{s.sub}</div>
              </div>
            </Reveal>
          ))}
        </div>
      </Container>
    </section>
  )
}

/* ---------------------------------------------------------------------------
 * Treatments — soft cards, one per category, each led by its line icon. The
 * whole card is the hit target.
 * ------------------------------------------------------------------------ */
export function LandingServicesSection() {
  const [services, setServices] = useState([])
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    Api.getServices()
      .then(d => setServices((d || []).slice(0, 6)))
      .catch(() => {})
      .finally(() => setLoading(false))
  }, [])

  return (
    <section className="bg-ivory py-24 md:py-32">
      <Container>
        <div className="flex flex-col md:flex-row md:items-end md:justify-between gap-8 mb-16 md:mb-20">
          <SectionHeading
            kicker={tr(S.servicesSection.kicker)}
            title={tr(S.servicesSection.title)}
            subtitle={tr(S.servicesSection.subtitle)}
          />
          <Link to="/clinic/services" className="hidden md:inline-flex shrink-0 text-plum">
            <TextLink>
              <span className="text-[12px] font-semibold uppercase tracking-[0.16em]">
                {tr(S.servicesSection.viewAll)}
              </span>
              <ArrowRight size={14} strokeWidth={1.8} className="rtl:rotate-180" />
            </TextLink>
          </Link>
        </div>

        {loading ? (
          <div className="flex justify-center py-20">
            <Loader2 className="animate-spin text-rose" size={26} strokeWidth={1.5} />
          </div>
        ) : (
          <div className="grid sm:grid-cols-2 lg:grid-cols-3 gap-5">
            {services.map((s, i) => (
              <Reveal key={s.id} delay={(i % 3) * 80} className="h-full">
                <Link
                  to={`/clinic/clinics?service_id=${s.id}`}
                  className="group relative flex flex-col h-full rounded-[1.75rem] border border-line bg-white/70 px-8 py-9 hover:bg-white hover:border-rose hover:shadow-[0_24px_50px_-30px_rgba(42,20,32,0.35)] transition-all duration-300"
                >
                  <div className="flex items-start justify-between gap-4">
                    {/* Category mark — a line icon, not the emoji the column
                        used to carry. */}
                    <span className="w-12 h-12 rounded-full bg-blush flex items-center justify-center text-rose-deep group-hover:bg-petal transition-colors duration-300">
                      <ServiceIcon service={s} size={22} />
                    </span>
                    <ArrowUpRight
                      size={18}
                      strokeWidth={1.4}
                      className="text-mauve/35 group-hover:text-rose-deep transition-all duration-300 group-hover:-translate-y-0.5 group-hover:translate-x-0.5 rtl:-scale-x-100"
                    />
                  </div>

                  <h3 className="font-display text-3xl leading-tight text-plum mt-7">
                    {tr(s.name)}
                  </h3>
                  <p className="mt-3 text-sm leading-relaxed text-mauve">
                    {tr(S.servicesSection.cardDesc)}
                  </p>
                </Link>
              </Reveal>
            ))}
          </div>
        )}

        <div className="mt-14 md:hidden">
          <Action as={Link} to="/clinic/services" className="w-full">
            {tr(S.servicesSection.viewAll)}
            <ArrowRight size={15} strokeWidth={1.8} className="rtl:rotate-180" />
          </Action>
        </div>
      </Container>
    </section>
  )
}

/* ---------------------------------------------------------------------------
 * The EVA Standard — how the clinic practises.
 *
 * Fills the narrative gap between "what we sell" and "come visit" without
 * inventing testimonials. Asymmetric on purpose: a statement column against a
 * hairline index, so it doesn't echo the treatments grid above it.
 * ------------------------------------------------------------------------ */
export function StandardSection() {
  const pillars = [
    { title: tr(S.standard.p1), sub: tr(S.standard.p1sub) },
    { title: tr(S.standard.p2), sub: tr(S.standard.p2sub) },
    { title: tr(S.standard.p3), sub: tr(S.standard.p3sub) },
    { title: tr(S.standard.p4), sub: tr(S.standard.p4sub) },
  ]

  return (
    <section className="bg-blush py-24 md:py-32">
      <Container>
        <div className="grid lg:grid-cols-12 gap-14 lg:gap-8 items-start">
          <div className="lg:col-span-5">
            <Reveal>
              <Eyebrow>{tr(S.standard.kicker)}</Eyebrow>
              <h2 className="font-display text-[clamp(2.4rem,5vw,3.75rem)] leading-[1.04] text-plum mt-5">
                {tr(S.standard.titleA)}<br />
                <span className="ltr:italic text-rose-deep">{tr(S.standard.titleB)}</span>
              </h2>
              <p className="mt-6 max-w-md text-base md:text-lg leading-relaxed text-mauve">
                {tr(S.standard.subtitle)}
              </p>
            </Reveal>
          </div>

          <div className="lg:col-span-6 lg:col-start-7 w-full border-t border-line">
            {pillars.map((p, i) => (
              <Reveal key={i} delay={i * 70}>
                <div className="flex items-baseline gap-5 sm:gap-8 py-6 border-b border-line">
                  <span className="mt-2.5 w-1.5 h-1.5 rounded-full bg-rose shrink-0" aria-hidden="true" />
                  <div>
                    <div className="font-display text-2xl leading-tight text-plum">{p.title}</div>
                    <div className="mt-1.5 text-sm text-mauve leading-relaxed">{p.sub}</div>
                  </div>
                </div>
              </Reveal>
            ))}
          </div>
        </div>
      </Container>
    </section>
  )
}

/* ---------------------------------------------------------------------------
 * Concierge — the one dark band on the page.
 * ------------------------------------------------------------------------ */
export function InfoSection() {
  const features = [tr(S.info.f1), tr(S.info.f2), tr(S.info.f3), tr(S.info.f4)]

  return (
    <section className="bg-plum text-ivory py-24 md:py-32">
      <Container>
        <div className="grid lg:grid-cols-12 gap-14 lg:gap-8 items-start">
          <div className="lg:col-span-6">
            <SectionHeading
                kicker={tr(S.info.badge)}
              tone="light"
              title={<>{tr(S.info.titleA)} <span className="ltr:italic text-rose">{tr(S.info.titleB)}</span></>}
              subtitle={tr(S.info.body)}
            />

            <div className="mt-10 flex flex-col sm:flex-row gap-3">
              <Action as={Link} to="/clinic/clinics" tone="light">
                {tr(S.info.cta1)}
                <ArrowRight size={15} strokeWidth={1.8} className="rtl:rotate-180" />
              </Action>
              <GhostAction as="a" href="#contact" tone="light">
                {tr(S.info.cta2)}
              </GhostAction>
            </div>
          </div>

          {/* Concierge index — numbered hairline list, not a floating glass card. */}
          <div className="lg:col-span-5 lg:col-start-8 w-full">
            <Eyebrow tone="light">{tr(S.info.conciergeTitle)}</Eyebrow>
            <div className="mt-7 border-t border-ivory/15">
              {features.map((item, i) => (
                <Reveal key={i} delay={i * 70}>
                  <div className="flex items-baseline gap-5 py-5 border-b border-ivory/15">
                    <span className="mt-2 w-1.5 h-1.5 rounded-full bg-rose shrink-0" aria-hidden="true" />
                    <span className="text-lg text-ivory/90">{item}</span>
                  </div>
                </Reveal>
              ))}
            </div>
            <p className="mt-6 text-sm text-ivory/45">{tr(S.info.conciergeSub)}</p>
          </div>
        </div>
      </Container>
    </section>
  )
}

import React, { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import {
  Sparkles, Users, ShieldCheck, Stethoscope, Languages, Star,
  ArrowRight, Globe, Plane, BadgeCheck, Gem, Loader2
} from 'lucide-react'
import { Container } from './Shared'
import { Api } from '../api'
import { CLINIC, S, tr } from '../brand'
import ClinicBookingWidget from './BookingWidget'

// Aesthetic/skincare hero image (replaces the generic hospital photo).
const HERO_IMG = 'https://images.unsplash.com/photo-1570172619644-dfd03ed5d881?q=80&w=2940&auto=format&fit=crop'

export function Hero({ stats }) {
  return (
    <div className="relative w-full overflow-hidden bg-slate-900">
      <div className="absolute inset-0 z-0">
        <img src={HERO_IMG} alt={tr(CLINIC.name)} className="w-full h-full object-cover opacity-90" />
        <div className="absolute inset-0 bg-gradient-to-r from-slate-950 via-slate-900/70 to-transparent rtl:bg-gradient-to-l" />
        <div className="absolute inset-0 bg-gradient-to-b from-transparent via-transparent to-slate-50" />
      </div>

      <div className="absolute top-0 right-0 -mt-20 -mr-20 w-[600px] h-[600px] bg-teal-500/10 rounded-full blur-[120px] pointer-events-none" />
      <div className="absolute bottom-0 left-0 -mb-20 -ml-20 w-[400px] h-[400px] bg-rose-500/10 rounded-full blur-[100px] pointer-events-none" />

      <Container className="relative z-10 pt-32 pb-20 lg:pt-40 lg:pb-32">
        <div className="grid lg:grid-cols-12 gap-12 lg:gap-8 items-center">
          <div className="lg:col-span-7 flex flex-col justify-center animate-in fade-in slide-in-from-left-8 duration-700">
            <div className="inline-flex items-center gap-2 self-start px-4 py-2 rounded-full bg-teal-950/80 border border-teal-500/30 text-teal-300 text-sm font-bold backdrop-blur-md mb-8 shadow-lg shadow-teal-900/20">
              <span className="relative flex h-2 w-2">
                <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-teal-400 opacity-75"></span>
                <span className="relative inline-flex rounded-full h-2 w-2 bg-teal-500"></span>
              </span>
              {tr(S.hero.badge)}
            </div>

            <h1 className="text-5xl lg:text-7xl font-extrabold text-white leading-[1.1] mb-8 tracking-tight drop-shadow-lg">
              {tr(S.hero.titleA)} <br />
              <span className="text-transparent bg-clip-text bg-gradient-to-r from-teal-200 to-emerald-400">
                {tr(S.hero.titleB)}
              </span>
            </h1>

            <p className="text-xl text-slate-100 mb-10 leading-relaxed max-w-xl font-medium drop-shadow-md">
              {tr(S.hero.subtitle)}
            </p>

            <div className="flex flex-wrap gap-6 text-white border-t border-slate-800/50 pt-8 mt-4 backdrop-blur-sm rounded-xl p-2 -ml-2">
              <div className="flex items-center gap-3">
                 <div className="w-12 h-12 rounded-2xl bg-slate-900/80 flex items-center justify-center text-teal-400 ring-1 ring-slate-700 shadow-lg backdrop-blur-md">
                    <Stethoscope size={20} strokeWidth={2.5} />
                 </div>
                 <div>
                    <div className="text-2xl font-bold leading-none drop-shadow-md">
                      {stats ? stats.doctors : <span className="inline-block w-8 h-5 rounded bg-white/20 animate-pulse align-middle" />}
                    </div>
                    <div className="text-xs text-slate-300 font-bold uppercase tracking-wider mt-1 drop-shadow-sm">{tr(S.hero.stat1)}</div>
                 </div>
              </div>

              <div className="w-px h-12 bg-slate-700/50 hidden sm:block"></div>

              <div className="flex items-center gap-3">
                 <div className="w-12 h-12 rounded-2xl bg-slate-900/80 flex items-center justify-center text-rose-300 ring-1 ring-slate-700 shadow-lg backdrop-blur-md">
                    <Sparkles size={20} strokeWidth={2.5} />
                 </div>
                 <div>
                    <div className="text-2xl font-bold leading-none drop-shadow-md">
                      {stats ? <>{stats.treatments}<span className="text-sm font-medium text-slate-300 ms-1">+</span></> : <span className="inline-block w-10 h-5 rounded bg-white/20 animate-pulse align-middle" />}
                    </div>
                    <div className="text-xs text-slate-300 font-bold uppercase tracking-wider mt-1 drop-shadow-sm">{tr(S.hero.stat2)}</div>
                 </div>
              </div>
            </div>
          </div>

          <div className="lg:col-span-5 flex justify-center lg:justify-end animate-in fade-in slide-in-from-bottom-12 duration-1000 delay-200 relative">
             <div className="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[120%] h-[120%] bg-teal-500/20 blur-[80px] rounded-full pointer-events-none"></div>
             <ClinicBookingWidget />
          </div>
        </div>
      </Container>

      <div className="absolute bottom-0 left-0 w-full h-24 bg-gradient-to-t from-slate-50 to-transparent pointer-events-none"></div>
    </div>
  )
}

export function StatsSection({ stats }) {
    const cards = [
       { val: stats?.treatments ? `${stats.treatments}+` : '—', label: tr(S.stats.s1), sub: tr(S.stats.s1sub), icon: <Sparkles size={24} />, color: 'text-teal-600', bg: 'bg-teal-50' },
       { val: stats?.doctors ?? '—', label: tr(S.stats.s2), sub: tr(S.stats.s2sub), icon: <Stethoscope size={24} />, color: 'text-blue-600', bg: 'bg-blue-50' },
       { val: <Languages size={28} strokeWidth={2.2} />, label: tr(S.stats.s3), sub: tr(S.stats.s3sub), icon: <Globe size={24} />, color: 'text-indigo-600', bg: 'bg-indigo-50' },
       { val: <Gem size={26} strokeWidth={2.2} />, label: tr(S.stats.s4), sub: tr(S.stats.s4sub), icon: <ShieldCheck size={24} />, color: 'text-rose-500', bg: 'bg-rose-50' },
    ]

    return (
        <div className="bg-slate-50 pb-16 pt-8 relative z-10">
            <Container>
                <div className="grid grid-cols-2 lg:grid-cols-4 gap-6 lg:gap-8">
                   {cards.map((s,i) => (
                      <div key={i} className="group flex flex-col items-center text-center p-8 rounded-3xl bg-white shadow-sm border border-slate-100 hover:shadow-xl hover:-translate-y-1 transition-all duration-300">
                          <div className={`mb-4 w-14 h-14 ${s.bg} ${s.color} rounded-2xl flex items-center justify-center transition-transform group-hover:scale-110 duration-300`}>
                              {s.icon}
                          </div>
                          <div className="text-4xl font-extrabold text-slate-900 mb-1 tracking-tight flex items-center justify-center h-10">{s.val}</div>
                          <div className="text-sm font-bold text-slate-700 uppercase tracking-wide">{s.label}</div>
                          <div className="text-xs font-medium text-slate-400 mt-1">{s.sub}</div>
                      </div>
                   ))}
                </div>
            </Container>
        </div>
    )
}

export function LandingServicesSection() {
   const [services, setServices] = useState([])
   const [loading, setLoading] = useState(true)

   useEffect(() => {
     Api.getServices().then(d => setServices((d || []).slice(0, 6))).catch(() => {}).finally(() => setLoading(false))
   }, [])

   return (
      <section className="py-24 bg-white relative overflow-hidden">
         <div className="absolute top-0 left-0 w-full h-full opacity-[0.03]" style={{backgroundImage: 'radial-gradient(#0f172a 1px, transparent 1px)', backgroundSize: '32px 32px'}}></div>

         <Container className="relative z-10">
            <div className="text-center max-w-3xl mx-auto mb-16">
                <h2 className="text-sm font-bold text-teal-600 tracking-widest uppercase mb-3">{tr(S.servicesSection.kicker)}</h2>
                <h3 className="text-4xl md:text-5xl font-extrabold text-slate-900 mb-6">{tr(S.servicesSection.title)}</h3>
                <p className="text-lg text-slate-500">{tr(S.servicesSection.subtitle)}</p>
            </div>

            {loading ? (
              <div className="flex justify-center py-12"><Loader2 className="animate-spin text-teal-500" size={32} /></div>
            ) : (
              <div className="grid md:grid-cols-2 lg:grid-cols-3 gap-8">
                 {services.map((s) => (
                    <Link key={s.id} to={`/clinic/clinics?service_id=${s.id}`} className="group bg-slate-50 p-8 rounded-[2rem] border border-slate-100 hover:bg-white hover:shadow-xl hover:shadow-slate-200/50 hover:-translate-y-2 transition-all duration-300 cursor-pointer block">
                        <div className="flex items-start justify-between mb-8">
                            <div className="w-16 h-16 rounded-2xl bg-white text-3xl border border-slate-100 flex items-center justify-center group-hover:scale-110 transition-all duration-300 shadow-sm">
                               {s.icon || '✨'}
                            </div>
                            <div className="w-10 h-10 rounded-full bg-transparent flex items-center justify-center text-slate-300 group-hover:text-teal-600 group-hover:bg-teal-50 transition-all">
                                <ArrowRight size={20} className="-rotate-45 group-hover:rotate-0 transition-transform duration-300 rtl:rotate-[225deg] rtl:group-hover:rotate-180" />
                            </div>
                        </div>
                        <h3 className="text-2xl font-bold text-slate-900 mb-3">{tr(s.name)}</h3>
                        <p className="text-slate-500 leading-relaxed font-medium">{tr(S.servicesSection.cardDesc)}</p>
                    </Link>
                 ))}
              </div>
            )}

            <div className="mt-16 text-center">
                <Link to="/clinic/services" className="inline-flex items-center gap-2 px-8 py-4 rounded-full bg-slate-900 text-white font-bold hover:bg-slate-800 transition-all shadow-lg hover:shadow-xl active:scale-95">
                    {tr(S.servicesSection.viewAll)} <ArrowRight size={18} className="rtl:rotate-180" />
                </Link>
            </div>
         </Container>
      </section>
   )
}

export function InfoSection() {
    const features = [tr(S.info.f1), tr(S.info.f2), tr(S.info.f3), tr(S.info.f4)]
    return (
        <section className="py-20 bg-slate-50">
            <Container>
                <div className="bg-gradient-to-br from-slate-900 via-slate-800 to-slate-900 rounded-[3rem] p-10 md:p-20 text-white relative overflow-hidden shadow-2xl shadow-slate-200/50">
                    <div className="absolute top-0 right-0 w-[600px] h-[600px] bg-teal-500/10 rounded-full blur-[100px] -mr-20 -mt-20 pointer-events-none"></div>

                    <div className="relative z-10 grid lg:grid-cols-2 gap-16 items-center">
                        <div className="max-w-xl">
                            <div className="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-white/10 backdrop-blur-md text-teal-300 font-bold text-xs uppercase tracking-wider mb-8 border border-white/5">
                                <Globe size={14} /> {tr(S.info.badge)}
                            </div>
                            <h2 className="text-4xl md:text-6xl font-extrabold mb-8 leading-[1.1] tracking-tight">
                                {tr(S.info.titleA)} <br/>
                                <span className="text-transparent bg-clip-text bg-gradient-to-r from-teal-200 to-blue-200">{tr(S.info.titleB)}</span>
                            </h2>
                            <p className="text-slate-300 text-lg md:text-xl mb-10 leading-relaxed font-medium">
                                {tr(S.info.body)}
                            </p>

                            <div className="flex flex-col sm:flex-row gap-4">
                                <Link to="/clinic/clinics" className="inline-flex items-center justify-center gap-2 px-8 py-4 rounded-xl bg-teal-500 text-white font-bold hover:bg-teal-400 transition-all shadow-lg shadow-teal-900/20 active:scale-95">
                                    <Plane size={20} /> {tr(S.info.cta1)}
                                </Link>
                                <a href="#contact" className="inline-flex items-center justify-center gap-2 px-8 py-4 rounded-xl bg-white/5 text-white font-bold border border-white/10 hover:bg-white/10 transition-all active:scale-95">
                                    {tr(S.info.cta2)}
                                </a>
                            </div>
                        </div>

                        <div className="hidden lg:block relative">
                            <div className="relative z-10 bg-slate-800/50 backdrop-blur-xl p-8 rounded-3xl border border-white/10 shadow-2xl transform rotate-3 hover:rotate-0 transition-all duration-500">
                                <div className="flex items-center gap-4 mb-6">
                                    <div className="w-12 h-12 rounded-full bg-teal-500 flex items-center justify-center text-white font-bold shadow-lg">
                                        <Plane size={24} />
                                    </div>
                                    <div>
                                        <div className="font-bold text-lg">{tr(S.info.conciergeTitle)}</div>
                                        <div className="text-slate-400 text-sm">{tr(S.info.conciergeSub)}</div>
                                    </div>
                                </div>
                                <div className="space-y-4">
                                    {features.map((item, i) => (
                                        <div key={i} className="flex items-center gap-3 text-slate-300 bg-white/5 p-3 rounded-xl">
                                            <BadgeCheck className="text-teal-400" size={18} /> {item}
                                        </div>
                                    ))}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </Container>
        </section>
    )
}

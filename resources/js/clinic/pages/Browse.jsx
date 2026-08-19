import React, { useEffect, useMemo, useState } from 'react'
import { Link, useNavigate, useParams, useLocation } from 'react-router-dom'
import {
  ArrowLeft, ArrowRight, Building2, ChevronDown, Loader2, MapPin,
  Phone, Search, Sparkles, User, Filter, Star, Clock, GraduationCap, Languages, Navigation
} from 'lucide-react'
import { Api, getLocale, t } from '../api'
import { PageShell, Container, Pill, StarRating, SearchableSelect, ServiceIcon, BookNowLink, monogramOf } from '../components/Shared'
import { S, tr, trSpecialty, WEEKDAYS } from '../brand'

export function ClinicsPage() {
  const locale = useMemo(() => getLocale(), [])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)
  const [partners, setPartners] = useState([])
  const [services, setServices] = useState([])
  const [branches, setBranches] = useState([])
  const [filters, setFilters] = useState({ partner_id: '', service_id: '', q: '' })

  const { search } = useLocation();
  const searchParams = useMemo(() => new URLSearchParams(search), [search]);

  useEffect(() => {
     const sid = searchParams.get('service_id');
     if(sid) setFilters(p => ({...p, service_id: sid}));
  }, [searchParams]);

  useEffect(() => {
    Promise.all([Api.getPartners(), Api.getServices()]).then(([p, s]) => { setPartners(p||[]); setServices(s||[]) })
  }, [])

  useEffect(() => {
    setLoading(true); setError(null)
    const payload = { partner_id: filters.partner_id || null, service_id: filters.service_id || null }
    Api.getBranchesIndex(payload).then((data) => {
        let list = data || []
        const q = (filters.q || '').trim().toLowerCase()
        if (q) list = list.filter(b => t(b.name, locale).toLowerCase().includes(q) || t(b.address, locale).toLowerCase().includes(q))
        setBranches(list)
    }).catch(e => setError(e.message)).finally(() => setLoading(false))
  }, [filters.partner_id, filters.service_id, filters.q])

  return (
    <PageShell title={tr(S.clinics.title)} subtitle={tr(S.clinics.subtitle)}>
      {/* Filter Bar */}
      <div className="bg-white border border-line rounded-[1.75rem] p-5 mb-10 sticky top-24 z-30">
        <div className="grid md:grid-cols-12 gap-4 items-end">
          <div className="md:col-span-4">
             <label className="text-[10px] font-semibold text-mauve/70 uppercase tracking-wider mb-2 block px-1">{tr(S.clinics.partner)}</label>
             <SearchableSelect
                value={filters.partner_id}
                onChange={(v) => setFilters(p => ({ ...p, partner_id: v }))}
                placeholder={tr(S.clinics.allPartners)}
                options={[{ value: '', label: tr(S.clinics.allPartners) }, ...partners.map(p => ({ value: p.id, label: t(p.name, locale) }))]}
             />
          </div>
          <div className="md:col-span-4">
             <label className="text-[10px] font-semibold text-mauve/70 uppercase tracking-wider mb-2 block px-1">{tr(S.clinics.service)}</label>
             <SearchableSelect
                value={filters.service_id}
                onChange={(v) => setFilters(p => ({ ...p, service_id: v }))}
                placeholder={tr(S.clinics.allServices)}
                options={[{ value: '', label: tr(S.clinics.allServices) }, ...services.map(s => ({ value: s.id, label: t(s.name, locale) }))]}
             />
          </div>
          <div className="md:col-span-4">
             <label className="text-[10px] font-semibold text-mauve/70 uppercase tracking-wider mb-2 block px-1">{tr(S.clinics.searchLabel)}</label>
             <div className="relative">
                 <input value={filters.q} onChange={(e) => setFilters(p => ({ ...p, q: e.target.value }))} className="w-full h-14 bg-ivory border border-line rounded-2xl ps-11 pe-4 font-medium text-plum focus:border-rose-deep outline-none transition-colors placeholder:font-normal" placeholder={tr(S.clinics.searchPlaceholder)} />
                 <Search className="absolute start-4 top-1/2 -translate-y-1/2 text-mauve/70 pointer-events-none" size={18}/>
             </div>
          </div>
        </div>
      </div>

      {/* Results */}
      {loading ? (
          <div className="flex flex-col items-center justify-center py-20 text-mauve/70">
             <Loader2 className="animate-spin mb-4 text-rose-deep" size={40} />
             <div className="font-medium animate-pulse">{tr({en:'Locating clinics…', ar:'جارٍ تحميل الفروع…'})}</div>
          </div>
      ) : branches.length === 0 ? (
          <div className="text-center py-20 bg-ivory border border-line rounded-[2rem]">
             <Building2 className="mx-auto mb-4 text-mauve/40" size={48} />
             <h3 className="text-xl font-semibold text-plum mb-2">{tr(S.clinics.noResults)}</h3>
          </div>
      ) : (
          <div className="grid gap-6">
             {branches.map(b => (
                <Link key={b.id} to={`/clinic/clinics/${b.slug}`} className="group relative block bg-white border border-line rounded-[2rem] p-6  hover:border-rose transition-all duration-300">
                    <div className="flex flex-col md:flex-row gap-6 items-start md:items-center">
                        <div className="w-20 h-20 rounded-2xl bg-petal/50 flex items-center justify-center text-rose-deep shrink-0 overflow-hidden group-hover:scale-110 transition-transform duration-300">
                           {(b.cover_image_url || b.logo_url)
                             ? <img src={b.cover_image_url || b.logo_url} alt={t(b.name, locale)} className="w-full h-full object-cover" />
                             : <Building2 size={32} />}
                        </div>
                        <div className="flex-1">
                            <h3 className="text-2xl font-semibold text-plum mb-2 group-hover:text-rose-deep transition-colors">{t(b.name, locale)}</h3>
                            <div className="flex items-center gap-2 text-mauve font-medium mb-3">
                               <MapPin size={16} className="text-rose"/> {t(b.address, locale)}
                            </div>
                            <div className="flex items-center gap-4">
                               {b.rating_count > 0 && <StarRating avg={b.rating_avg} count={b.rating_count} />}
                               {b.open_now != null && (
                                 <Pill>{b.open_now ? tr({en:'Open now', ar:'مفتوح الآن'}) : tr({en:'Closed', ar:'مغلق'})}</Pill>
                               )}
                            </div>
                        </div>
                        <div className="self-end md:self-center">
                           <div className="w-12 h-12 rounded-full bg-ivory flex items-center justify-center group-hover:bg-rose-deep group-hover:text-white transition-all">
                              <ArrowRight size={20} />
                           </div>
                        </div>
                    </div>
                </Link>
             ))}
          </div>
      )}
    </PageShell>
  )
}

/**
 * Opening hours for a branch. Index 0 = Sunday, matching the day_of_week the
 * server sends and the convention Branch::isOpenNow() uses, so the printed
 * week and the "Open now" badge cannot disagree.
 */
function OpeningHours({ hours = [], openNow }) {
  if (!hours.length) return null

  const today = new Date().getDay()

  return (
    <div className="bg-white rounded-[1.75rem] border border-line p-6">
      <div className="flex items-center justify-between gap-4 mb-5">
        <h3 className="font-display text-2xl text-plum">{tr(S.branch.hoursTitle)}</h3>
        {openNow != null && (
          <span className={`inline-flex items-center gap-2 rounded-full px-3 py-1 text-[11px] font-medium uppercase tracking-[0.14em] ${openNow ? 'bg-success-soft text-success' : 'bg-petal/60 text-mauve'}`}>
            <span className={`w-1.5 h-1.5 rounded-full ${openNow ? 'bg-success' : 'bg-mauve/50'}`} />
            {tr(openNow ? S.branch.openNow : S.branch.closedNow)}
          </span>
        )}
      </div>

      <ul className="divide-y divide-line">
        {hours.map(h => {
          const isToday = h.day === today
          return (
            <li
              key={h.day}
              className={`flex items-center justify-between gap-4 py-2.5 text-sm ${isToday ? 'font-medium text-plum' : 'text-mauve'}`}
            >
              <span className="flex items-center gap-2">
                {tr(WEEKDAYS[h.day])}
                {isToday && (
                  <span className="rounded-full bg-blush px-2 py-0.5 text-[9px] font-medium uppercase tracking-[0.14em] text-rose-deep">
                    {tr(S.branch.today)}
                  </span>
                )}
              </span>
              {h.is_closed
                ? <span className="text-mauve/60">{tr(S.branch.closed)}</span>
                : <span className="tabular-nums" dir="ltr">{h.opens_at} – {h.closes_at}</span>}
            </li>
          )
        })}
      </ul>
    </div>
  )
}

/**
 * Branch location. The map is an OpenStreetMap embed rather than a Google Maps
 * one: no API key, no billing account and no third-party script on the page.
 * The Directions button still hands off to whichever maps app the visitor has.
 */
function BranchMap({ branch, locale }) {
  const { latitude: lat, longitude: lng } = branch

  if (lat == null || lng == null) return null

  const d = 0.006 // ~600m box around the pin
  const bbox = [lng - d, lat - d / 2, lng + d, lat + d / 2].join('%2C')
  const embed = `https://www.openstreetmap.org/export/embed.html?bbox=${bbox}&layer=mapnik&marker=${lat}%2C${lng}`
  const directions = `https://www.google.com/maps/dir/?api=1&destination=${lat},${lng}`

  return (
    <div className="bg-white rounded-[1.75rem] border border-line overflow-hidden">
      <div className="flex items-center justify-between gap-4 p-6 pb-4">
        <h3 className="font-display text-2xl text-plum">{tr(S.branch.locationTitle)}</h3>
        <a
          href={directions}
          target="_blank"
          rel="noreferrer"
          className="inline-flex items-center gap-2 rounded-full bg-plum text-ivory px-5 py-2.5 text-[11px] font-medium uppercase tracking-[0.14em] hover:bg-rose-deep transition-colors"
        >
          <Navigation size={13} strokeWidth={1.8} /> {tr(S.branch.directions)}
        </a>
      </div>
      <p className="px-6 pb-4 text-sm text-mauve leading-relaxed">{t(branch.address, locale)}</p>
      <iframe
        title={tr(S.branch.locationTitle)}
        src={embed}
        loading="lazy"
        className="w-full h-[280px] border-0 border-t border-line"
      />
    </div>
  )
}

export function ClinicDetailsPage() {
  const { slug } = useParams(); 
  const locale = useMemo(() => getLocale(), []); 
  const navigate = useNavigate()
  const [branch, setBranch] = useState(null); 
  const [doctors, setDoctors] = useState([])
  const [loading, setLoading] = useState(true)
  
  useEffect(() => { 
      setLoading(true)
      Api.getBranchBySlug(slug).then(d => { 
          setBranch(d?.branch); 
          setDoctors(d?.doctors||[]) 
          setLoading(false)
      }) 
  }, [slug])
  
  if(loading) return <div className="h-screen flex items-center justify-center"><Loader2 className="animate-spin text-rose-deep" size={40}/></div>
  if(!branch) return <div className="pt-32 text-center font-semibold text-plum">Clinic not found</div>

  return (
    <div className="pt-24 bg-white min-h-screen">
       <div className="bg-ivory border-b border-line pb-16 pt-10">
          <Container>
             <button onClick={()=>navigate(-1)} className="flex items-center gap-2 mb-8 font-semibold text-mauve hover:text-plum transition-colors group">
                 <div className="w-8 h-8 rounded-full bg-white border border-line flex items-center justify-center group-hover:border-rose transition-colors"><ArrowLeft size={16} className="rtl:rotate-180"/></div>
                 {tr({en:'Back to Clinics', ar:'العودة للفروع'})}
             </button>
             
             <div className="flex flex-col md:flex-row gap-8 items-start">
                 <div className="w-24 h-24 bg-plum text-white flex items-center justify-center overflow-hidden shrink-0">
                     {(branch.cover_image_url || branch.logo_url)
                       ? <img src={branch.cover_image_url || branch.logo_url} alt={t(branch.name, locale)} className="w-full h-full object-cover" />
                       : <Building2 size={40} />}
                 </div>
                 <div className="flex-1">
                     <h1 className="text-4xl md:text-5xl font-semibold text-plum mb-4">{t(branch.name, locale)}</h1>
                     <div className="flex flex-wrap items-center gap-6 text-mauve font-medium text-lg">
                        <span className="flex items-center gap-2"><MapPin size={20} className="text-rose"/> {t(branch.address, locale)}</span>
                        {branch.open_now != null && (
                          <span className="flex items-center gap-2"><Clock size={20} className="text-rose"/> {branch.open_now ? tr({en:'Open now', ar:'مفتوح الآن'}) : tr({en:'Closed', ar:'مغلق'})}</span>
                        )}
                        {branch.rating_count > 0 && (
                          <span className="flex items-center gap-2"><Star className="text-champagne" fill="currentColor" size={20}/> {Number(branch.rating_avg).toFixed(1)} ({branch.rating_count})</span>
                        )}
                     </div>
                 </div>
                 <BookNowLink
                   context={{ partner: branch.partner?.id, branch: branch.id }}
                   className="inline-flex items-center gap-2 rounded-full bg-plum text-ivory px-7 py-3.5 text-[12px] font-medium uppercase tracking-[0.16em] hover:bg-rose-deep transition-colors shrink-0 self-start md:self-center"
                 >
                   {tr(S.nav.bookNow)} <ArrowRight size={14} strokeWidth={1.8} className="rtl:rotate-180" />
                 </BookNowLink>
             </div>
          </Container>
       </div>
       <Container className="py-16">
           <div className="flex items-center justify-between mb-8">
               <h2 className="text-3xl font-semibold text-plum">{tr({en:'Our Doctors', ar:'أطباؤنا'})}</h2>
               <div className="text-mauve font-semibold">{doctors.length} {tr({en:'specialists', ar:'أخصائي'})}</div>
           </div>
           
           <div className="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
              {doctors.map(d => (
                  // Not one big <Link>: the card carries two destinations, the
                  // profile and a booking that already knows this doctor.
                  <div key={d.id} className="group bg-white border border-line rounded-[2rem] p-6 hover:border-rose transition-all duration-300 flex flex-col">
                      <Link to={`/clinic/doctors/${d.id}`} className="flex items-center gap-5 mb-6">
                          <div className="w-20 h-20 rounded-2xl bg-ivory overflow-hidden shrink-0 border border-line">
                             {d.avatar_path ? <img src={d.avatar_path} className="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500" alt={d.name}/> : <span className="w-full h-full flex items-center justify-center bg-petal/70 font-display text-2xl text-rose-deep">{monogramOf(d.name)}</span>}
                          </div>
                          <div>
                              <div className="font-semibold text-xl text-plum group-hover:text-rose-deep transition-colors">{d.name}</div>
                              <div className="text-rose-deep font-medium bg-petal/50 rounded-full inline-block px-3 py-0.5 text-sm mt-1">{trSpecialty(d.specialty)}</div>
                          </div>
                      </Link>
                      <div className="flex items-center justify-between gap-3 pt-6 mt-auto border-t border-line">
                          <Link to={`/clinic/doctors/${d.id}`} className="text-mauve/70 text-sm font-medium hover:text-plum transition-colors">
                            {tr({en:'View Profile', ar:'عرض الملف'})}
                          </Link>
                          <BookNowLink
                            context={{ partner: branch.partner?.id, branch: branch.id, doctor: d.id }}
                            className="inline-flex items-center gap-2 rounded-full bg-plum text-ivory px-5 py-2.5 text-[11px] font-medium uppercase tracking-[0.14em] hover:bg-rose-deep transition-colors"
                          >
                            {tr(S.nav.bookNow)} <ArrowRight size={13} strokeWidth={1.8} className="rtl:rotate-180" />
                          </BookNowLink>
                      </div>
                  </div>
              ))}
           </div>

           {/* Practical details: when to come and how to get there. */}
           <div className="grid lg:grid-cols-2 gap-6 mt-12">
              <OpeningHours hours={branch.opening_hours} openNow={branch.open_now} />
              <BranchMap branch={branch} locale={locale} />
           </div>
       </Container>
    </div>
  )
}

export function ServicesPage() {
  const locale = useMemo(() => getLocale(), []); 
  const [services, setServices] = useState([])
  const [loading, setLoading] = useState(true)

  useEffect(() => { 
      Api.getServices().then(d => { setServices(d||[]); setLoading(false) }) 
  }, [])
  
  return (
    <PageShell title={tr(S.servicesPage.title)} subtitle={tr(S.servicesPage.subtitle)}>
       {loading ? (
          <div className="grid md:grid-cols-3 gap-6">
              {[1,2,3,4,5,6].map(i => <div key={i} className="h-40 bg-ivory animate-pulse"/>)}
          </div>
       ) : (
           <div className="grid md:grid-cols-3 gap-6">
              {services.map(s => (
                  <Link key={s.id} to={`/clinic/clinics?service_id=${s.id}`} className="group p-8 bg-white border border-line rounded-[2.5rem] hover:border-rose hover:shadow-[0_28px_60px_-36px_rgba(42,20,32,0.4)] transition-all duration-300 flex flex-col items-center text-center">
                      <div className="w-20 h-20 rounded-full bg-blush flex items-center justify-center mb-6 text-rose-deep group-hover:bg-petal transition-colors duration-300">
                          <ServiceIcon service={s} size={30} />
                      </div>
                      <div className="font-display text-3xl text-plum mb-6">{t(s.name, locale)}</div>
                      <div className="mt-auto font-semibold text-rose-deep flex items-center gap-2 opacity-0 group-hover:opacity-100 transition-all transform translate-y-2 group-hover:translate-y-0">
                          {tr(S.servicesPage.cardCta)} <ArrowRight size={16} className="rtl:rotate-180"/>
                      </div>
                  </Link>
              ))}
           </div>
       )}
    </PageShell>
  )
}

export function DoctorDetailsPage() {
  const { id } = useParams(); 
  const navigate = useNavigate(); 
  const [doctor, setDoctor] = useState(null)
  const [loading, setLoading] = useState(true)
  
  useEffect(() => { 
      setLoading(true)
      Api.getDoctorById(id).then(d => { setDoctor(d?.doctor); setLoading(false) }) 
  }, [id])
  
  if(loading) return <div className="h-screen flex items-center justify-center"><Loader2 className="animate-spin text-rose-deep" size={40}/></div>
  if(!doctor) return <div className="pt-32 text-center font-semibold text-plum">{tr({en:'Doctor not found', ar:'الطبيب غير موجود'})}</div>

  return (
    <div className="pt-28 pb-12 bg-white min-h-screen">
       <Container>
          <button onClick={()=>navigate(-1)} className="flex items-center gap-2 mb-8 font-semibold text-mauve hover:text-plum transition-colors group">
             <div className="w-8 h-8 rounded-full bg-ivory border border-line flex items-center justify-center group-hover:border-rose transition-colors"><ArrowLeft size={16} className="rtl:rotate-180"/></div>
             {tr({en:'Back', ar:'رجوع'})}
          </button>
          
          <div className="grid lg:grid-cols-12 gap-12">
             {/* Left Column: Image & Quick Stats */}
             <div className="lg:col-span-4">
                 <div className="rounded-[2.5rem] overflow-hidden border border-line mb-8 relative group">
                    <div className="absolute inset-0 bg-gradient-to-t from-plum/50 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
                    {doctor.avatar_path ? <img src={doctor.avatar_path} className="w-full aspect-[3/4] object-cover" alt={doctor.name}/> : <span className="w-full aspect-[3/4] flex items-center justify-center bg-petal/70 font-display text-7xl text-rose-deep">{monogramOf(doctor.name)}</span>}
                 </div>
                 
                 <div className="bg-ivory p-6 rounded-2xl border border-line space-y-4">
                    <div className="flex items-center gap-4">
                        <div className="w-10 h-10 rounded-full bg-white text-rose-deep flex items-center justify-center "><Languages size={20}/></div>
                        <div>
                            <div className="text-xs font-semibold text-mauve/70 uppercase">{tr({en:'Languages', ar:'اللغات'})}</div>
                            <div className="font-semibold text-plum">{doctor.languages ? doctor.languages.join(', ') : tr({en:'Arabic, English', ar:'العربية، الإنجليزية'})}</div>
                        </div>
                    </div>
                    {doctor.experience && (
                      <div className="flex items-center gap-4">
                          <div className="w-10 h-10 rounded-full bg-white text-rose-deep flex items-center justify-center "><GraduationCap size={20}/></div>
                          <div>
                              <div className="text-xs font-semibold text-mauve/70 uppercase">{tr({en:'Experience', ar:'الخبرة'})}</div>
                              <div className="font-semibold text-plum">{doctor.experience}</div>
                          </div>
                      </div>
                    )}
                 </div>
             </div>

             {/* Right Column: Bio & Booking */}
             <div className="lg:col-span-8 flex flex-col">
                 <div className="mb-8">
                     <span className="inline-block px-4 py-1.5 rounded-full bg-petal/50 text-rose-deep font-semibold text-sm mb-4 border border-line">
                        {trSpecialty(doctor.specialty)}
                     </span>
                     <h1 className="text-5xl font-semibold text-plum mb-6 leading-tight">{doctor.name}</h1>

                     <div className="prose prose-lg text-mauve leading-relaxed mb-10">
                        <h3 className="text-plum font-semibold text-xl mb-3">{tr({en:'About', ar:'نبذة'})}</h3>
                        <p>{doctor.bio || tr({en:'A dedicated specialist committed to the highest quality of aesthetic and dermatology care.', ar:'أخصائي متمرّس ملتزم بتقديم أعلى مستويات الرعاية التجميلية والجلدية.'})}</p>
                     </div>

                     <div className="bg-plum rounded-[2rem] p-8 text-white relative overflow-hidden">
                        <div className="absolute top-0 right-0 w-64 h-64 bg-plum rounded-full blur-[100px] opacity-20 -mr-16 -mt-16 pointer-events-none"></div>
                        <div className="relative z-10 flex flex-col md:flex-row items-center justify-between gap-8">
                            <div>
                                <h3 className="text-2xl font-semibold mb-2">{tr({en:'Book an Appointment', ar:'احجزي موعدًا'})}</h3>
                                <p className="text-mauve/70">{tr({en:`Schedule your consultation with ${doctor.name} today.`, ar:`احجزي استشارتك مع ${doctor.name} اليوم.`})}</p>
                            </div>
                            <BookNowLink
                              context={{ partner: doctor.partner?.id, branch: doctor.branch?.id, doctor: doctor.id }}
                              className="inline-flex items-center gap-2 rounded-full bg-ivory text-plum px-8 py-4 text-[12px] font-medium uppercase tracking-[0.16em] hover:bg-white transition-colors whitespace-nowrap"
                            >
                                {tr(S.nav.bookNow)} <ArrowRight size={14} strokeWidth={1.8} className="rtl:rotate-180" />
                            </BookNowLink>
                        </div>
                     </div>
                 </div>
             </div>
          </div>
       </Container>
    </div>
  )
}
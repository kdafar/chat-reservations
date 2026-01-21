import React, { useEffect, useMemo, useState } from 'react'
import { Link, useNavigate, useParams, useLocation } from 'react-router-dom'
import {
  ArrowLeft, ArrowRight, Building2, ChevronDown, Loader2, MapPin,
  Phone, Search, Sparkles, User, Filter, Star, Clock, GraduationCap, Languages
} from 'lucide-react'
import { Api, getLocale, t } from '../api'
import { PageShell, Container, Pill, StarRating } from '../components/Shared'

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
    <PageShell title="Our Clinics" subtitle="Find a medical center near you.">
      {/* Filter Bar */}
      <div className="bg-white border border-slate-100 shadow-sm rounded-3xl p-5 mb-10 sticky top-24 z-30">
        <div className="grid md:grid-cols-12 gap-4 items-end">
          <div className="md:col-span-4 relative">
             <label className="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-2 block pl-1">Partner</label>
             <div className="relative">
                 <select value={filters.partner_id} onChange={(e) => setFilters(p => ({ ...p, partner_id: e.target.value }))} className="w-full h-14 rounded-xl bg-slate-50 border border-slate-200 px-4 appearance-none font-semibold text-slate-700 focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500 outline-none transition-all">
                    <option value="">All Partners</option>
                    {partners.map(p => <option key={p.id} value={p.id}>{t(p.name, locale)}</option>)}
                 </select>
                 <ChevronDown className="absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" size={18}/>
             </div>
          </div>
          <div className="md:col-span-4 relative">
             <label className="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-2 block pl-1">Service</label>
             <div className="relative">
                 <select value={filters.service_id} onChange={(e) => setFilters(p => ({ ...p, service_id: e.target.value }))} className="w-full h-14 rounded-xl bg-slate-50 border border-slate-200 px-4 appearance-none font-semibold text-slate-700 focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500 outline-none transition-all">
                    <option value="">All Services</option>
                    {services.map(s => <option key={s.id} value={s.id}>{t(s.name, locale)}</option>)}
                 </select>
                 <ChevronDown className="absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" size={18}/>
             </div>
          </div>
          <div className="md:col-span-4 relative">
             <label className="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-2 block pl-1">Search</label>
             <div className="relative">
                 <input value={filters.q} onChange={(e) => setFilters(p => ({ ...p, q: e.target.value }))} className="w-full h-14 rounded-xl bg-slate-50 border border-slate-200 pl-11 pr-4 font-semibold text-slate-700 focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500 outline-none transition-all placeholder:font-normal" placeholder="Search clinics..." />
                 <Search className="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" size={18}/>
             </div>
          </div>
        </div>
      </div>

      {/* Results */}
      {loading ? (
          <div className="flex flex-col items-center justify-center py-20 text-slate-400">
             <Loader2 className="animate-spin mb-4 text-teal-600" size={40} />
             <div className="font-medium animate-pulse">Locating clinics...</div>
          </div>
      ) : branches.length === 0 ? (
          <div className="text-center py-20 bg-slate-50 rounded-3xl border border-slate-100">
             <Building2 className="mx-auto mb-4 text-slate-300" size={48} />
             <h3 className="text-xl font-bold text-slate-900 mb-2">No clinics found</h3>
             <p className="text-slate-500">Try adjusting your filters or search terms.</p>
          </div>
      ) : (
          <div className="grid gap-6">
             {branches.map(b => (
                <Link key={b.id} to={`/clinic/clinics/${b.slug}`} className="group relative block bg-white border border-slate-100 rounded-[2rem] p-6 hover:shadow-xl hover:shadow-slate-200/50 hover:border-teal-200 transition-all duration-300">
                    <div className="flex flex-col md:flex-row gap-6 items-start md:items-center">
                        <div className="w-20 h-20 rounded-2xl bg-teal-50 flex items-center justify-center text-teal-600 shrink-0 group-hover:scale-110 transition-transform duration-300">
                           <Building2 size={32} />
                        </div>
                        <div className="flex-1">
                            <h3 className="text-2xl font-bold text-slate-900 mb-2 group-hover:text-teal-700 transition-colors">{t(b.name, locale)}</h3>
                            <div className="flex items-center gap-2 text-slate-500 font-medium mb-3">
                               <MapPin size={16} className="text-teal-500"/> {t(b.address, locale)}
                            </div>
                            <div className="flex items-center gap-4">
                               <StarRating avg={b.rating} count={b.reviews} />
                               <Pill>Open 24/7</Pill>
                            </div>
                        </div>
                        <div className="self-end md:self-center">
                           <div className="w-12 h-12 rounded-full bg-slate-50 flex items-center justify-center group-hover:bg-teal-600 group-hover:text-white transition-all">
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
  
  if(loading) return <div className="h-screen flex items-center justify-center"><Loader2 className="animate-spin text-teal-600" size={40}/></div>
  if(!branch) return <div className="pt-32 text-center font-bold text-slate-900">Clinic not found</div>

  return (
    <div className="pt-24 bg-white min-h-screen">
       <div className="bg-slate-50 border-b border-slate-100 pb-16 pt-10">
          <Container>
             <button onClick={()=>navigate(-1)} className="flex items-center gap-2 mb-8 font-bold text-slate-500 hover:text-slate-900 transition-colors group">
                 <div className="w-8 h-8 rounded-full bg-white border border-slate-200 flex items-center justify-center group-hover:border-slate-400 transition-colors"><ArrowLeft size={16}/></div> 
                 Back to Clinics
             </button>
             
             <div className="flex flex-col md:flex-row gap-8 items-start">
                 <div className="w-24 h-24 rounded-3xl bg-teal-600 text-white flex items-center justify-center shadow-lg shadow-teal-500/30">
                     <Building2 size={40} />
                 </div>
                 <div>
                     <h1 className="text-4xl md:text-5xl font-extrabold text-slate-900 mb-4">{t(branch.name, locale)}</h1>
                     <div className="flex flex-wrap items-center gap-6 text-slate-600 font-medium text-lg">
                        <span className="flex items-center gap-2"><MapPin size={20} className="text-teal-500"/> {t(branch.address, locale)}</span>
                        <span className="flex items-center gap-2"><Clock size={20} className="text-teal-500"/> Open 24 Hours</span>
                        <span className="flex items-center gap-2"><Star className="text-amber-500" fill="currentColor" size={20}/> 4.9 (210 reviews)</span>
                     </div>
                 </div>
             </div>
          </Container>
       </div>
       <Container className="py-16">
           <div className="flex items-center justify-between mb-8">
               <h2 className="text-3xl font-bold text-slate-900">Available Doctors</h2>
               <div className="text-slate-500 font-bold">{doctors.length} Specialist{doctors.length!==1?'s':''}</div>
           </div>
           
           <div className="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
              {doctors.map(d => (
                  <Link key={d.id} to={`/clinic/doctors/${d.id}`} className="group bg-white border border-slate-100 rounded-[2rem] p-6 hover:shadow-xl hover:shadow-slate-200/50 hover:-translate-y-1 transition-all duration-300">
                      <div className="flex items-center gap-5 mb-6">
                          <div className="w-20 h-20 rounded-2xl bg-slate-100 overflow-hidden shrink-0 border border-slate-100">
                             {d.avatar_path ? <img src={d.avatar_path} className="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500" alt={d.name}/> : <User className="w-full h-full p-4 text-slate-400"/>}
                          </div>
                          <div>
                              <div className="font-bold text-xl text-slate-900 group-hover:text-teal-700 transition-colors">{d.name}</div>
                              <div className="text-teal-600 font-medium bg-teal-50 inline-block px-2 py-0.5 rounded-lg text-sm mt-1">{d.specialty}</div>
                          </div>
                      </div>
                      <div className="flex items-center justify-between pt-6 border-t border-slate-50">
                          <div className="text-slate-400 text-sm font-bold">View Profile</div>
                          <div className="w-10 h-10 rounded-full bg-slate-50 flex items-center justify-center text-slate-400 group-hover:bg-teal-600 group-hover:text-white transition-all">
                              <ArrowRight size={18} />
                          </div>
                      </div>
                  </Link>
              ))}
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
    <PageShell title="Medical Departments" subtitle="Comprehensive care across all specialties.">
       {loading ? (
          <div className="grid md:grid-cols-3 gap-6">
              {[1,2,3,4,5,6].map(i => <div key={i} className="h-40 bg-slate-100 rounded-3xl animate-pulse"/>)}
          </div>
       ) : (
           <div className="grid md:grid-cols-3 gap-6">
              {services.map(s => (
                  <Link key={s.id} to={`/clinic/clinics?service_id=${s.id}`} className="group p-8 bg-white border border-slate-100 rounded-[2.5rem] hover:shadow-2xl hover:shadow-slate-200/50 hover:-translate-y-2 hover:border-teal-100 transition-all duration-300 flex flex-col items-center text-center">
                      <div className="w-20 h-20 bg-teal-50 text-teal-600 rounded-3xl flex items-center justify-center mb-6 group-hover:bg-teal-500 group-hover:text-white group-hover:rotate-6 transition-all duration-300 shadow-sm">
                          <Sparkles size={32} strokeWidth={1.5} />
                      </div>
                      <div className="font-bold text-2xl text-slate-900 mb-2">{t(s.name, locale)}</div>
                      <div className="text-slate-500 font-medium mb-6">Expert care and advanced treatment options.</div>
                      <div className="mt-auto font-bold text-teal-600 flex items-center gap-2 opacity-0 group-hover:opacity-100 transition-all transform translate-y-2 group-hover:translate-y-0">
                          Find Doctors <ArrowRight size={16}/>
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
  
  if(loading) return <div className="h-screen flex items-center justify-center"><Loader2 className="animate-spin text-teal-600" size={40}/></div>
  if(!doctor) return <div className="pt-32 text-center">Doctor not found</div>

  return (
    <div className="pt-28 pb-12 bg-white min-h-screen">
       <Container>
          <button onClick={()=>navigate(-1)} className="flex items-center gap-2 mb-8 font-bold text-slate-500 hover:text-slate-900 transition-colors group">
             <div className="w-8 h-8 rounded-full bg-slate-50 border border-slate-200 flex items-center justify-center group-hover:border-slate-400 transition-colors"><ArrowLeft size={16}/></div> 
             Back
          </button>
          
          <div className="grid lg:grid-cols-12 gap-12">
             {/* Left Column: Image & Quick Stats */}
             <div className="lg:col-span-4">
                 <div className="rounded-[2.5rem] overflow-hidden border border-slate-100 shadow-2xl shadow-slate-200/50 mb-8 relative group">
                    <div className="absolute inset-0 bg-gradient-to-t from-slate-900/50 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
                    {doctor.avatar_path ? <img src={doctor.avatar_path} className="w-full aspect-[3/4] object-cover" alt={doctor.name}/> : <User className="w-full aspect-[3/4] p-20 text-slate-300 bg-slate-50"/>}
                 </div>
                 
                 <div className="bg-slate-50 rounded-3xl p-6 border border-slate-100 space-y-4">
                    <div className="flex items-center gap-4">
                        <div className="w-10 h-10 rounded-full bg-white text-teal-600 flex items-center justify-center shadow-sm"><Languages size={20}/></div>
                        <div>
                            <div className="text-xs font-bold text-slate-400 uppercase">Languages</div>
                            <div className="font-bold text-slate-900">{doctor.languages ? doctor.languages.join(', ') : 'English'}</div>
                        </div>
                    </div>
                    <div className="flex items-center gap-4">
                        <div className="w-10 h-10 rounded-full bg-white text-teal-600 flex items-center justify-center shadow-sm"><GraduationCap size={20}/></div>
                        <div>
                            <div className="text-xs font-bold text-slate-400 uppercase">Experience</div>
                            <div className="font-bold text-slate-900">{doctor.experience || '10+ Years'}</div>
                        </div>
                    </div>
                 </div>
             </div>

             {/* Right Column: Bio & Booking */}
             <div className="lg:col-span-8 flex flex-col">
                 <div className="mb-8">
                     <span className="inline-block px-4 py-1.5 rounded-full bg-teal-50 text-teal-700 font-bold text-sm mb-4 border border-teal-100">
                        {doctor.specialty}
                     </span>
                     <h1 className="text-5xl font-extrabold text-slate-900 mb-6 leading-tight">{doctor.name}</h1>
                     <div className="flex items-center gap-2 mb-8">
                        <StarRating avg={4.9} count={120} />
                     </div>
                     
                     <div className="prose prose-lg text-slate-500 leading-relaxed mb-10">
                        <h3 className="text-slate-900 font-bold text-xl mb-3">About</h3>
                        <p>{doctor.bio || 'A dedicated specialist committed to providing the highest quality of patient care.'}</p>
                     </div>

                     <div className="bg-slate-900 rounded-[2rem] p-8 text-white relative overflow-hidden">
                        <div className="absolute top-0 right-0 w-64 h-64 bg-teal-500 rounded-full blur-[100px] opacity-20 -mr-16 -mt-16 pointer-events-none"></div>
                        <div className="relative z-10 flex flex-col md:flex-row items-center justify-between gap-8">
                            <div>
                                <h3 className="text-2xl font-bold mb-2">Book an Appointment</h3>
                                <p className="text-slate-400">Schedule your consultation with {doctor.name} today.</p>
                            </div>
                            <Link to="/clinic/book" className="bg-teal-500 text-white px-8 py-4 rounded-xl font-bold hover:bg-teal-400 transition-all shadow-lg shadow-teal-500/30 whitespace-nowrap active:scale-95">
                                Book Now
                            </Link>
                        </div>
                     </div>
                 </div>
             </div>
          </div>
       </Container>
    </div>
  )
}
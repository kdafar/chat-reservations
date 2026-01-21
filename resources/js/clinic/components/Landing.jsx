import React from 'react'
import {
  Clock, Users, ShieldCheck, Phone, Stethoscope, Building2, Star, 
  HeartPulse, Activity, Sparkles, BadgeCheck, ArrowRight, Globe, Plane
} from 'lucide-react'
import { Container, SectionHeading } from './Shared'
import ClinicBookingWidget from './BookingWidget'

export function Hero() {
  return (
    <div className="relative w-full overflow-hidden bg-slate-900">
      {/* Background Image & Overlay */}
      <div className="absolute inset-0 z-0">
        <img
          src="https://images.unsplash.com/photo-1631217868264-e5b90bb7e133?q=80&w=2942&auto=format&fit=crop"
          alt="Medical Team Standing in Hospital"
          // Kept opacity high for visibility
          className="w-full h-full object-cover opacity-90 mix-blend-normal"
        />
        {/* Adjusted gradient: lighter 'via' value (60% instead of 95%) and transparent end */}
        <div className="absolute inset-0 bg-gradient-to-r from-slate-950 via-slate-900/70 to-transparent" />
        
        {/* Bottom fade */}
        <div className="absolute inset-0 bg-gradient-to-b from-transparent via-transparent to-slate-50" />
      </div>

      {/* Decorative Blobs - Reduced opacity slightly to not interfere with image */}
      <div className="absolute top-0 right-0 -mt-20 -mr-20 w-[600px] h-[600px] bg-teal-500/10 rounded-full blur-[120px] pointer-events-none" />
      <div className="absolute bottom-0 left-0 -mb-20 -ml-20 w-[400px] h-[400px] bg-blue-500/10 rounded-full blur-[100px] pointer-events-none" />

      <Container className="relative z-10 pt-32 pb-20 lg:pt-40 lg:pb-32">
        <div className="grid lg:grid-cols-12 gap-12 lg:gap-8 items-center">
          
          {/* Left Content */}
          <div className="lg:col-span-7 flex flex-col justify-center animate-in fade-in slide-in-from-left-8 duration-700">
            
            <div className="inline-flex items-center gap-2 self-start px-4 py-2 rounded-full bg-teal-950/80 border border-teal-500/30 text-teal-300 text-sm font-bold backdrop-blur-md mb-8 shadow-lg shadow-teal-900/20">
              <span className="relative flex h-2 w-2">
                <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-teal-400 opacity-75"></span>
                <span className="relative inline-flex rounded-full h-2 w-2 bg-teal-500"></span>
              </span>
              Accepting New Patients
            </div>

            <h1 className="text-5xl lg:text-7xl font-extrabold text-white leading-[1.1] mb-8 tracking-tight drop-shadow-lg">
              Healthcare that <br />
              <span className="text-transparent bg-clip-text bg-gradient-to-r from-teal-200 to-emerald-400">
                puts you first.
              </span>
            </h1>

            <p className="text-xl text-slate-100 mb-10 leading-relaxed max-w-xl font-medium drop-shadow-md">
              Experience a new standard of medical excellence. Skip the waiting room and book appointments with top-tier specialists instantly.
            </p>

            {/* Trust Badges */}
            <div className="flex flex-wrap gap-6 text-white border-t border-slate-800/50 pt-8 mt-4 backdrop-blur-sm rounded-xl p-2 -ml-2">
              <div className="flex items-center gap-3">
                 <div className="w-12 h-12 rounded-2xl bg-slate-900/80 flex items-center justify-center text-teal-400 ring-1 ring-slate-700 shadow-lg backdrop-blur-md">
                    <Clock size={20} strokeWidth={2.5} />
                 </div>
                 <div>
                    <div className="text-2xl font-bold leading-none drop-shadow-md">15<span className="text-sm font-medium text-slate-300 ml-1">min</span></div>
                    <div className="text-xs text-slate-300 font-bold uppercase tracking-wider mt-1 drop-shadow-sm">Avg. Wait Time</div>
                 </div>
              </div>
              
              <div className="w-px h-12 bg-slate-700/50 hidden sm:block"></div>

              <div className="flex items-center gap-3">
                 <div className="w-12 h-12 rounded-2xl bg-slate-900/80 flex items-center justify-center text-blue-400 ring-1 ring-slate-700 shadow-lg backdrop-blur-md">
                    <Users size={20} strokeWidth={2.5} />
                 </div>
                 <div>
                    <div className="text-2xl font-bold leading-none drop-shadow-md">50k<span className="text-sm font-medium text-slate-300 ml-1">+</span></div>
                    <div className="text-xs text-slate-300 font-bold uppercase tracking-wider mt-1 drop-shadow-sm">Happy Patients</div>
                 </div>
              </div>
            </div>
          </div>

          {/* Right Widget Area */}
          <div className="lg:col-span-5 flex justify-center lg:justify-end animate-in fade-in slide-in-from-bottom-12 duration-1000 delay-200 relative">
             {/* Glow effect behind widget */}
             <div className="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[120%] h-[120%] bg-teal-500/20 blur-[80px] rounded-full pointer-events-none"></div>
             <ClinicBookingWidget />
          </div>

        </div>
      </Container>
      
      {/* Bottom fade to white */}
      <div className="absolute bottom-0 left-0 w-full h-24 bg-gradient-to-t from-slate-50 to-transparent pointer-events-none"></div>
    </div>
  )
}

export function StatsSection() {
    const stats = [
       { num: '24/7', label: 'Emergency Care', icon: <Phone size={24} />, color: 'text-red-500', bg: 'bg-red-50' },
       { num: '100+', label: 'Specialist Doctors', icon: <Stethoscope size={24} />, color: 'text-blue-600', bg: 'bg-blue-50' },
       { num: '30+', label: 'Medical Depts', icon: <Building2 size={24} />, color: 'text-indigo-600', bg: 'bg-indigo-50' },
       { num: '4.9', label: 'Patient Rating', icon: <Star size={24} />, color: 'text-amber-500', bg: 'bg-amber-50' },
    ]

    return (
        <div className="bg-slate-50 pb-16 pt-8 relative z-10">
            <Container>
                <div className="grid grid-cols-2 lg:grid-cols-4 gap-6 lg:gap-8">
                   {stats.map((s,i) => (
                      <div key={i} className="group flex flex-col items-center text-center p-8 rounded-3xl bg-white shadow-sm border border-slate-100 hover:shadow-xl hover:-translate-y-1 transition-all duration-300">
                          <div className={`mb-4 w-14 h-14 ${s.bg} ${s.color} rounded-2xl flex items-center justify-center transition-transform group-hover:scale-110 duration-300`}>
                              {s.icon}
                          </div>
                          <div className="text-4xl font-extrabold text-slate-900 mb-2 tracking-tight">{s.num}</div>
                          <div className="text-sm font-bold text-slate-500 uppercase tracking-widest">{s.label}</div>
                      </div>
                   ))}
                </div>
            </Container>
        </div>
    )
}

export function LandingServicesSection() {
   const services = [
      { icon: <HeartPulse />, title: 'Heart Center', desc: 'Comprehensive cardiology care from diagnosis to rehabilitation.' },
      { icon: <Activity />, title: 'Neurology', desc: 'Advanced treatment for disorders of the nervous system.' },
      { icon: <Users />, title: 'Pediatrics', desc: 'Compassionate, specialized healthcare for infants and children.' },
      { icon: <Stethoscope />, title: 'Primary Care', desc: 'Your partner in maintaining overall health and wellness.' },
      { icon: <Sparkles />, title: 'Dermatology', desc: 'Expert care for skin, hair, and nail conditions.' },
      { icon: <ShieldCheck />, title: 'Orthopedics', desc: 'Restoring mobility with advanced joint and bone care.' },
   ]

   return (
      <section className="py-24 bg-white relative overflow-hidden">
         <div className="absolute top-0 left-0 w-full h-full opacity-[0.03]" style={{backgroundImage: 'radial-gradient(#0f172a 1px, transparent 1px)', backgroundSize: '32px 32px'}}></div>
         
         <Container className="relative z-10">
            <div className="text-center max-w-3xl mx-auto mb-16">
                <h2 className="text-sm font-bold text-teal-600 tracking-widest uppercase mb-3">Departments</h2>
                <h3 className="text-4xl md:text-5xl font-extrabold text-slate-900 mb-6">Medical Excellence</h3>
                <p className="text-lg text-slate-500">World-class specialized departments ready to serve your needs.</p>
            </div>

            <div className="grid md:grid-cols-2 lg:grid-cols-3 gap-8">
               {services.map((s, i) => (
                  <div key={i} className="group bg-slate-50 p-8 rounded-[2rem] border border-slate-100 hover:bg-white hover:shadow-xl hover:shadow-slate-200/50 hover:-translate-y-2 transition-all duration-300 cursor-pointer">
                      <div className="flex items-start justify-between mb-8">
                          <div className="w-16 h-16 rounded-2xl bg-white text-teal-600 border border-slate-100 flex items-center justify-center group-hover:bg-teal-600 group-hover:text-white group-hover:scale-110 transition-all duration-300 shadow-sm">
                             {React.cloneElement(s.icon, { size: 30, strokeWidth: 1.5 })}
                          </div>
                          <div className="w-10 h-10 rounded-full bg-transparent flex items-center justify-center text-slate-300 group-hover:text-teal-600 group-hover:bg-teal-50 transition-all">
                              <ArrowRight size={20} className="-rotate-45 group-hover:rotate-0 transition-transform duration-300" />
                          </div>
                      </div>
                      
                      <h3 className="text-2xl font-bold text-slate-900 mb-3">{s.title}</h3>
                      <p className="text-slate-500 leading-relaxed font-medium">{s.desc}</p>
                  </div>
               ))}
            </div>
            
            <div className="mt-16 text-center">
                <button className="inline-flex items-center gap-2 px-8 py-4 rounded-full bg-slate-900 text-white font-bold hover:bg-slate-800 transition-all shadow-lg hover:shadow-xl active:scale-95">
                    View All Departments <ArrowRight size={18} />
                </button>
            </div>
         </Container>
      </section>
   )
}

export function InfoSection() {
    return (
        <section className="py-20 bg-slate-50">
            <Container>
                <div className="bg-gradient-to-br from-slate-900 via-slate-800 to-slate-900 rounded-[3rem] p-10 md:p-20 text-white relative overflow-hidden shadow-2xl shadow-slate-200/50">
                    
                    {/* Background Pattern */}
                    <div className="absolute top-0 right-0 w-[600px] h-[600px] bg-teal-500/10 rounded-full blur-[100px] -mr-20 -mt-20 pointer-events-none"></div>
                    <div className="absolute inset-0 bg-[url('https://www.transparenttextures.com/patterns/stardust.png')] opacity-10"></div>

                    <div className="relative z-10 grid lg:grid-cols-2 gap-16 items-center">
                        <div className="max-w-xl">
                            <div className="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-white/10 backdrop-blur-md text-teal-300 font-bold text-xs uppercase tracking-wider mb-8 border border-white/5">
                                <Globe size={14} /> International Patients
                            </div>
                            <h2 className="text-4xl md:text-6xl font-extrabold mb-8 leading-[1.1] tracking-tight">
                                Traveling for <br/>
                                <span className="text-transparent bg-clip-text bg-gradient-to-r from-teal-200 to-blue-200">Treatment?</span>
                            </h2>
                            <p className="text-slate-300 text-lg md:text-xl mb-10 leading-relaxed font-medium">
                                We make medical tourism seamless. From visa assistance to luxury accommodation, our dedicated team handles every detail of your journey.
                            </p>
                            
                            <div className="flex flex-col sm:flex-row gap-4">
                                <button className="inline-flex items-center justify-center gap-2 px-8 py-4 rounded-xl bg-teal-500 text-white font-bold hover:bg-teal-400 transition-all shadow-lg shadow-teal-900/20 active:scale-95">
                                    <Plane size={20} /> Plan Your Trip
                                </button>
                                <button className="inline-flex items-center justify-center gap-2 px-8 py-4 rounded-xl bg-white/5 text-white font-bold border border-white/10 hover:bg-white/10 transition-all active:scale-95">
                                    Contact Support
                                </button>
                            </div>
                        </div>

                        <div className="hidden lg:block relative">
                            <div className="relative z-10 bg-slate-800/50 backdrop-blur-xl p-8 rounded-3xl border border-white/10 shadow-2xl transform rotate-3 hover:rotate-0 transition-all duration-500">
                                <div className="flex items-center gap-4 mb-6">
                                    <div className="w-12 h-12 rounded-full bg-teal-500 flex items-center justify-center text-white font-bold shadow-lg">
                                        <Plane size={24} />
                                    </div>
                                    <div>
                                        <div className="font-bold text-lg">Concierge Service</div>
                                        <div className="text-slate-400 text-sm">Dedicated 24/7 Support</div>
                                    </div>
                                </div>
                                <div className="space-y-4">
                                    {['Visa Assistance', 'Airport Pickup', 'Luxury Accommodation', 'Language Translation'].map((item, i) => (
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
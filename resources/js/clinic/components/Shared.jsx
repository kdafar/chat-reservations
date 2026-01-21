import React, { useEffect, useState } from 'react'
import { Link, NavLink, useLocation } from 'react-router-dom'
import {
  HeartPulse, Phone, Menu, MapPin, ChevronDown, Star, ArrowRight, X
} from 'lucide-react'
import { GULF_CODES } from '../api'

// --- Atoms ---

export function Container({ children, className = '' }) {
  return <div className={`max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 ${className}`}>{children}</div>
}

export function SectionHeading({ title, subtitle, center = true }) {
  return (
    <div className={`mb-16 ${center ? 'text-center' : 'text-left'}`}>
      <h2 className="text-3xl md:text-5xl font-bold text-slate-900 tracking-tight leading-tight">{title}</h2>
      {subtitle && (
        <div className={`mt-4 max-w-2xl text-lg text-slate-500 leading-relaxed ${center ? 'mx-auto' : ''}`}>
          {subtitle}
        </div>
      )}
    </div>
  )
}

export function PageShell({ title, subtitle, children }) {
  return (
    <div className="pt-32 pb-16 bg-white min-h-screen">
      <Container>
        <div className="mb-12">
            <h2 className="text-4xl md:text-5xl font-extrabold text-slate-900 tracking-tight leading-tight mb-4">{title}</h2>
            {subtitle && (
                <div className="max-w-3xl text-xl text-slate-500 leading-relaxed">
                {subtitle}
                </div>
            )}
        </div>
        {children}
      </Container>
    </div>
  )
}

export function Pill({ children }) {
  return (
    <span className="inline-flex items-center rounded-full px-3 py-1 text-xs font-bold bg-slate-100 text-slate-600">
      {children}
    </span>
  )
}

export function StarRating({ avg = 0, count = 0 }) {
  const a = Number(avg || 0)
  return (
    <div className="flex items-center gap-2 text-sm">
      <div className="inline-flex items-center gap-1 text-amber-500 font-bold bg-amber-50 px-2 py-0.5 rounded-md">
        <Star size={14} fill="currentColor" />
        <span>{a.toFixed(1)}</span>
      </div>
      <span className="text-slate-400 font-medium">({count} reviews)</span>
    </div>
  )
}

export function PhoneInput({ value, onChange, code, onCodeChange }) {
  return (
    <div className="flex rounded-xl bg-slate-50 border-2 border-slate-100 focus-within:border-teal-500 focus-within:ring-4 focus-within:ring-teal-500/10 transition-all overflow-hidden h-14">
      <div className="relative flex items-center bg-slate-100 px-4 hover:bg-slate-200 transition-colors cursor-pointer group border-r border-slate-200 min-w-[110px]">
        <span className="text-xl mr-2">{GULF_CODES.find(c => c.code === code)?.flag}</span>
        <span className="text-sm font-bold text-slate-700 mr-1">{code}</span>
        <ChevronDown size={14} className="text-slate-400 group-hover:text-slate-600 ml-auto" />
        <select
          value={code}
          onChange={e => onCodeChange(e.target.value)}
          className="absolute inset-0 w-full h-full opacity-0 cursor-pointer"
        >
          {GULF_CODES.map(c => (
            <option key={c.code} value={c.code}>
              {c.flag} {c.name} ({c.code})
            </option>
          ))}
        </select>
      </div>
      <input
        type="tel"
        inputMode="numeric"
        value={value}
        onChange={onChange}
        placeholder="5000 0000"
        className="flex-1 w-full bg-transparent px-4 outline-none font-bold text-slate-900 placeholder:text-slate-300 text-lg placeholder:font-medium"
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

  // Close mobile menu when route changes
  useEffect(() => {
    setMobileMenuOpen(false)
  }, [location.pathname])

  const isLanding = location.pathname === '/clinic/book' || location.pathname === '/';

  const linkClass = ({ isActive }) =>
    `hover:text-teal-500 transition-colors relative group font-bold text-sm ${isActive ? 'text-teal-500' : ''}`

  return (
    <>
      <nav className={`fixed top-0 left-0 right-0 z-50 transition-all duration-300 ${scrolled || !isLanding || mobileMenuOpen ? 'bg-white/95 backdrop-blur-xl shadow-sm py-4 border-b border-slate-100' : 'bg-transparent py-6'}`}>
        <Container className="flex items-center justify-between">
          <Link to="/clinic/book" className="flex items-center gap-3 group">
            <div className={`p-2.5 rounded-xl shadow-lg transition-transform group-hover:scale-105 duration-300 ${scrolled || !isLanding || mobileMenuOpen ? 'bg-gradient-to-br from-teal-500 to-emerald-600 text-white shadow-teal-500/30' : 'bg-white/10 backdrop-blur-md text-white border border-white/20'}`}>
              <HeartPulse size={24} />
            </div>
            <div>
              <div className={`font-bold text-xl tracking-tight leading-none ${scrolled || !isLanding || mobileMenuOpen ? 'text-slate-900' : 'text-slate-900 lg:text-white'}`}>
                Med<span className="text-teal-500">Care</span>
              </div>
              <div className={`text-[10px] font-bold tracking-widest uppercase mt-0.5 ${scrolled || !isLanding || mobileMenuOpen ? 'text-slate-400' : 'text-slate-400 lg:text-slate-300'}`}>Medical Center</div>
            </div>
          </Link>

          <div className={`hidden md:flex items-center gap-8 ${scrolled || !isLanding ? 'text-slate-600' : 'text-slate-200'}`}>
            <NavLink to="/clinic/clinics" className={linkClass}>Clinics</NavLink>
            <NavLink to="/clinic/services" className={linkClass}>Services</NavLink>
            <a href="#contact" className="hover:text-teal-500 transition-colors relative group font-bold text-sm">Contact</a>
          </div>

          <div className="flex items-center gap-3">
            <button className={`hidden md:flex items-center gap-2 px-5 py-2.5 rounded-full font-bold text-sm transition-all ${scrolled || !isLanding ? 'bg-slate-900 text-white hover:bg-slate-800 shadow-lg shadow-slate-200' : 'bg-white text-teal-900 hover:bg-teal-50'}`}>
              <Phone size={16} /> 1800-MED-CARE
            </button>
            <button 
                onClick={() => setMobileMenuOpen(!mobileMenuOpen)}
                className={`md:hidden p-2 rounded-lg transition-colors ${scrolled || !isLanding || mobileMenuOpen ? 'text-slate-600 hover:bg-slate-100' : 'text-white hover:bg-white/10'}`}
            >
              {mobileMenuOpen ? <X size={24} /> : <Menu size={24} />}
            </button>
          </div>
        </Container>
      </nav>

      {/* Mobile Menu Overlay */}
      {mobileMenuOpen && (
        <div className="fixed inset-0 z-40 bg-white pt-24 px-6 md:hidden animate-in fade-in slide-in-from-top-10 duration-200">
            <div className="flex flex-col gap-6 text-xl font-bold text-slate-900">
                <NavLink to="/clinic/clinics" className="flex items-center justify-between border-b border-slate-100 pb-4">
                    Clinics <ArrowRight size={20} className="text-slate-300" />
                </NavLink>
                <NavLink to="/clinic/services" className="flex items-center justify-between border-b border-slate-100 pb-4">
                    Services <ArrowRight size={20} className="text-slate-300" />
                </NavLink>
                <a href="#contact" onClick={() => setMobileMenuOpen(false)} className="flex items-center justify-between border-b border-slate-100 pb-4">
                    Contact <ArrowRight size={20} className="text-slate-300" />
                </a>
            </div>
            <button className="w-full mt-8 bg-teal-600 text-white py-4 rounded-xl font-bold flex items-center justify-center gap-2">
                <Phone size={20} /> Call 1800-MED-CARE
            </button>
        </div>
      )}
    </>
  )
}

export function Footer() {
  return (
     <footer id="contact" className="bg-slate-50 pt-24 pb-12 border-t border-slate-200">
        <Container>
           <div className="grid md:grid-cols-12 gap-12 mb-16">
              <div className="md:col-span-5">
                 <div className="flex items-center gap-3 mb-6">
                    <div className="bg-gradient-to-br from-teal-500 to-emerald-600 text-white p-2.5 rounded-xl shadow-lg shadow-teal-500/20">
                       <HeartPulse size={24} />
                    </div>
                    <div className="font-bold text-2xl tracking-tight text-slate-900">
                       Med<span className="text-teal-600">Care</span>
                    </div>
                 </div>
                 <p className="text-slate-500 text-lg leading-relaxed mb-8 max-w-md">
                    Empowering lives through compassionate care and cutting-edge medical innovation. We are here for you, every step of the way.
                 </p>
                 <div className="flex gap-4">
                     {/* Social placeholders */}
                     {[1,2,3].map(i => (
                         <div key={i} className="w-10 h-10 rounded-full bg-white border border-slate-200 flex items-center justify-center text-slate-400 hover:text-teal-600 hover:border-teal-600 hover:bg-teal-50 transition-all cursor-pointer">
                             <div className="w-4 h-4 bg-current rounded-sm opacity-50"></div>
                         </div>
                     ))}
                 </div>
              </div>
              
              <div className="md:col-span-2">
                 <h4 className="font-bold text-slate-900 text-lg mb-6">Patient</h4>
                 <ul className="space-y-4 text-slate-500 font-medium">
                    <li><a href="#" className="hover:text-teal-600 transition-colors">Find a Doctor</a></li>
                    <li><a href="#" className="hover:text-teal-600 transition-colors">Our Specialties</a></li>
                    <li><a href="#" className="hover:text-teal-600 transition-colors">Insurance Accepted</a></li>
                    <li><a href="#" className="hover:text-teal-600 transition-colors">Patient Portal</a></li>
                 </ul>
              </div>
              
              <div className="md:col-span-3">
                 <h4 className="font-bold text-slate-900 text-lg mb-6">Contact Us</h4>
                 <ul className="space-y-6 text-slate-500 font-medium">
                    <li className="flex items-start gap-4">
                       <MapPin size={20} className="mt-1 text-teal-600 shrink-0" />
                       <span>123 Medical District Ave,<br/>Central City, 50210</span>
                    </li>
                    <li className="flex items-center gap-4">
                       <Phone size={20} className="text-teal-600 shrink-0" />
                       <span>+1 (800) 123-4567</span>
                    </li>
                 </ul>
              </div>
           </div>
           
           <div className="pt-8 border-t border-slate-200 flex flex-col md:flex-row justify-between items-center text-slate-400 font-medium text-sm gap-4">
              <div>© {new Date().getFullYear()} MedCare Hospital Group. All rights reserved.</div>
              <div className="flex gap-6">
                  <a href="#" className="hover:text-slate-600">Privacy Policy</a>
                  <a href="#" className="hover:text-slate-600">Terms of Service</a>
              </div>
           </div>
        </Container>
     </footer>
  )
}
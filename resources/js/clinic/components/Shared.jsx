import React, { useEffect, useRef, useState } from 'react'
import { Link, NavLink, useLocation } from 'react-router-dom'
import {
  Sparkles, Phone, Menu, MapPin, ChevronDown, Star, ArrowRight, X, Search, Globe, Check, Mail, Instagram, Music2, Ghost
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
      className={`inline-flex items-center gap-1.5 px-3 py-2 rounded-full font-bold text-sm transition-all ${solid ? 'text-slate-600 hover:bg-slate-100' : 'text-white/90 hover:bg-white/10'} ${className}`}
    >
      <Globe size={16} /> {label}
    </a>
  )
}

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

// --- Brand logo lockup (used in navbar + footer) ---
export function BrandLogo({ light = false }) {
  return (
    <Link to="/clinic/book" className="flex items-center gap-3 group">
      {CLINIC.logo ? (
        <img src={CLINIC.logo} alt={tr(CLINIC.name)} className="w-11 h-11 rounded-xl object-cover shadow-lg transition-transform group-hover:scale-105 duration-300" />
      ) : (
        <div className={`p-2.5 rounded-xl shadow-lg transition-transform group-hover:scale-105 duration-300 ${light ? 'bg-white/10 backdrop-blur-md text-white border border-white/20' : 'bg-gradient-to-br from-teal-500 to-emerald-600 text-white shadow-teal-500/30'}`}>
          <Sparkles size={24} />
        </div>
      )}
      <div>
        <div className={`font-bold text-xl tracking-tight leading-none ${light ? 'text-slate-900 lg:text-white' : 'text-slate-900'}`}>
          {tr(CLINIC.name)}
        </div>
        <div className={`text-[10px] font-bold tracking-widest uppercase mt-0.5 ${light ? 'text-slate-400 lg:text-slate-300' : 'text-slate-400'}`}>
          {tr(CLINIC.kicker)}
        </div>
      </div>
    </Link>
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
        className={`w-full h-14 rounded-xl bg-slate-50 border px-4 flex items-center gap-2 font-semibold text-start transition-all outline-none ${open ? 'border-teal-500 ring-4 ring-teal-500/10' : 'border-slate-200 hover:border-slate-300'}`}
      >
        {icon && <span className="text-slate-400 shrink-0">{icon}</span>}
        <span className={`flex-1 truncate ${selected ? 'text-slate-800' : 'text-slate-400'}`}>
          {selected ? selected.label : placeholder}
        </span>
        <ChevronDown size={18} className={`text-slate-400 shrink-0 transition-transform ${open ? 'rotate-180' : ''}`} />
      </button>

      {open && (
        <div className="absolute z-50 mt-2 w-full bg-white rounded-2xl border border-slate-200 shadow-2xl shadow-slate-300/40 overflow-hidden animate-in fade-in slide-in-from-top-2 duration-150">
          {searchable && (
            <div className="p-2 border-b border-slate-100">
              <div className="relative">
                <Search size={16} className="absolute top-1/2 -translate-y-1/2 start-3 text-slate-400" />
                <input
                  ref={inputRef}
                  value={q}
                  onChange={e => setQ(e.target.value)}
                  placeholder={tr(S.select.search)}
                  className="w-full h-10 rounded-lg bg-slate-50 border border-slate-200 ps-9 pe-3 text-sm font-medium outline-none focus:border-teal-500"
                />
              </div>
            </div>
          )}
          <div className="max-h-64 overflow-y-auto py-1">
            {filtered.length === 0 && (
              <div className="px-4 py-6 text-center text-sm text-slate-400 font-medium">{tr(S.select.none)}</div>
            )}
            {filtered.map(o => {
              const isSel = String(o.value) === String(value)
              return (
                <button
                  type="button"
                  key={`${o.value}`}
                  onClick={() => { onChange(o.value); setOpen(false); setQ('') }}
                  className={`w-full text-start px-4 py-2.5 flex items-center gap-2 text-sm font-semibold transition-colors ${isSel ? 'bg-teal-50 text-teal-700' : 'text-slate-700 hover:bg-slate-50'}`}
                >
                  <span className="flex-1 truncate">{o.label}</span>
                  {isSel && <Check size={16} className="text-teal-600 shrink-0" />}
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

  useEffect(() => { setMobileMenuOpen(false) }, [location.pathname])

  const isLanding = location.pathname === '/clinic/book' || location.pathname === '/';
  const solid = scrolled || !isLanding || mobileMenuOpen

  const linkClass = ({ isActive }) =>
    `hover:text-teal-500 transition-colors relative group font-bold text-sm ${isActive ? 'text-teal-500' : ''}`

  return (
    <>
      <nav className={`fixed top-0 left-0 right-0 z-50 transition-all duration-300 ${solid ? 'bg-white/95 backdrop-blur-xl shadow-sm py-4 border-b border-slate-100' : 'bg-transparent py-6'}`}>
        <Container className="flex items-center justify-between">
          <BrandLogo light={!solid} />

          <div className={`hidden md:flex items-center gap-8 ${solid ? 'text-slate-600' : 'text-slate-700 lg:text-slate-200'}`}>
            <NavLink to="/clinic/clinics" className={linkClass}>{tr(S.nav.clinics)}</NavLink>
            <NavLink to="/clinic/services" className={linkClass}>{tr(S.nav.services)}</NavLink>
            <a href="#contact" className="hover:text-teal-500 transition-colors relative group font-bold text-sm">{tr(S.nav.contact)}</a>
          </div>

          <div className="flex items-center gap-2 sm:gap-3">
            <LangSwitcher solid={solid} />
            {CLINIC.phone ? (
              <a href={`tel:${CLINIC.phone}`} className={`hidden md:flex items-center gap-2 px-5 py-2.5 rounded-full font-bold text-sm transition-all ${solid ? 'bg-slate-900 text-white hover:bg-slate-800 shadow-lg shadow-slate-200' : 'bg-white text-teal-900 hover:bg-teal-50'}`}>
                <Phone size={16} /> {CLINIC.phone}
              </a>
            ) : (
              <Link to="/clinic/clinics" className={`hidden md:flex items-center gap-2 px-5 py-2.5 rounded-full font-bold text-sm transition-all ${solid ? 'bg-slate-900 text-white hover:bg-slate-800 shadow-lg shadow-slate-200' : 'bg-white text-teal-900 hover:bg-teal-50'}`}>
                {tr(S.nav.bookNow)} <ArrowRight size={16} />
              </Link>
            )}
            <button
                onClick={() => setMobileMenuOpen(!mobileMenuOpen)}
                className={`md:hidden p-2 rounded-lg transition-colors ${solid ? 'text-slate-600 hover:bg-slate-100' : 'text-white hover:bg-white/10'}`}
            >
              {mobileMenuOpen ? <X size={24} /> : <Menu size={24} />}
            </button>
          </div>
        </Container>
      </nav>

      {mobileMenuOpen && (
        <div className="fixed inset-0 z-40 bg-white pt-24 px-6 md:hidden animate-in fade-in slide-in-from-top-10 duration-200">
            <div className="flex flex-col gap-6 text-xl font-bold text-slate-900">
                <NavLink to="/clinic/clinics" className="flex items-center justify-between border-b border-slate-100 pb-4">
                    {tr(S.nav.clinics)} <ArrowRight size={20} className="text-slate-300" />
                </NavLink>
                <NavLink to="/clinic/services" className="flex items-center justify-between border-b border-slate-100 pb-4">
                    {tr(S.nav.services)} <ArrowRight size={20} className="text-slate-300" />
                </NavLink>
                <a href="#contact" onClick={() => setMobileMenuOpen(false)} className="flex items-center justify-between border-b border-slate-100 pb-4">
                    {tr(S.nav.contact)} <ArrowRight size={20} className="text-slate-300" />
                </a>
            </div>
            {CLINIC.phone ? (
              <a href={`tel:${CLINIC.phone}`} className="w-full mt-8 bg-teal-600 text-white py-4 rounded-xl font-bold flex items-center justify-center gap-2">
                  <Phone size={20} /> {CLINIC.phone}
              </a>
            ) : (
              <Link to="/clinic/clinics" className="w-full mt-8 bg-teal-600 text-white py-4 rounded-xl font-bold flex items-center justify-center gap-2">
                  {tr(S.nav.bookNow)} <ArrowRight size={20} />
              </Link>
            )}
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
                    {CLINIC.logo ? (
                      <img src={CLINIC.logo} alt={tr(CLINIC.name)} className="w-11 h-11 rounded-xl object-cover shadow-lg" />
                    ) : (
                      <div className="bg-gradient-to-br from-teal-500 to-emerald-600 text-white p-2.5 rounded-xl shadow-lg shadow-teal-500/20">
                         <Sparkles size={24} />
                      </div>
                    )}
                    <div className="font-bold text-2xl tracking-tight text-slate-900">{tr(CLINIC.name)}</div>
                 </div>
                 <p className="text-slate-500 text-lg leading-relaxed mb-8 max-w-md">
                    {tr(S.footer.tagline)}
                 </p>
                 <div className="flex gap-4">
                     {[
                       { url: CLINIC.social.instagram, Icon: Instagram },
                       { url: CLINIC.social.tiktok, Icon: Music2 },
                       { url: CLINIC.social.snapchat, Icon: Ghost },
                     ].filter(s => s.url).map(({ url, Icon }, i) => (
                       <a key={i} href={url} target="_blank" rel="noreferrer" className="w-10 h-10 rounded-full bg-white border border-slate-200 flex items-center justify-center text-slate-400 hover:text-teal-600 hover:border-teal-600 hover:bg-teal-50 transition-all">
                           <Icon size={18} />
                       </a>
                     ))}
                 </div>
              </div>

              <div className="md:col-span-3">
                 <h4 className="font-bold text-slate-900 text-lg mb-6">{tr(S.footer.exploreTitle)}</h4>
                 <ul className="space-y-4 text-slate-500 font-medium">
                    <li><Link to="/clinic/clinics" className="hover:text-teal-600 transition-colors">{tr(S.nav.clinics)}</Link></li>
                    <li><Link to="/clinic/services" className="hover:text-teal-600 transition-colors">{tr(S.nav.services)}</Link></li>
                    <li><Link to="/clinic/book" className="hover:text-teal-600 transition-colors">{tr(S.nav.bookNow)}</Link></li>
                 </ul>
              </div>

              <div className="md:col-span-4">
                 <h4 className="font-bold text-slate-900 text-lg mb-6">{tr(S.footer.contactTitle)}</h4>
                 <ul className="space-y-6 text-slate-500 font-medium">
                    <li className="flex items-start gap-4">
                       <MapPin size={20} className="mt-1 text-teal-600 shrink-0" />
                       <span>{tr(CLINIC.address)}</span>
                    </li>
                    {CLINIC.phone && (
                      <li className="flex items-center gap-4">
                         <Phone size={20} className="text-teal-600 shrink-0" />
                         <a href={`tel:${CLINIC.phone}`} className="hover:text-teal-600" dir="ltr">{CLINIC.phone}</a>
                      </li>
                    )}
                    <li className="flex items-center gap-4">
                       <Mail size={20} className="text-teal-600 shrink-0" />
                       <a href={`mailto:${CLINIC.email}`} className="hover:text-teal-600" dir="ltr">{CLINIC.email}</a>
                    </li>
                    <li className="flex items-center gap-4">
                       <Globe size={20} className="text-teal-600 shrink-0" />
                       <a href={CLINIC.websiteUrl} target="_blank" rel="noreferrer" className="hover:text-teal-600" dir="ltr">{CLINIC.website}</a>
                    </li>
                 </ul>
              </div>
           </div>

           <div className="pt-8 border-t border-slate-200 flex flex-col md:flex-row justify-between items-center text-slate-400 font-medium text-sm gap-4">
              <div>© {new Date().getFullYear()} {tr(CLINIC.name)}. {tr(S.footer.rights)}</div>
              <div className="flex gap-6">
                  <a href="#" className="hover:text-slate-600">{tr(S.footer.privacy)}</a>
                  <a href="#" className="hover:text-slate-600">{tr(S.footer.terms)}</a>
              </div>
           </div>
        </Container>
     </footer>
  )
}

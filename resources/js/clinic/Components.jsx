import React, { useEffect, useMemo, useState } from 'react'
import { Link, NavLink, useLocation } from 'react-router-dom'
import {
  Activity, ArrowRight, ArrowLeft, BadgeCheck, Building2, Calendar, CalendarDays,
  Check, ChevronDown, Clock, HeartPulse, Info, Loader2, MapPin, Menu, Phone,
  Search, ShieldCheck, Sparkles, Star, Stethoscope, User, Users, X, AlertCircle, FileText, Trash2
} from 'lucide-react'
import { Api, getLocale, t, formatDateDisplay, formatTimeDisplay, GULF_CODES } from './api'

// --- Layout Partials ---

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
    <div className="pt-28 pb-16 bg-white min-h-screen">
      <Container>
        <div className="mb-10">
            <h2 className="text-3xl md:text-5xl font-bold text-slate-900 tracking-tight leading-tight">{title}</h2>
            {subtitle && (
                <div className="mt-4 max-w-2xl text-lg text-slate-500 leading-relaxed">
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
      <div className="inline-flex items-center gap-1 text-amber-500 font-bold">
        <Star size={16} fill="currentColor" />
        <span>{a.toFixed(1)}</span>
      </div>
      <span className="text-slate-400">({count})</span>
    </div>
  )
}

export function PhoneInput({ value, onChange, code, onCodeChange }) {
  return (
    <div className="flex rounded-xl bg-slate-50 border border-slate-200 focus-within:ring-2 focus-within:ring-teal-500 focus-within:border-teal-500 transition-all overflow-hidden h-14">
      <div className="relative flex items-center bg-slate-100 px-4 hover:bg-slate-200 transition-colors cursor-pointer group border-r border-slate-200">
        <span className="text-xl mr-2">{GULF_CODES.find(c => c.code === code)?.flag}</span>
        <ChevronDown size={14} className="text-slate-400 group-hover:text-slate-600" />
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
        className="flex-1 w-full bg-transparent px-4 outline-none font-semibold text-slate-900 placeholder:text-slate-400 text-lg"
      />
    </div>
  )
}

// --- Navbar ---

export function Navbar() {
  const [scrolled, setScrolled] = useState(false)
  const location = useLocation();

  useEffect(() => {
    const handler = () => setScrolled(window.scrollY > 20)
    window.addEventListener('scroll', handler)
    return () => window.removeEventListener('scroll', handler)
  }, [])

  const isLanding = location.pathname === '/clinic/book' || location.pathname === '/';

  const linkClass = ({ isActive }) =>
    `hover:text-teal-500 transition-colors relative group ${isActive ? 'text-teal-500' : ''}`

  return (
    <nav className={`fixed top-0 left-0 right-0 z-50 transition-all duration-300 ${scrolled || !isLanding ? 'bg-white/95 backdrop-blur-xl shadow-sm py-3 border-b border-slate-100' : 'bg-transparent py-5'}`}>
      <Container className="flex items-center justify-between">
        <Link to="/clinic/book" className="flex items-center gap-3">
          <div className="bg-gradient-to-br from-teal-500 to-emerald-600 text-white p-2.5 rounded-xl shadow-lg shadow-teal-500/30">
            <HeartPulse size={24} />
          </div>
          <div>
            <div className={`font-bold text-xl tracking-tight leading-none ${scrolled || !isLanding ? 'text-slate-900' : 'text-slate-900 lg:text-white'}`}>
              Med<span className="text-teal-500">Care</span>
            </div>
            <div className={`text-[10px] font-bold tracking-widest uppercase ${scrolled || !isLanding ? 'text-slate-400' : 'text-slate-400 lg:text-slate-300'}`}>Medical Center</div>
          </div>
        </Link>

        <div className={`hidden md:flex items-center gap-8 font-bold text-sm ${scrolled || !isLanding ? 'text-slate-600' : 'text-slate-200'}`}>
          <NavLink to="/clinic/clinics" className={linkClass}>Clinics</NavLink>
          <NavLink to="/clinic/services" className={linkClass}>Services</NavLink>
          <a href="#contact" className="hover:text-teal-500 transition-colors relative group">Contact</a>
        </div>

        <div className="flex items-center gap-3">
          <button className={`hidden md:flex items-center gap-2 px-4 py-2 rounded-full font-bold text-sm transition-all ${scrolled || !isLanding ? 'bg-slate-100 text-slate-600 hover:bg-slate-200' : 'bg-white/10 text-white hover:bg-white/20'}`}>
            <Phone size={16} /> 1800-MED-CARE
          </button>
          <button className="md:hidden p-2 text-slate-600 bg-white rounded-lg shadow-sm">
            <Menu size={24} />
          </button>
        </div>
      </Container>
    </nav>
  )
}

// --- Footer ---

export function Footer() {
  return (
     <footer id="contact" className="bg-slate-50 pt-24 pb-12 border-t border-slate-200">
        <Container>
           <div className="grid md:grid-cols-12 gap-12 mb-16">
              <div className="md:col-span-5">
                 <div className="flex items-center gap-3 mb-8">
                    <div className="bg-teal-600 text-white p-2 rounded-lg">
                       <HeartPulse size={24} />
                    </div>
                    <div className="font-bold text-2xl tracking-tight text-slate-900">
                       MedCare
                    </div>
                 </div>
                 <p className="text-slate-500 text-lg leading-relaxed mb-8 max-w-md">
                    Empowering lives through compassionate care and cutting-edge medical innovation. We are here for you, every step of the way.
                 </p>
              </div>
              
              <div className="md:col-span-2">
                 <h4 className="font-bold text-slate-900 text-lg mb-6">Patient</h4>
                 <ul className="space-y-4 text-slate-500 font-medium">
                    <li><a href="#" className="hover:text-teal-600 transition-colors">Doctors</a></li>
                    <li><a href="#" className="hover:text-teal-600 transition-colors">Specialties</a></li>
                    <li><a href="#" className="hover:text-teal-600 transition-colors">Insurance</a></li>
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
           
           <div className="pt-8 border-t border-slate-200 flex flex-col md:flex-row justify-between items-center text-slate-400 font-medium text-sm">
              <div>© {new Date().getFullYear()} MedCare Hospital Group.</div>
           </div>
        </Container>
     </footer>
  )
}

// --- Booking Widget ---

export function ClinicBookingWidget() {
  const locale = useMemo(() => getLocale(), [])
  const [view, setView] = useState('book')
  const [step, setStep] = useState(1)
  const [loading, setLoading] = useState(false)
  const [initLoading, setInitLoading] = useState(true)
  const [error, setError] = useState(null)

  const [partners, setPartners] = useState([])
  const [branches, setBranches] = useState([])
  const [doctors, setDoctors] = useState([])
  const [availableSlots, setAvailableSlots] = useState([])

  const [branchesLoading, setBranchesLoading] = useState(false)
  const [doctorsLoading, setDoctorsLoading] = useState(false)
  const [slotsLoading, setSlotsLoading] = useState(false)

  const [completedBooking, setCompletedBooking] = useState(null)
  const [manageSuccess, setManageSuccess] = useState(false)

  const [formData, setFormData] = useState({
    partner_id: null,
    branch_id: null,
    doctor_id: null,
    party_size: 1,
    res_date: '',
    res_time: '',
    name: '',
    notes: '',
  })
  const [phoneState, setPhoneState] = useState({ code: '+965', number: '' })
  const [bookingRef, setBookingRef] = useState('')

  useEffect(() => {
    Api.getPartners()
      .then((data) => {
        setPartners(data || [])
        // IMPORTANT: Only auto-select if there is exactly ONE partner.
        // If there are multiple, we leave it null so we can fetch ALL branches.
        if (data?.length === 1) setFormData(p => ({ ...p, partner_id: data[0].id }))
      })
      .catch(console.error)
      .finally(() => setInitLoading(false))
  }, [])

  // --- FIXED BRANCH FETCHING LOGIC ---
  useEffect(() => {
    if (initLoading) return;

    setBranchesLoading(true)
    
    // Pass null if no specific partner selected.
    // The Backend API is designed to return ALL available branches if partner_id is null.
    const pid = formData.partner_id;

    Api.getBranches(pid)
      .then(data => {
        setBranches(data || [])
        // If we found branches and user hasn't picked one, don't auto-pick unless only 1 exists
        if (data?.length === 1) setFormData(p => ({ ...p, branch_id: data[0].id }))
      })
      .catch(console.error)
      .finally(() => setBranchesLoading(false))

  }, [formData.partner_id, initLoading])

  useEffect(() => {
    if (step === 2 && formData.branch_id) {
      setDoctorsLoading(true)
      Api.getDoctors(formData.branch_id)
        .then(data => setDoctors(data || []))
        .catch(console.error)
        .finally(() => setDoctorsLoading(false))
    }
  }, [step, formData.branch_id])

  useEffect(() => {
    if (!formData.res_date || !formData.branch_id || !formData.doctor_id) return
    setSlotsLoading(true)
    setAvailableSlots([])

    const timer = setTimeout(() => {
      Api.getSlots(formData.branch_id, formData.doctor_id, formData.res_date, formData.party_size)
        .then((data) => setAvailableSlots(data || []))
        .catch(console.error)
        .finally(() => setSlotsLoading(false))
    }, 300)
    return () => clearTimeout(timer)
  }, [formData.res_date, formData.branch_id, formData.doctor_id, formData.party_size])

  const handleNext = () => setStep((s) => s + 1)
  const handleBack = () => {
    if (step === 1 && formData.branch_id && branches.length > 1) {
      setFormData(p => ({ ...p, branch_id: null }))
    } else if (step === 1 && formData.partner_id && partners.length > 1) {
      setFormData(p => ({ ...p, partner_id: null, branch_id: null }))
    } else {
      setStep((s) => s - 1)
    }
  }

  const handleSubmit = async () => {
    if (phoneState.number.length < 5) {
      setError("Please enter a valid mobile number.")
      return
    }

    setLoading(true)
    setError(null)

    const payload = { ...formData, msisdn: `${phoneState.code}${phoneState.number}` }

    try {
      const response = await Api.submitBooking(payload)
      if (response?.ok || response?.booking) {
        setCompletedBooking(response.booking || response)
        setStep(5)
      } else {
        setError(response?.message || 'Unable to complete booking. Please try again.')
      }
    } catch (e) {
      setError(e.message || 'An unexpected error occurred. Please try again.')
    } finally {
      setLoading(false)
    }
  }

  const handleCancelBooking = async () => {
    if (phoneState.number.length < 5 || bookingRef.length < 3) {
      setError("Please enter your Mobile Number and Booking Reference.")
      return
    }

    setLoading(true)
    setError(null)

    try {
      const payload = {
        msisdn: `${phoneState.code}${phoneState.number}`,
        booking_code: bookingRef
      }

      const response = await Api.cancelBooking(payload)

      if (response?.ok) setManageSuccess(true)
      else setError(response?.message || "Could not verify booking details.")
    } catch (e) {
      setError("We couldn't find a booking matching those details. Please check your Booking Reference and try again.")
    } finally {
      setLoading(false)
    }
  }

  const renderErrorModal = () => {
    if (!error) return null;
    return (
      <div className="absolute inset-0 z-50 flex items-center justify-center p-6 bg-slate-900/30 backdrop-blur-sm animate-in fade-in duration-200">
        <div className="bg-white rounded-3xl p-6 shadow-2xl w-full max-w-sm border border-slate-100 transform scale-100 animate-in zoom-in-95 duration-200">
          <div className="w-12 h-12 bg-red-50 text-red-500 rounded-2xl flex items-center justify-center mb-4 mx-auto">
            <AlertCircle size={24} />
          </div>
          <h3 className="text-xl font-bold text-slate-900 text-center mb-2">Attention</h3>
          <p className="text-slate-500 text-center mb-6 leading-relaxed text-sm">{error}</p>
          <button
            onClick={() => setError(null)}
            className="w-full py-3.5 rounded-xl bg-slate-900 text-white font-bold hover:bg-slate-800 transition-colors shadow-lg"
          >
            Dismiss
          </button>
        </div>
      </div>
    )
  }

  const renderHeader = () => (
    <div className="px-8 pt-8 pb-4 bg-white flex flex-col gap-4">
      {step === 1 && (
        <div className="flex bg-slate-100 p-1 rounded-xl mb-2">
          <button
            onClick={() => setView('book')}
            className={`flex-1 py-2 text-sm font-bold rounded-lg transition-all ${view === 'book' ? 'bg-white text-teal-700 shadow-sm' : 'text-slate-500 hover:text-slate-700'}`}
          >
            New Booking
          </button>
          <button
            onClick={() => setView('manage')}
            className={`flex-1 py-2 text-sm font-bold rounded-lg transition-all ${view === 'manage' ? 'bg-white text-teal-700 shadow-sm' : 'text-slate-500 hover:text-slate-700'}`}
          >
            Manage Booking
          </button>
        </div>
      )}

      {view === 'book' ? (
        <>
          <div className="flex items-center gap-2">
            {[1, 2, 3, 4].map(s => (
              <div key={s} className={`h-1.5 flex-1 rounded-full transition-all duration-500 ${step >= s ? 'bg-teal-500' : 'bg-slate-100'}`} />
            ))}
          </div>

          <div className="flex items-center justify-between">
            <div>
              <h3 className="text-2xl font-bold text-slate-900 leading-none">
                {step === 1 && 'Where?'}
                {step === 2 && 'Who?'}
                {step === 3 && 'When?'}
                {step === 4 && 'Details'}
              </h3>
              <p className="text-slate-400 text-sm font-medium mt-1">
                {step === 1 && 'Select a clinic location'}
                {step === 2 && 'Choose your specialist'}
                {step === 3 && 'Pick a date & time'}
                {step === 4 && 'Complete your booking'}
              </p>
            </div>
            {step < 5 && (
              <div className="text-xs font-bold text-teal-600 bg-teal-50 px-2.5 py-1 rounded-md">{step}/4</div>
            )}
          </div>
        </>
      ) : (
        <div className="flex items-center justify-between pb-2">
          <div>
            <h3 className="text-2xl font-bold text-slate-900 leading-none">Manage Booking</h3>
            <p className="text-slate-400 text-sm font-medium mt-1">Cancel or reschedule your visit.</p>
          </div>
        </div>
      )}
    </div>
  )

  const renderFooterNav = (disabled = false, primaryAction = handleNext, primaryLabel = 'Next') => (
    <div className="flex items-center gap-4 pt-6 mt-auto border-t border-slate-100">
      {step > 1 && (
        <button
          onClick={handleBack}
          className="w-1/3 h-14 rounded-xl font-bold text-slate-500 border-2 border-slate-100 hover:border-slate-300 hover:text-slate-700 hover:bg-slate-50 transition-all flex items-center justify-center gap-2"
        >
          <ArrowLeft size={18} /> Back
        </button>
      )}
      <button
        onClick={primaryAction}
        disabled={disabled}
        className="flex-1 h-14 bg-teal-600 text-white rounded-xl font-bold hover:bg-teal-700 disabled:opacity-50 disabled:cursor-not-allowed transition-all shadow-lg shadow-teal-200 hover:shadow-xl flex items-center justify-center gap-2 text-lg"
      >
        {loading ? <Loader2 className="animate-spin" /> : (<>{primaryLabel} <ArrowRight size={20} /></>)}
      </button>
    </div>
  )

  const renderStep1 = () => (
    <div className="p-8 h-full flex flex-col animate-in fade-in slide-in-from-right-8 duration-300">
      <div className="flex-1 overflow-y-auto custom-scrollbar">
        {branchesLoading ? (
          <div className="flex flex-col items-center justify-center h-full space-y-4">
            <Loader2 className="animate-spin text-teal-600 w-8 h-8" />
            <p className="text-slate-400 font-medium">Locating clinics...</p>
          </div>
        ) : branches.length === 0 ? (
          <div className="flex flex-col items-center justify-center h-full text-slate-400">
            <Building2 size={32} className="mb-2 opacity-50" />
            <p>No clinics available at the moment.</p>
          </div>
        ) : (
          <div className="grid gap-3">
            {branches.map(branch => {
              const isSelected = formData.branch_id === branch.id
              return (
                <button
                  key={branch.id}
                  onClick={() => setFormData(p => ({ ...p, branch_id: branch.id }))}
                  className={`group relative flex items-center p-5 rounded-2xl border-2 transition-all text-left w-full ${isSelected ? 'border-teal-500 bg-teal-50/50 ring-1 ring-teal-500' : 'border-slate-100 hover:border-teal-400 hover:bg-white'}`}
                >
                  <div className={`w-12 h-12 rounded-xl flex items-center justify-center mr-4 transition-colors ${isSelected ? 'bg-teal-500 text-white' : 'bg-slate-100 text-slate-400 group-hover:bg-teal-50 group-hover:text-teal-600'}`}>
                    <Building2 size={24} />
                  </div>
                  <div className="flex-1">
                    <div className={`font-bold text-lg transition-colors ${isSelected ? 'text-teal-900' : 'text-slate-900'}`}>{t(branch.name, locale)}</div>
                    <div className="text-sm text-slate-500 font-medium">{t(branch.address, locale)}</div>
                  </div>
                  {isSelected && <div className="text-teal-600"><Check size={24} strokeWidth={3} /></div>}
                </button>
              )
            })}
          </div>
        )}
      </div>
      {renderFooterNav(!formData.branch_id)}
    </div>
  )

  const renderStep2 = () => (
    <div className="p-8 h-full flex flex-col animate-in fade-in slide-in-from-right-8 duration-300">
      <div className="flex-1 overflow-y-auto custom-scrollbar">
        {doctorsLoading ? (
          <div className="flex flex-col items-center justify-center h-full space-y-4">
            <Loader2 className="animate-spin text-teal-600 w-8 h-8" />
            <p className="text-slate-400 font-medium">Finding specialists...</p>
          </div>
        ) : doctors.length === 0 ? (
          <div className="flex flex-col items-center justify-center h-full text-slate-400">
            <User size={32} className="mb-2 opacity-50" />
            <p>No doctors found for this branch.</p>
          </div>
        ) : (
          <div className="grid gap-3">
            {doctors.map(doc => {
              const isSelected = formData.doctor_id === doc.id
              return (
                <button
                  key={doc.id}
                  onClick={() => setFormData(p => ({ ...p, doctor_id: doc.id }))}
                  className={`flex flex-col sm:flex-row items-start sm:items-center p-4 rounded-2xl border-2 transition-all text-left w-full relative overflow-hidden group ${isSelected ? 'border-teal-500 bg-teal-50 ring-1 ring-teal-500' : 'border-slate-100 hover:border-teal-300 hover:bg-white hover:shadow-md'}`}
                >
                  <div className="flex items-center w-full">
                    <div className="relative shrink-0">
                      <div className="w-16 h-16 rounded-2xl bg-slate-200 overflow-hidden mr-4 border border-slate-100">
                        {doc.avatar_path ? (
                          <img
                            src={doc.avatar_path.startsWith('http') ? doc.avatar_path : `/storage/${doc.avatar_path}`}
                            className="w-full h-full object-cover"
                            alt=""
                            onError={(e) => { e.target.style.display = 'none' }}
                          />
                        ) : (
                          <div className="w-full h-full flex items-center justify-center bg-slate-100 text-slate-300">
                            <User size={32} />
                          </div>
                        )}
                      </div>
                    </div>

                    <div className="flex-1 min-w-0">
                      <h4 className={`font-bold text-lg truncate transition-colors ${isSelected ? 'text-teal-900' : 'text-slate-900'}`}>{doc.name}</h4>
                      <div className="text-teal-600 font-bold text-sm mb-1">{doc.specialty}</div>
                      <div className="flex items-center gap-3 text-xs text-slate-500 font-medium">
                        <span className="text-slate-400">Book Online</span>
                      </div>
                    </div>
                    {isSelected && <div className="text-teal-600 ml-2"><Check size={24} strokeWidth={3} /></div>}
                  </div>
                </button>
              )
            })}
          </div>
        )}
      </div>
      {renderFooterNav(!formData.doctor_id)}
    </div>
  )

  const renderStep3 = () => (
    <div className="p-8 h-full flex flex-col animate-in fade-in slide-in-from-right-8 duration-300">
      <div className="flex-1 overflow-y-auto custom-scrollbar space-y-6">
        <div className="bg-slate-50 p-4 rounded-2xl border border-slate-100">
          <label className="text-xs font-bold uppercase text-slate-400 mb-3 block tracking-wider">Select Date</label>
          <div className="relative">
            <input
              type="date"
              min={new Date().toISOString().split('T')[0]}
              value={formData.res_date}
              onChange={e => setFormData(p => ({ ...p, res_date: e.target.value, res_time: '' }))}
              className="w-full bg-white p-4 rounded-xl border-2 border-slate-200 focus:border-teal-500 outline-none font-bold text-slate-900 text-lg shadow-sm transition-all cursor-pointer"
            />
            <Calendar size={20} className="absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" />
          </div>
        </div>

        <div>
          <label className="text-xs font-bold uppercase text-slate-400 mb-3 flex items-center justify-between tracking-wider">
            <span>Available Slots</span>
            {slotsLoading && <Loader2 size={14} className="animate-spin text-teal-600" />}
          </label>

          {!formData.res_date ? (
            <div className="text-center py-8 border-2 border-dashed border-slate-200 rounded-xl">
              <p className="text-slate-400 font-medium">Select a date first</p>
            </div>
          ) : availableSlots.length === 0 && !slotsLoading ? (
            <div className="text-center py-8 bg-amber-50 rounded-xl text-amber-800 font-medium">
              Fully booked for this day.
            </div>
          ) : (
            <div className="grid grid-cols-3 gap-3">
              {availableSlots.map((slot) => {
                const val = slot.value || slot
                const label = slot.label || slot
                const isSelected = formData.res_time === val

                return (
                  <button
                    key={val}
                    onClick={() => setFormData(p => ({ ...p, res_time: val }))}
                    className={`py-3 rounded-xl font-bold text-sm transition-all ${isSelected
                      ? 'bg-teal-600 text-white shadow-lg shadow-teal-200 scale-105'
                      : 'bg-white border-2 border-slate-100 text-slate-600 hover:border-teal-500 hover:text-teal-600'
                      }`}
                  >
                    {label}
                  </button>
                )
              })}
            </div>
          )}
        </div>
      </div>

      {renderFooterNav(!formData.res_time || !formData.res_date)}
    </div>
  )

  const renderStep4 = () => (
    <div className="p-8 h-full flex flex-col animate-in fade-in slide-in-from-right-8 duration-300">
      <div className="flex-1 space-y-5 overflow-y-auto custom-scrollbar">
        <div>
          <label className="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Full Name</label>
          <div className="relative">
            <User className="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400" size={20} />
            <input
              type="text"
              value={formData.name}
              onChange={e => setFormData(p => ({ ...p, name: e.target.value }))}
              placeholder="e.g. Ali Ahmed"
              className="w-full pl-12 pr-4 h-14 rounded-xl bg-slate-50 border border-slate-200 focus:bg-white focus:ring-2 focus:ring-teal-500 focus:border-teal-500 outline-none font-semibold text-slate-900 transition-all"
            />
          </div>
        </div>
        <div>
          <label className="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Mobile Number</label>
          <PhoneInput
            value={phoneState.number}
            onChange={e => setPhoneState(p => ({ ...p, number: e.target.value.replace(/\D/g, '') }))}
            code={phoneState.code}
            onCodeChange={c => setPhoneState(p => ({ ...p, code: c }))}
          />
        </div>

        <div className="bg-teal-50 rounded-xl p-4 flex items-center gap-4 border border-teal-100">
          <div className="w-12 h-12 bg-white rounded-lg flex items-center justify-center text-teal-600 shadow-sm shrink-0">
            <CalendarDays size={24} />
          </div>
          <div>
            <div className="font-bold text-slate-900">{formatDateDisplay(formData.res_date)}</div>
            <div className="text-sm text-slate-600 font-medium">
              {formatTimeDisplay(formData.res_time)} • Dr. {doctors.find(d => d.id === formData.doctor_id)?.name?.split(' ')?.slice(-1)}
            </div>
          </div>
        </div>
      </div>

      {renderFooterNav(!formData.name || !phoneState.number || loading, handleSubmit, 'Confirm Booking')}
    </div>
  )

  const renderSuccess = () => (
    <div className="p-8 h-full flex flex-col items-center justify-center text-center animate-in zoom-in duration-300 bg-teal-50/50">
      <div className="w-24 h-24 bg-white rounded-full flex items-center justify-center text-teal-500 mb-6 shadow-xl shadow-teal-100 animate-bounce ring-8 ring-white">
        <Check size={48} strokeWidth={4} />
      </div>
      <h3 className="text-3xl font-bold text-slate-900 mb-2">You're Booked!</h3>
      <p className="text-slate-500 font-medium mb-8 max-w-xs mx-auto">A confirmation message has been sent to your WhatsApp.</p>

      <div className="bg-white rounded-2xl p-0 w-full mb-8 shadow-xl shadow-slate-200 overflow-hidden border border-slate-100 relative max-w-sm">
        <div className="h-2 w-full bg-gradient-to-r from-teal-400 to-emerald-500" />
        <div className="p-6">
          <p className="text-[10px] uppercase tracking-[0.2em] text-slate-400 font-bold mb-2">BOOKING REFERENCE</p>
          <p className="text-3xl font-mono font-bold text-slate-900 tracking-wider mb-6">{completedBooking?.booking_code}</p>

          <div className="space-y-3 pt-6 border-t border-dashed border-slate-200">
            <div className="flex justify-between text-sm">
              <span className="text-slate-500 font-medium">Date</span>
              <span className="font-bold text-slate-800">{formatDateDisplay(completedBooking?.res_date)}</span>
            </div>
            <div className="flex justify-between text-sm">
              <span className="text-slate-500 font-medium">Time</span>
              <span className="font-bold text-slate-800">{formatTimeDisplay(completedBooking?.res_time)}</span>
            </div>
          </div>
        </div>
        <div className="absolute -left-3 top-1/2 w-6 h-6 bg-teal-50 rounded-full" />
        <div className="absolute -right-3 top-1/2 w-6 h-6 bg-teal-50 rounded-full" />
      </div>

      <button
        onClick={() => window.location.reload()}
        className="text-teal-700 font-bold hover:bg-teal-100 px-6 py-3 rounded-full transition-colors"
      >
        Book Another Appointment
      </button>
    </div>
  )

  const renderManageView = () => {
    if (manageSuccess) {
      return (
        <div className="p-8 h-full flex flex-col items-center justify-center text-center animate-in zoom-in duration-300">
          <div className="w-20 h-20 bg-red-100 rounded-full flex items-center justify-center text-red-600 mb-6">
            <Trash2 size={40} />
          </div>
          <h3 className="text-2xl font-bold text-slate-900 mb-2">Booking Cancelled</h3>
          <p className="text-slate-500 mb-8">Your appointment has been successfully cancelled.</p>
          <button
            onClick={() => { setView('book'); setManageSuccess(false); }}
            className="text-teal-600 font-bold hover:bg-teal-50 px-6 py-3 rounded-full transition-colors"
          >
            Make New Booking
          </button>
        </div>
      )
    }

    return (
      <div className="p-8 h-full flex flex-col animate-in fade-in slide-in-from-right-8 duration-300">
        <div className="bg-amber-50 border border-amber-200 rounded-2xl p-4 mb-6 text-sm text-amber-800">
          <p className="font-bold mb-1 flex items-center gap-2"><Info size={16} /> Security Check</p>
          To cancel a booking, please provide the mobile number used and the booking reference code sent to your WhatsApp.
        </div>

        <div className="space-y-6 flex-1">
          <div>
            <label className="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Mobile Number</label>
            <PhoneInput
              value={phoneState.number}
              onChange={e => setPhoneState(p => ({ ...p, number: e.target.value.replace(/\D/g, '') }))}
              code={phoneState.code}
              onCodeChange={c => setPhoneState(p => ({ ...p, code: c }))}
            />
          </div>
          <div>
            <label className="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Booking Reference</label>
            <div className="relative">
              <FileText className="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400" size={20} />
              <input
                type="text"
                value={bookingRef}
                onChange={e => setBookingRef(e.target.value.toUpperCase())}
                placeholder="e.g. 5X2A9B"
                className="w-full pl-12 pr-4 h-14 rounded-xl bg-slate-50 border border-slate-200 focus:bg-white focus:ring-2 focus:ring-teal-500 focus:border-teal-500 outline-none font-bold text-slate-900 tracking-widest placeholder:font-normal placeholder:tracking-normal transition-all"
              />
            </div>
          </div>
        </div>

        <div className="mt-auto pt-6 border-t border-slate-100">
          <button
            onClick={handleCancelBooking}
            disabled={loading || phoneState.number.length < 5 || bookingRef.length < 3}
            className="w-full bg-slate-900 text-white h-14 rounded-xl font-bold hover:bg-red-600 disabled:opacity-50 disabled:hover:bg-slate-900 transition-colors shadow-lg flex items-center justify-center gap-2"
          >
            {loading ? <Loader2 className="animate-spin" /> : 'Find & Cancel Booking'}
          </button>
        </div>
      </div>
    )
  }

  return (
    <div className="bg-white rounded-3xl shadow-2xl shadow-slate-900/10 border border-slate-100 overflow-hidden w-full max-w-[550px] mx-auto min-h-[700px] flex flex-col relative z-20">
      {renderErrorModal()}
      {step < 5 && renderHeader()}
      <div className="flex-1 relative bg-white h-full flex flex-col">
        {view === 'manage' ? renderManageView() : (
          <>
            {step === 1 && renderStep1()}
            {step === 2 && renderStep2()}
            {step === 3 && renderStep3()}
            {step === 4 && renderStep4()}
            {step === 5 && renderSuccess()}
          </>
        )}
      </div>
    </div>
  )
}

// --- Other Sections ---

export function Hero() {
  return (
    <div className="relative min-h-[100vh] lg:min-h-[850px] flex items-center pt-24 pb-12 overflow-hidden bg-slate-50">
      <div className="absolute inset-0 z-0">
        <img
          src="https://images.unsplash.com/photo-1579684385127-1ef15d508118?q=80&w=2864&auto=format&fit=crop"
          alt="Doctor"
          className="w-full h-full object-cover"
        />
        <div className="absolute inset-0 bg-gradient-to-r from-slate-900/90 via-slate-900/60 to-transparent" />
        <div className="absolute inset-0 bg-gradient-to-t from-slate-900/80 via-transparent to-transparent" />
      </div>

      <Container className="relative z-10 w-full h-full">
        <div className="grid lg:grid-cols-12 gap-12 h-full items-center">
          <div className="lg:col-span-7 pt-10 lg:pt-0 animate-in fade-in slide-in-from-left-8 duration-700">
            <div className="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-teal-900/50 border border-teal-500/30 text-teal-300 text-sm font-bold backdrop-blur-md mb-8">
              <BadgeCheck size={16} /> Online Booking Portal
            </div>

            <h1 className="text-5xl lg:text-7xl font-extrabold text-white leading-[1.1] mb-8 tracking-tight">
              Healthcare that <br />
              <span className="text-transparent bg-clip-text bg-gradient-to-r from-teal-200 to-emerald-400">puts you first.</span>
            </h1>

            <p className="text-xl text-slate-300 mb-10 leading-relaxed max-w-xl font-medium">
              Skip the waiting room. Book appointments with top-tier specialists instantly.
            </p>

            <div className="flex flex-wrap gap-8 text-white font-bold">
              <div className="flex items-center gap-3">
                <div className="w-10 h-10 rounded-full bg-teal-500/20 flex items-center justify-center text-teal-400 border border-teal-500/30">
                  <Clock size={20} />
                </div>
                <div>
                  <div className="text-xs text-slate-400 uppercase tracking-wider">Wait Time</div>
                  <div>&lt; 15 Mins</div>
                </div>
              </div>
              <div className="flex items-center gap-3">
                <div className="w-10 h-10 rounded-full bg-teal-500/20 flex items-center justify-center text-teal-400 border border-teal-500/30">
                  <Users size={20} />
                </div>
                <div>
                  <div className="text-xs text-slate-400 uppercase tracking-wider">Patients</div>
                  <div>50k+</div>
                </div>
              </div>
              <div className="flex items-center gap-3">
                <div className="w-10 h-10 rounded-full bg-teal-500/20 flex items-center justify-center text-teal-400 border border-teal-500/30">
                  <ShieldCheck size={20} />
                </div>
                <div>
                  <div className="text-xs text-slate-400 uppercase tracking-wider">Certified</div>
                  <div>MOH</div>
                </div>
              </div>
            </div>
          </div>

          <div className="lg:col-span-5 flex justify-center lg:justify-end animate-in fade-in slide-in-from-bottom-12 duration-1000 delay-200">
            <ClinicBookingWidget />
          </div>
        </div>
      </Container>
    </div>
  )
}

export function StatsSection() {
    return (
        <div className="bg-white py-16 border-b border-slate-100">
            <Container>
                <div className="grid grid-cols-2 md:grid-cols-4 gap-8">
                   {[
                      { num: '24/7', label: 'Emergency Care', icon: <Phone size={24} /> },
                      { num: '100+', label: 'Specialist Doctors', icon: <Stethoscope size={24} /> },
                      { num: '30+', label: 'Medical Depts', icon: <Building2 size={24} /> },
                      { num: '4.9', label: 'Patient Rating', icon: <Star size={24} /> },
                   ].map((s,i) => (
                      <div key={i} className="flex flex-col items-center text-center p-6 rounded-2xl bg-slate-50 hover:bg-slate-100 transition-colors">
                          <div className="mb-3 text-teal-600">{s.icon}</div>
                          <div className="text-3xl font-extrabold text-slate-900 mb-1">{s.num}</div>
                          <div className="text-sm font-bold text-slate-500 uppercase tracking-wide">{s.label}</div>
                      </div>
                   ))}
                </div>
            </Container>
        </div>
    )
}

export function LandingServicesSection() {
   const services = [
      { icon: <HeartPulse />, title: 'Heart Center', desc: 'World-class cardiology care.' },
      { icon: <Activity />, title: 'Neurology', desc: 'Advanced brain & spine clinic.' },
      { icon: <Users />, title: 'Pediatrics', desc: 'Compassionate care for kids.' },
      { icon: <Stethoscope />, title: 'Primary Care', desc: 'Your daily health partner.' },
      { icon: <Sparkles />, title: 'Dermatology', desc: 'Skin, hair & laser treatments.' },
      { icon: <ShieldCheck />, title: 'Orthopedics', desc: 'Joint replacement experts.' },
   ]

   return (
      <section className="py-24 bg-slate-50 relative overflow-hidden">
         <div className="absolute top-0 left-0 w-full h-full bg-[url('https://www.transparenttextures.com/patterns/cubes.png')] opacity-[0.03]"></div>
         <Container className="relative z-10">
            <SectionHeading 
               title="Medical Excellence" 
               subtitle="Explore our specialized departments led by internationally accredited physicians." 
            />
            <div className="grid md:grid-cols-2 lg:grid-cols-3 gap-8">
               {services.map((s, i) => (
                  <div key={i} className="bg-white p-8 rounded-3xl shadow-sm border border-slate-100 hover:shadow-xl hover:shadow-teal-100/50 hover:-translate-y-1 transition-all duration-300 group cursor-pointer">
                     <div className="w-16 h-16 rounded-2xl bg-teal-50 text-teal-600 flex items-center justify-center mb-6 group-hover:bg-teal-500 group-hover:text-white transition-all shadow-sm">
                        {React.cloneElement(s.icon, { size: 32 })}
                     </div>
                     <h3 className="text-2xl font-bold text-slate-900 mb-3">{s.title}</h3>
                     <p className="text-slate-500 leading-relaxed font-medium mb-6">{s.desc}</p>
                     <div className="flex items-center text-teal-600 font-bold group-hover:gap-2 transition-all">
                        View Department <ArrowRight size={18} className="ml-2" />
                     </div>
                  </div>
               ))}
            </div>
         </Container>
      </section>
   )
}

export function InfoSection() {
    return (
        <section className="py-24 bg-white">
            <Container>
                <div className="bg-gradient-to-br from-slate-900 to-slate-800 rounded-[3rem] p-8 md:p-20 text-white relative overflow-hidden shadow-2xl">
                    <div className="absolute top-0 right-0 w-[600px] h-[600px] bg-teal-500 rounded-full blur-[120px] opacity-20 -translate-y-1/2 translate-x-1/3" />
                    <div className="relative z-10 grid lg:grid-cols-2 gap-16 items-center">
                        <div>
                            <div className="inline-block px-4 py-2 rounded-lg bg-white/10 backdrop-blur-md text-teal-300 font-bold text-sm mb-6">
                                INTERNATIONAL PATIENTS
                            </div>
                            <h2 className="text-4xl md:text-6xl font-bold mb-8 leading-tight">Traveling for treatment?</h2>
                            <p className="text-slate-300 text-xl mb-10 leading-relaxed font-medium">
                                We make medical tourism easy. From visa assistance to luxury accommodation arranging, our international desk handles everything so you can focus on healing.
                            </p>
                            <div className="flex flex-col sm:flex-row gap-4">
                               <button className="bg-teal-500 text-white px-8 py-4 rounded-xl font-bold hover:bg-teal-400 transition-colors shadow-lg shadow-teal-500/20 text-lg">
                                  Contact International Desk
                               </button>
                               <button className="bg-white/10 text-white px-8 py-4 rounded-xl font-bold hover:bg-white/20 transition-colors backdrop-blur-md text-lg">
                                  Download Guide
                               </button>
                            </div>
                        </div>
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-6">
                            <div className="bg-white/5 backdrop-blur-lg p-8 rounded-3xl border border-white/10 hover:bg-white/10 transition-colors">
                                <Phone className="mb-6 text-teal-400" size={32} />
                                <div className="font-bold text-xl mb-2">24/7 Concierge</div>
                                <div className="text-slate-400 font-medium">Dedicated support line for international guests.</div>
                            </div>
                            <div className="bg-white/5 backdrop-blur-lg p-8 rounded-3xl border border-white/10 hover:bg-white/10 transition-colors">
                                <Building2 className="mb-6 text-teal-400" size={32} />
                                <div className="font-bold text-xl mb-2">Luxury Stay</div>
                                <div className="text-slate-400 font-medium">Partnered with 5-star hotels near the campus.</div>
                            </div>
                        </div>
                    </div>
                </div>
            </Container>
        </section>
    )
}
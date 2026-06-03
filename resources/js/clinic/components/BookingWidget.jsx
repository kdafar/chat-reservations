import React, { useEffect, useMemo, useState } from 'react'
import {
  ArrowRight, ArrowLeft, Building2, Calendar, CalendarDays,
  Check, Info, Loader2, User, AlertCircle, FileText, Trash2,
  MapPin, Clock, Stethoscope, ChevronRight, MessageCircle, X
} from 'lucide-react'
import { Api, getLocale, t, formatDateDisplay, formatTimeDisplay } from '../api'
import { PhoneInput } from './Shared'

export default function ClinicBookingWidget() {
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

  // OTP modal state. Opens when handleSubmit confirms the server requires an OTP.
  const [otp, setOtp] = useState({
    open: false,
    code: '',
    sending: false,
    verifying: false,
    error: null,
    cooldown: 0, // seconds until resend allowed
  })

  // Resend cooldown tick. Self-clearing when it hits 0.
  useEffect(() => {
    if (!otp.open || otp.cooldown <= 0) return
    const t = setTimeout(() => setOtp(p => ({ ...p, cooldown: Math.max(0, p.cooldown - 1) })), 1000)
    return () => clearTimeout(t)
  }, [otp.open, otp.cooldown])

  // -- Data Loading Effects (Preserved Logic) --
  useEffect(() => {
    Api.getPartners()
      .then((data) => {
        setPartners(data || [])
        if (data?.length === 1) setFormData(p => ({ ...p, partner_id: data[0].id }))
      })
      .catch(console.error)
      .finally(() => setInitLoading(false))
  }, [])

  useEffect(() => {
    if (initLoading) return;
    setBranchesLoading(true)
    const pid = formData.partner_id;
    Api.getBranches(pid)
      .then(data => {
        setBranches(data || [])
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

  // -- Handlers --
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

  const fullMsisdn = () => `${phoneState.code}${phoneState.number}`

  // Submit the booking with (optionally) an OTP code. Used by both the
  // "no OTP required" path and the modal "Verify & Confirm" flow.
  const submitBookingWith = async (otpCode = null) => {
    const payload = { ...formData, msisdn: fullMsisdn() }
    if (otpCode) payload.otp_code = otpCode
    const response = await Api.submitBooking(payload)
    if (response?.ok || response?.booking) {
      setCompletedBooking(response.booking || response)
      setStep(5)
      return true
    }
    throw new Error(response?.message || 'Unable to complete booking. Please try again.')
  }

  const handleSubmit = async () => {
    if (phoneState.number.length < 5) {
      setError("Please enter a valid mobile number.")
      return
    }
    setLoading(true)
    setError(null)
    try {
      // Probe the OTP flag. If disabled, the endpoint returns { enabled:false }
      // and we submit immediately. If enabled, the WA code is on its way.
      const probe = await Api.requestBookingOtp(fullMsisdn())
      if (probe?.enabled === false) {
        await submitBookingWith(null)
        return
      }
      // OTP required — open the modal and let the user enter the code.
      setOtp({
        open: true,
        code: '',
        sending: false,
        verifying: false,
        error: null,
        cooldown: 60,
      })
    } catch (e) {
      setError(e.message || 'An unexpected error occurred. Please try again.')
    } finally {
      setLoading(false)
    }
  }

  const handleVerifyOtp = async () => {
    if ((otp.code || '').length !== 6) {
      setOtp(p => ({ ...p, error: 'Enter the full 6-digit code we sent on WhatsApp.' }))
      return
    }
    setOtp(p => ({ ...p, verifying: true, error: null }))
    try {
      await submitBookingWith(otp.code.trim())
      setOtp(p => ({ ...p, open: false, verifying: false, code: '' }))
    } catch (e) {
      const msg = e.code === 'otp_invalid'
        ? 'That code is invalid or expired. Try again or resend.'
        : (e.message || 'Verification failed. Please try again.')
      setOtp(p => ({ ...p, verifying: false, error: msg }))
    }
  }

  const handleResendOtp = async () => {
    if (otp.cooldown > 0 || otp.sending) return
    setOtp(p => ({ ...p, sending: true, error: null }))
    try {
      await Api.requestBookingOtp(fullMsisdn())
      setOtp(p => ({ ...p, sending: false, cooldown: 60, code: '' }))
    } catch (e) {
      const msg = e.status === 429
        ? (e.message || 'Please wait before requesting another code.')
        : 'Could not resend the code. Please try again in a moment.'
      setOtp(p => ({ ...p, sending: false, error: msg }))
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

  // -- Helpers --
  const getSelectedBranchName = () => {
    const b = branches.find(b => b.id === formData.branch_id)
    return b ? t(b.name, locale) : ''
  }

  const getSelectedDoctorName = () => {
    const d = doctors.find(d => d.id === formData.doctor_id)
    return d ? d.name : ''
  }

  // -- Render Components --
  const renderOtpModal = () => {
    if (!otp.open) return null
    const masked = `${phoneState.code} •••• ${phoneState.number.slice(-3)}`
    return (
      <div className="absolute inset-0 z-[60] flex items-center justify-center p-6 bg-slate-900/40 backdrop-blur-sm animate-in fade-in duration-200">
        <div className="bg-white rounded-3xl p-6 shadow-2xl w-full max-w-sm border border-slate-100 animate-in zoom-in-95 duration-200 relative">
          <button
            onClick={() => setOtp(p => ({ ...p, open: false }))}
            className="absolute top-4 right-4 text-slate-400 hover:text-slate-700 transition-colors"
            disabled={otp.verifying}
            aria-label="Close"
          >
            <X size={20} />
          </button>

          <div className="w-12 h-12 bg-emerald-50 text-emerald-600 rounded-2xl flex items-center justify-center mb-4 mx-auto ring-4 ring-emerald-50/50">
            <MessageCircle size={24} />
          </div>
          <h3 className="text-xl font-bold text-slate-900 text-center mb-1">Verify on WhatsApp</h3>
          <p className="text-slate-500 text-center mb-6 leading-relaxed text-sm">
            We sent a 6-digit code to <span className="font-semibold text-slate-700">{masked}</span>.
          </p>

          <input
            type="text"
            inputMode="numeric"
            pattern="[0-9]*"
            autoFocus
            value={otp.code}
            onChange={e => setOtp(p => ({ ...p, code: e.target.value.replace(/\D/g, '').slice(0, 6), error: null }))}
            className="w-full h-14 px-4 rounded-xl bg-white border-2 border-slate-200 focus:border-teal-500 focus:ring-4 focus:ring-teal-500/10 font-bold text-center text-2xl tracking-[0.5em] text-slate-900 outline-none transition-all placeholder:text-slate-300 placeholder:tracking-normal placeholder:font-normal placeholder:text-base"
            placeholder="Enter 6-digit code"
            maxLength={6}
          />

          {otp.error && (
            <p className="text-red-600 text-xs mt-2 text-center font-medium">{otp.error}</p>
          )}

          <button
            onClick={handleVerifyOtp}
            disabled={otp.verifying || otp.code.length !== 6}
            className="w-full mt-5 py-3.5 rounded-xl bg-teal-600 text-white font-bold hover:bg-teal-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors shadow-lg shadow-teal-200 active:scale-95 duration-150 flex items-center justify-center gap-2"
          >
            {otp.verifying ? <Loader2 className="animate-spin" size={20} /> : 'Verify & Confirm Booking'}
          </button>

          <div className="text-center mt-4">
            {otp.cooldown > 0 ? (
              <span className="text-slate-400 text-xs">Resend code in {otp.cooldown}s</span>
            ) : (
              <button
                onClick={handleResendOtp}
                disabled={otp.sending}
                className="text-teal-600 text-xs font-semibold hover:text-teal-700 disabled:opacity-50"
              >
                {otp.sending ? 'Sending…' : 'Resend code'}
              </button>
            )}
          </div>
        </div>
      </div>
    )
  }

  const renderErrorModal = () => {
    if (!error) return null;
    return (
      <div className="absolute inset-0 z-[60] flex items-center justify-center p-6 bg-slate-900/40 backdrop-blur-sm animate-in fade-in duration-200">
        <div className="bg-white rounded-3xl p-6 shadow-2xl w-full max-w-sm border border-slate-100 transform scale-100 animate-in zoom-in-95 duration-200">
          <div className="w-12 h-12 bg-red-50 text-red-500 rounded-2xl flex items-center justify-center mb-4 mx-auto ring-4 ring-red-50/50">
            <AlertCircle size={24} />
          </div>
          <h3 className="text-xl font-bold text-slate-900 text-center mb-2">Attention</h3>
          <p className="text-slate-500 text-center mb-6 leading-relaxed text-sm">{error}</p>
          <button onClick={() => setError(null)} className="w-full py-3.5 rounded-xl bg-slate-900 text-white font-bold hover:bg-slate-800 transition-colors shadow-lg active:scale-95 duration-150">
            Dismiss
          </button>
        </div>
      </div>
    )
  }

  const renderHeader = () => {
    const progress = (step / 4) * 100;
    
    return (
      <div className="px-8 pt-8 pb-6 bg-white flex flex-col gap-5 border-b border-slate-50">
        {step === 1 && (
          <div className="flex bg-slate-100 p-1.5 rounded-2xl mb-2 relative">
            <button 
              onClick={() => setView('book')} 
              className={`relative z-10 flex-1 py-2.5 text-sm font-bold rounded-xl transition-all duration-300 ${view === 'book' ? 'bg-white text-teal-700 shadow-sm ring-1 ring-black/5' : 'text-slate-500 hover:text-slate-700'}`}
            >
              New Booking
            </button>
            <button 
              onClick={() => setView('manage')} 
              className={`relative z-10 flex-1 py-2.5 text-sm font-bold rounded-xl transition-all duration-300 ${view === 'manage' ? 'bg-white text-teal-700 shadow-sm ring-1 ring-black/5' : 'text-slate-500 hover:text-slate-700'}`}
            >
              Manage Booking
            </button>
          </div>
        )}
        
        {view === 'book' ? (
          <div>
            <div className="flex items-center justify-between mb-2">
              <span className="text-xs font-bold tracking-wider text-teal-600 uppercase bg-teal-50 px-2 py-1 rounded-md">
                Step {step} of 4
              </span>
            </div>
            <h3 className="text-3xl font-bold text-slate-900 leading-tight">
              {step === 1 && 'Find a Clinic'}
              {step === 2 && 'Select Specialist'}
              {step === 3 && 'Choose Time'}
              {step === 4 && 'Confirm Details'}
            </h3>
            <p className="text-slate-400 text-sm font-medium mt-2">
              {step === 1 && 'Where would you like to visit?'}
              {step === 2 && 'Who would you like to see?'}
              {step === 3 && 'Select a date and available slot.'}
              {step === 4 && 'Please review your booking info.'}
            </p>
            {/* Progress Bar */}
            <div className="h-1.5 w-full bg-slate-100 rounded-full mt-6 overflow-hidden">
              <div 
                className="h-full bg-teal-500 transition-all duration-500 ease-out rounded-full" 
                style={{ width: `${progress}%` }} 
              />
            </div>
          </div>
        ) : (
          <div>
            <h3 className="text-3xl font-bold text-slate-900 leading-tight">Manage Booking</h3>
            <p className="text-slate-400 text-sm font-medium mt-2">Cancel or view details of your visit.</p>
          </div>
        )}
      </div>
    )
  }

  const renderFooterNav = (disabled = false, primaryAction = handleNext, primaryLabel = 'Next') => (
    <div className="flex items-center gap-3 pt-6 mt-auto border-t border-slate-50 bg-white/50 backdrop-blur-sm sticky bottom-0 z-10">
      {step > 1 && (
        <button 
          onClick={handleBack} 
          className="w-14 h-14 flex items-center justify-center rounded-2xl font-bold text-slate-400 border border-slate-200 hover:border-slate-300 hover:text-slate-700 hover:bg-slate-50 transition-all active:scale-95"
          title="Go Back"
        >
          <ArrowLeft size={20} />
        </button>
      )}
      <button 
        onClick={primaryAction} 
        disabled={disabled} 
        className="flex-1 h-14 bg-teal-600 text-white rounded-2xl font-bold hover:bg-teal-700 disabled:opacity-50 disabled:cursor-not-allowed transition-all shadow-lg shadow-teal-200 hover:shadow-xl hover:shadow-teal-600/20 flex items-center justify-center gap-3 text-lg active:scale-[0.98]"
      >
        {loading ? <Loader2 className="animate-spin" /> : (<>{primaryLabel} <ArrowRight size={20} /></>)}
      </button>
    </div>
  )

  // Step 1: Branches
  const renderStep1 = () => (
    <div className="p-8 h-full flex flex-col animate-in fade-in slide-in-from-right-8 duration-300">
      <div className="flex-1 overflow-y-auto custom-scrollbar pr-2 -mr-2">
        {branchesLoading ? (
          <div className="flex flex-col items-center justify-center py-20 text-slate-400">
            <Loader2 className="animate-spin mb-2" size={32} />
            <span className="text-sm font-medium">Loading clinics...</span>
          </div>
        ) : branches.length === 0 ? (
          <div className="text-center py-20 text-slate-400">No clinics available at the moment.</div>
        ) : (
          <div className="grid gap-4">
            {branches.map(branch => (
              <button 
                key={branch.id} 
                onClick={() => setFormData(p => ({ ...p, branch_id: branch.id }))} 
                className={`group relative flex items-start p-5 rounded-2xl border-2 transition-all text-left w-full hover:shadow-lg hover:scale-[1.01] duration-200 ${
                  formData.branch_id === branch.id 
                    ? 'border-teal-500 bg-teal-50/60 ring-1 ring-teal-500 shadow-teal-100' 
                    : 'border-slate-100 bg-white hover:border-teal-200'
                }`}
              >
                <div className={`w-10 h-10 rounded-full flex items-center justify-center mr-4 shrink-0 transition-colors ${
                  formData.branch_id === branch.id ? 'bg-teal-100 text-teal-600' : 'bg-slate-100 text-slate-400 group-hover:bg-teal-50 group-hover:text-teal-500'
                }`}>
                  <MapPin size={20} />
                </div>
                <div className="flex-1">
                  <div className={`font-bold text-lg mb-1 transition-colors ${formData.branch_id === branch.id ? 'text-teal-900' : 'text-slate-900'}`}>
                    {t(branch.name, locale)}
                  </div>
                  <div className="text-sm text-slate-500 leading-relaxed">{t(branch.address, locale)}</div>
                </div>
                {formData.branch_id === branch.id && (
                  <div className="absolute top-5 right-5 text-teal-600 bg-white rounded-full p-1 shadow-sm">
                    <Check size={16} strokeWidth={3} />
                  </div>
                )}
              </button>
            ))}
          </div>
        )}
      </div>
      {renderFooterNav(!formData.branch_id)}
    </div>
  )

  // Step 2: Doctors
  const renderStep2 = () => (
    <div className="p-8 h-full flex flex-col animate-in fade-in slide-in-from-right-8 duration-300">
      <div className="flex-1 overflow-y-auto custom-scrollbar pr-2 -mr-2">
        {doctorsLoading ? (
           <div className="flex flex-col items-center justify-center py-20 text-slate-400">
             <Loader2 className="animate-spin mb-2" size={32} />
             <span className="text-sm font-medium">Finding specialists...</span>
           </div>
        ) : doctors.length === 0 ? (
          <div className="text-center py-20 text-slate-400">No doctors found for this location.</div>
        ) : (
          <div className="grid gap-3">
            {doctors.map(doc => (
              <button 
                key={doc.id} 
                onClick={() => setFormData(p => ({ ...p, doctor_id: doc.id }))} 
                className={`flex items-center p-4 rounded-2xl border transition-all text-left w-full hover:shadow-md duration-200 ${
                  formData.doctor_id === doc.id 
                    ? 'border-teal-500 bg-teal-50/50 ring-1 ring-teal-500' 
                    : 'border-slate-100 bg-white hover:border-teal-200'
                }`}
              >
                <div className={`w-14 h-14 rounded-full mr-4 overflow-hidden border-2 shrink-0 ${formData.doctor_id === doc.id ? 'border-teal-500' : 'border-slate-100'}`}>
                  {doc.avatar_path ? (
                    <img 
                      src={doc.avatar_path.startsWith('http') ? doc.avatar_path : `/storage/${doc.avatar_path}`} 
                      className="w-full h-full object-cover" 
                      onError={e => e.target.style.display='none'} 
                      alt={doc.name}
                    />
                  ) : (
                    <div className="w-full h-full bg-slate-100 flex items-center justify-center text-slate-400">
                      <User size={24} />
                    </div>
                  )}
                </div>
                <div className="flex-1 min-w-0">
                  <div className="font-bold text-slate-900 truncate text-lg">{doc.name}</div>
                  <div className="text-teal-600 text-sm font-medium flex items-center gap-1.5 mt-0.5">
                    <Stethoscope size={14} />
                    <span className="truncate">{doc.specialty}</span>
                  </div>
                </div>
                {formData.doctor_id === doc.id ? (
                   <Check className="text-teal-600 ml-2" strokeWidth={3} size={20} />
                ) : (
                   <ChevronRight className="text-slate-300 ml-2 group-hover:text-teal-400" size={20} />
                )}
              </button>
            ))}
          </div>
        )}
      </div>
      {renderFooterNav(!formData.doctor_id)}
    </div>
  )

  // Step 3: Slots
  const renderStep3 = () => (
    <div className="p-8 h-full flex flex-col animate-in fade-in slide-in-from-right-8 duration-300">
      <div className="flex-1 overflow-y-auto custom-scrollbar pr-2 -mr-2 space-y-6">
        
        {/* Date Picker Container */}
        <div className="bg-white p-1 rounded-2xl border border-slate-200 shadow-sm">
           <div className="relative">
              <div className="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400">
                 <CalendarDays size={20} />
              </div>
              <input 
                type="date" 
                min={new Date().toISOString().split('T')[0]} 
                value={formData.res_date} 
                onChange={e => setFormData(p => ({ ...p, res_date: e.target.value, res_time: '' }))} 
                className="w-full pl-12 pr-4 py-4 rounded-xl bg-transparent font-bold text-lg text-slate-700 focus:outline-none cursor-pointer" 
              />
           </div>
        </div>

        {/* Slots Area */}
        <div className="min-h-[200px]">
          {slotsLoading ? (
            <div className="flex flex-col items-center justify-center py-10 text-slate-400 opacity-70">
              <Loader2 className="animate-spin mb-3" size={28} />
              <span className="text-sm">Checking availability...</span>
            </div>
          ) : availableSlots.length > 0 ? (
            <div className="grid grid-cols-3 gap-3">
                {availableSlots.map(s => {
                  const val = s.value || s;
                  const lbl = s.label || s;
                  const isSelected = formData.res_time === val;
                  return (
                    <button 
                      key={val} 
                      onClick={() => setFormData(p => ({ ...p, res_time: val }))} 
                      className={`py-3 px-2 rounded-xl font-bold text-sm transition-all duration-200 ${
                        isSelected 
                          ? 'bg-teal-600 text-white shadow-md shadow-teal-200 scale-105' 
                          : 'bg-white border border-slate-200 text-slate-600 hover:border-teal-400 hover:text-teal-600 hover:bg-teal-50'
                      }`}
                    >
                      {lbl}
                    </button>
                  )
                })}
            </div>
          ) : formData.res_date ? (
            <div className="flex flex-col items-center justify-center py-10 bg-amber-50 rounded-2xl border border-amber-100 text-amber-800">
              <Clock className="mb-2 opacity-50" size={24} />
              <span className="font-semibold">No slots available</span>
              <span className="text-xs mt-1 opacity-70">Please try another date</span>
            </div>
          ) : (
            <div className="text-center py-10 text-slate-400 flex flex-col items-center">
              <Calendar size={32} className="mb-2 opacity-20" />
              <span className="text-sm">Select a date above to view times</span>
            </div>
          )}
        </div>
      </div>
      {renderFooterNav(!formData.res_time || !formData.res_date)}
    </div>
  )

  // Step 4: Details & Summary
  const renderStep4 = () => (
    <div className="p-8 h-full flex flex-col animate-in fade-in slide-in-from-right-8 duration-300">
      <div className="flex-1 space-y-6 overflow-y-auto custom-scrollbar pr-2 -mr-2">
        
        {/* Booking Recap Card */}
        <div className="bg-slate-50 rounded-2xl p-5 border border-slate-100 relative overflow-hidden">
          <div className="absolute top-0 right-0 w-24 h-24 bg-teal-100 rounded-full -mr-10 -mt-10 opacity-50 blur-xl"></div>
          <h4 className="text-xs font-bold uppercase tracking-wider text-slate-400 mb-4 flex items-center gap-2">
            <Info size={14} /> Booking Summary
          </h4>
          
          <div className="space-y-4">
             <div className="flex items-start gap-3">
                <MapPin className="text-teal-500 mt-1" size={16} />
                <div>
                   <div className="font-bold text-slate-900 text-sm">{getSelectedBranchName()}</div>
                   <div className="text-xs text-slate-500">Selected Clinic</div>
                </div>
             </div>
             <div className="flex items-start gap-3">
                <User className="text-teal-500 mt-1" size={16} />
                <div>
                   <div className="font-bold text-slate-900 text-sm">{getSelectedDoctorName()}</div>
                   <div className="text-xs text-slate-500">Specialist</div>
                </div>
             </div>
             <div className="flex items-start gap-3">
                <CalendarDays className="text-teal-500 mt-1" size={16} />
                <div>
                   <div className="font-bold text-slate-900 text-sm">
                      {formatDateDisplay(formData.res_date)} • {formatTimeDisplay(formData.res_time)}
                   </div>
                   <div className="text-xs text-slate-500">Appointment Time</div>
                </div>
             </div>
          </div>
        </div>

        {/* Input Fields */}
        <div className="space-y-4 pt-2">
          <div>
            <label className="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2 ml-1">Patient Name</label>
            <input 
              type="text" 
              value={formData.name} 
              onChange={e => setFormData(p => ({ ...p, name: e.target.value }))} 
              className="w-full h-14 px-4 rounded-xl bg-white border-2 border-slate-100 focus:border-teal-500 focus:ring-4 focus:ring-teal-500/10 font-semibold transition-all outline-none text-slate-900 placeholder:text-slate-300" 
              placeholder="e.g. Ali Ahmed" 
            />
          </div>
          <div>
            <label className="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2 ml-1">Mobile Number</label>
            <PhoneInput 
              value={phoneState.number} 
              onChange={e => setPhoneState(p => ({ ...p, number: e.target.value.replace(/\D/g, '') }))} 
              code={phoneState.code} 
              onCodeChange={c => setPhoneState(p => ({ ...p, code: c }))} 
            />
          </div>
        </div>
      </div>
      {renderFooterNav(!formData.name || !phoneState.number || loading, handleSubmit, 'Confirm Booking')}
    </div>
  )

  const renderSuccess = () => (
    <div className="p-8 h-full flex flex-col items-center justify-center text-center animate-in zoom-in-95 duration-500">
        <div className="w-24 h-24 bg-teal-100 text-teal-600 rounded-full flex items-center justify-center mb-8 shadow-xl shadow-teal-100 relative">
           <Check size={48} strokeWidth={3} />
           <div className="absolute inset-0 rounded-full border-4 border-teal-50 animate-ping opacity-20"></div>
        </div>
        <h3 className="text-3xl font-bold mb-2 text-slate-900">Booking Confirmed!</h3>
        <p className="text-slate-500 mb-8 max-w-[250px]">Your appointment has been successfully scheduled.</p>
        
        <div className="bg-slate-50 p-6 rounded-3xl w-full border border-slate-100 mb-8">
           <div className="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Booking Reference</div>
           <div className="font-mono text-3xl font-bold text-slate-900 tracking-wider select-all">{completedBooking?.booking_code}</div>
        </div>

        <button 
          onClick={() => window.location.reload()} 
          className="text-teal-700 font-bold hover:bg-teal-50 px-6 py-3 rounded-xl transition-colors"
        >
          Book Another Appointment
        </button>
    </div>
  )

  const renderManage = () => manageSuccess ? (
    <div className="p-8 h-full flex flex-col items-center justify-center text-center animate-in zoom-in-95 duration-300">
        <div className="w-20 h-20 bg-red-50 text-red-500 rounded-full flex items-center justify-center mb-6">
          <Trash2 size={32} />
        </div>
        <h3 className="text-2xl font-bold text-slate-900 mb-2">Booking Cancelled</h3>
        <p className="text-slate-500 mb-8">Your appointment has been removed from our system.</p>
        <button 
          onClick={() => { setView('book'); setManageSuccess(false); }} 
          className="mt-4 text-white bg-teal-600 hover:bg-teal-700 px-8 py-3.5 rounded-xl font-bold transition-all shadow-lg shadow-teal-200"
        >
          New Booking
        </button>
    </div>
  ) : (
    <div className="p-8 h-full flex flex-col animate-in fade-in slide-in-from-right-8 duration-300">
        <div className="space-y-6 flex-1">
            <div className="bg-slate-50 p-6 rounded-2xl border border-slate-100 mb-6">
               <p className="text-sm text-slate-600 leading-relaxed">
                  To cancel or reschedule, please enter the mobile number used during booking and your reference code.
               </p>
            </div>
            <div>
               <label className="text-xs font-bold text-slate-500 uppercase tracking-wider mb-2 ml-1 block">Mobile Number</label>
               <PhoneInput 
                 value={phoneState.number} 
                 onChange={e => setPhoneState(p => ({ ...p, number: e.target.value.replace(/\D/g, '') }))} 
                 code={phoneState.code} 
                 onCodeChange={c => setPhoneState(p => ({ ...p, code: c }))} 
               />
            </div>
            <div>
               <label className="text-xs font-bold text-slate-500 uppercase tracking-wider mb-2 ml-1 block">Reference Code</label>
               <input 
                 type="text" 
                 value={bookingRef} 
                 onChange={e => setBookingRef(e.target.value.toUpperCase())} 
                 className="w-full h-14 px-4 rounded-xl bg-white border-2 border-slate-100 focus:border-teal-500 focus:ring-4 focus:ring-teal-500/10 font-bold tracking-widest text-slate-900 outline-none transition-all placeholder:font-normal placeholder:tracking-normal placeholder:text-slate-300" 
                 placeholder="e.g. A1B2C"
               />
            </div>
        </div>
        <button 
          onClick={handleCancelBooking} 
          disabled={loading} 
          className="w-full bg-slate-900 text-white h-14 rounded-2xl font-bold mt-6 hover:bg-slate-800 transition-all shadow-lg shadow-slate-300 active:scale-[0.98] flex items-center justify-center gap-2"
        >
          {loading ? <Loader2 className="animate-spin" /> : <>Cancel Booking <ArrowRight size={18} /></>}
        </button>
    </div>
  )

  return (
    <div className="bg-white rounded-[2.5rem] shadow-2xl shadow-slate-900/10 border border-slate-100 overflow-hidden w-full max-w-[550px] mx-auto min-h-[750px] flex flex-col relative z-20 font-sans">
      {renderErrorModal()}
      {renderOtpModal()}
      {step < 5 && renderHeader()}
      <div className="flex-1 relative bg-white h-full flex flex-col">
        {view === 'manage' ? renderManage() : (
           step===1?renderStep1():step===2?renderStep2():step===3?renderStep3():step===4?renderStep4():renderSuccess()
        )}
      </div>
    </div>
  )
}
import React, { useEffect, useMemo, useState } from 'react'
import { useLocation } from 'react-router-dom'
import {
  ArrowRight, ArrowLeft, Building2, Calendar, CalendarDays,
  Check, Info, Loader2, User, AlertCircle, FileText, Trash2,
  MapPin, Clock, Stethoscope, ChevronRight, MessageCircle, X, Sparkles
} from 'lucide-react'
import { Api, getLocale, t, formatDateDisplay, formatTimeDisplay, formatPrice } from '../api'
// `Calendar` from lucide is the icon; the Shared one is our date picker.
import { PhoneInput, Calendar as BookingCalendar, monogramOf } from './Shared'
import { S, tr } from '../brand'

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
  // The offer the patient came in on, resolved for display only.
  const [chosenOffer, setChosenOffer] = useState(null)

  const [formData, setFormData] = useState({
    partner_id: null,
    branch_id: null,
    doctor_id: null,
    // Set when the patient arrived from an offer card (/clinic/book?package=…).
    // Rides along with the submit payload; the server ignores a stale id.
    package_id: null,
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

  // The flow is a named list rather than fixed numbers, because the "choose a
  // clinic" step only exists when the site lists more than one clinic. With a
  // single clinic it is dropped and the widget stays a 4-step flow, so the
  // step counter and progress bar stay honest either way.
  const STEPS = useMemo(
    () => (partners.length > 1
      ? ['clinic', 'branch', 'doctor', 'time', 'confirm']
      : ['branch', 'doctor', 'time', 'confirm']),
    [partners.length],
  )
  const totalSteps = STEPS.length
  const current = STEPS[step - 1] ?? null   // null once we're past the last step
  const isSuccess = step > totalSteps

  // Resend cooldown tick. Self-clearing when it hits 0.
  useEffect(() => {
    if (!otp.open || otp.cooldown <= 0) return
    const t = setTimeout(() => setOtp(p => ({ ...p, cooldown: Math.max(0, p.cooldown - 1) })), 1000)
    return () => clearTimeout(t)
  }, [otp.open, otp.cooldown])

  /* -------------------------------------------------------------------------
   * Prefill from the URL.
   *
   * Browsing (treatment → clinic → branch → doctor) used to end at a Book
   * button that dropped the patient back on step 1 with nothing selected, so
   * they picked the same clinic, branch and doctor a second time. Those links
   * now carry what is already known — /clinic/book?partner=&branch=&doctor= —
   * and the widget opens on the first question that is still unanswered.
   * ---------------------------------------------------------------------- */
  const { search } = useLocation()
  const prefill = useMemo(() => {
    const q = new URLSearchParams(search)
    const num = (k) => {
      const v = parseInt(q.get(k) ?? '', 10)
      return Number.isFinite(v) && v > 0 ? v : null
    }
    // Hierarchical: a branch is meaningless without its clinic, a doctor
    // without a branch. Dropping the orphans stops a hand-edited URL from
    // carrying an id that never gets checked against a real list.
    const partner_id = num('partner')
    const branch_id = partner_id ? num('branch') : null
    const doctor_id = branch_id ? num('doctor') : null
    // A package sits outside that hierarchy — it is what the patient wants,
    // not where they want it — so it is read on its own terms and survives
    // even when the clinic/branch ids are missing or get dropped as orphans.
    const package_id = num('package')
    return { partner_id, branch_id, doctor_id, package_id }
  }, [search])

  // Only the hierarchy decides which step opens first; a package answers no
  // step, so it is tracked separately and never moves the flow.
  const hasPrefill = !!(prefill.partner_id || prefill.branch_id || prefill.doctor_id)
  const hasContext = hasPrefill || !!prefill.package_id
  const [prefillApplied, setPrefillApplied] = useState(false)

  useEffect(() => {
    if (!hasContext || prefillApplied) return
    setFormData(p => ({
      ...p,
      partner_id: prefill.partner_id ?? p.partner_id,
      branch_id: prefill.branch_id ?? p.branch_id,
      doctor_id: prefill.doctor_id ?? p.doctor_id,
      package_id: prefill.package_id ?? p.package_id,
    }))
  }, [hasContext, prefillApplied, prefill])

  // Jump to the first unanswered step once the ids are in state.
  useEffect(() => {
    if (!hasPrefill || prefillApplied || initLoading) return

    const answered = {
      clinic: !!formData.partner_id,
      branch: !!formData.branch_id,
      doctor: !!formData.doctor_id,
    }
    let i = 0
    while (i < STEPS.length && answered[STEPS[i]]) i++
    if (i > 0) setStep(i + 1)
    setPrefillApplied(true)
  }, [hasPrefill, prefillApplied, initLoading, STEPS, formData.partner_id, formData.branch_id, formData.doctor_id])

  /** Drop the prefilled context and take the patient back to the first step. */
  const restartFlow = () => {
    setFormData(p => ({ ...p, partner_id: partners.length === 1 ? partners[0].id : null, branch_id: null, doctor_id: null, package_id: null, res_date: '', res_time: '' }))
    setStep(1)
  }

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

  // Branches belong to a clinic, so they are only fetched once one is chosen.
  // With a single clinic on the site that selection happens automatically above,
  // so this still fires immediately and the clinic step is skipped entirely.
  useEffect(() => {
    if (initLoading) return
    if (!formData.partner_id) {
      setBranches([])
      return
    }
    setBranchesLoading(true)
    Api.getBranches(formData.partner_id)
      .then(data => {
        setBranches(data || [])
        if (data?.length === 1) setFormData(p => ({ ...p, branch_id: data[0].id }))
      })
      .catch(console.error)
      .finally(() => setBranchesLoading(false))
  }, [formData.partner_id, initLoading])

  // Also fetch when a doctor arrived prefilled from a browse page: we skip
  // the doctor step in that case but still need the name for the summary.
  useEffect(() => {
    if ((current === 'doctor' || formData.doctor_id) && formData.branch_id) {
      setDoctorsLoading(true)
      Api.getDoctors(formData.branch_id)
        .then(data => setDoctors(data || []))
        .catch(console.error)
        .finally(() => setDoctorsLoading(false))
    }
  }, [current, formData.branch_id, formData.doctor_id])

  /* The offer carried in from a "Book" button on an offer card. Fetched only
   * to name it back to the patient on the confirm step — the id itself is
   * already in formData and submits regardless, so a failed lookup or an id
   * that is no longer published simply leaves the row out. */
  useEffect(() => {
    if (!formData.package_id) {
      setChosenOffer(null)
      return
    }
    let alive = true
    Api.getOffers()
      .then(d => {
        if (!alive) return
        setChosenOffer((d?.offers || []).find(o => o.id === formData.package_id) || null)
      })
      .catch(() => { if (alive) setChosenOffer(null) })
    return () => { alive = false }
  }, [formData.package_id])

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

  /* -------------------------------------------------------------------------
   * Validate prefilled ids against what the server actually returned.
   *
   * A stale bookmark or a hand-edited URL can name a branch that belongs to
   * another clinic, or a doctor who does not work at the chosen branch. Rather
   * than submit that, drop the bad id and reopen the step that sets it. Kept
   * as effects (not inline in the fetch callbacks) so the step change is never
   * a side effect of a state updater, which React may run more than once.
   * ---------------------------------------------------------------------- */
  const backToStep = (name) => {
    const i = STEPS.indexOf(name)
    if (i >= 0) setStep(i + 1)
  }

  useEffect(() => {
    if (branchesLoading || !branches.length || !formData.branch_id) return
    if (branches.some(b => b.id === formData.branch_id)) return
    setFormData(p => ({ ...p, branch_id: null, doctor_id: null, res_date: '', res_time: '' }))
    backToStep('branch')
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [branches, branchesLoading, formData.branch_id])

  useEffect(() => {
    if (doctorsLoading || !doctors.length || !formData.doctor_id) return
    if (doctors.some(d => d.id === formData.doctor_id)) return
    setFormData(p => ({ ...p, doctor_id: null, res_date: '', res_time: '' }))
    backToStep('doctor')
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [doctors, doctorsLoading, formData.doctor_id])

  // -- Handlers --
  const handleNext = () => setStep((s) => s + 1)

  /**
   * Going back clears the choice made on the step being left, along with
   * everything downstream of it — otherwise a stale branch or doctor id from
   * the previous clinic survives and gets submitted.
   */
  const handleBack = () => {
    const leaving = STEPS[step - 1]
    if (leaving === 'branch') {
      setFormData(p => ({ ...p, branch_id: null, doctor_id: null, res_date: '', res_time: '' }))
    } else if (leaving === 'doctor') {
      setFormData(p => ({ ...p, doctor_id: null, res_date: '', res_time: '' }))
    } else if (leaving === 'time') {
      setFormData(p => ({ ...p, res_date: '', res_time: '' }))
    }
    setStep((s) => Math.max(1, s - 1))
  }

  /** Picking a clinic invalidates any branch/doctor chosen under another one. */
  const selectPartner = (partnerId) => {
    setFormData(p => ({
      ...p,
      partner_id: partnerId,
      branch_id: null,
      doctor_id: null,
      res_date: '',
      res_time: '',
    }))
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
      setStep(totalSteps + 1)   // past the last step = success screen
      return true
    }
    throw new Error(response?.message || tr(S.booking.errUnable))
  }

  const handleSubmit = async () => {
    if (phoneState.number.length < 5) {
      setError(tr(S.booking.errMobile))
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
      setError(e.message || tr(S.booking.errUnexpected))
    } finally {
      setLoading(false)
    }
  }

  const handleVerifyOtp = async () => {
    if ((otp.code || '').length !== 6) {
      setOtp(p => ({ ...p, error: tr(S.booking.errOtpIncomplete) }))
      return
    }
    setOtp(p => ({ ...p, verifying: true, error: null }))
    try {
      await submitBookingWith(otp.code.trim())
      setOtp(p => ({ ...p, open: false, verifying: false, code: '' }))
    } catch (e) {
      const msg = e.code === 'otp_invalid'
        ? tr(S.booking.errOtpInvalid)
        : (e.message || tr(S.booking.errVerify))
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
        ? (e.message || tr(S.booking.errResendWait))
        : tr(S.booking.errResend)
      setOtp(p => ({ ...p, sending: false, error: msg }))
    }
  }

  const handleCancelBooking = async () => {
    if (phoneState.number.length < 5 || bookingRef.length < 3) {
      setError(tr(S.booking.errMobileRef))
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
      else setError(response?.message || tr(S.booking.errCancelVerify))
    } catch (e) {
      setError(tr(S.booking.errCantFind))
    } finally {
      setLoading(false)
    }
  }

  // -- Helpers --
  const getSelectedBranchName = () => {
    const b = branches.find(b => b.id === formData.branch_id)
    return b ? t(b.name, locale) : ''
  }

  const getSelectedPartnerName = () => {
    const p = partners.find(p => p.id === formData.partner_id)
    return p ? t(p.name, locale) : ''
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
      <div className="absolute inset-0 z-[60] flex items-center justify-center p-6 bg-plum/40 backdrop-blur-sm animate-in fade-in duration-200">
        <div className="bg-white p-6 w-full max-w-sm border border-line rounded-3xl animate-in zoom-in-95 duration-200 relative">
          <button
            onClick={() => setOtp(p => ({ ...p, open: false }))}
            className="absolute top-4 end-4 text-mauve/70 hover:text-plum transition-colors"
            disabled={otp.verifying}
            aria-label={tr(S.booking.close)}
          >
            <X size={20} />
          </button>

          <div className="w-12 h-12 rounded-full bg-success-soft text-success flex items-center justify-center mb-4 mx-auto ring-4 ring-success-soft/60">
            <MessageCircle size={24} />
          </div>
          <h3 className="text-xl font-semibold text-plum text-center mb-1">{tr(S.booking.verifyWa)}</h3>
          <p className="text-mauve text-center mb-6 leading-relaxed text-sm">
            {tr(S.booking.sentCodeA)} <span className="font-semibold text-plum" dir="ltr">{masked}</span>.
          </p>

          <input
            type="text"
            inputMode="numeric"
            pattern="[0-9]*"
            autoFocus
            value={otp.code}
            onChange={e => setOtp(p => ({ ...p, code: e.target.value.replace(/\D/g, '').slice(0, 6), error: null }))}
            className="w-full h-14 px-5 rounded-2xl bg-white border-2 border-line focus:border-plum focus:ring-4 focus:ring-plum/10 font-semibold text-center text-2xl tracking-[0.5em] text-plum outline-none transition-all placeholder:text-mauve/40 placeholder:tracking-normal placeholder:font-normal placeholder:text-base"
            placeholder={tr(S.booking.enterCode)}
            maxLength={6}
            dir="ltr"
          />

          {otp.error && (
            <p className="text-danger text-xs mt-2 text-center font-medium">{otp.error}</p>
          )}

          <button
            onClick={handleVerifyOtp}
            disabled={otp.verifying || otp.code.length !== 6}
            className="w-full mt-5 py-3.5 bg-plum text-white font-semibold hover:bg-rose-deep disabled:opacity-50 disabled:cursor-not-allowed transition-colors duration-150 flex items-center justify-center gap-2"
          >
            {otp.verifying ? <Loader2 className="animate-spin" size={20} /> : tr(S.booking.verifyConfirm)}
          </button>

          <div className="text-center mt-4">
            {otp.cooldown > 0 ? (
              <span className="text-mauve/70 text-xs">{tr(S.booking.resendIn)} {otp.cooldown}s</span>
            ) : (
              <button
                onClick={handleResendOtp}
                disabled={otp.sending}
                className="text-rose-deep text-xs font-semibold hover:text-rose-deep disabled:opacity-50"
              >
                {otp.sending ? tr(S.booking.sending) : tr(S.booking.resend)}
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
      <div className="absolute inset-0 z-[60] flex items-center justify-center p-6 bg-plum/40 backdrop-blur-sm animate-in fade-in duration-200">
        <div className="bg-white p-6 w-full max-w-sm border border-line rounded-3xl transform scale-100 animate-in zoom-in-95 duration-200">
          <div className="w-12 h-12 rounded-full bg-danger-soft text-danger flex items-center justify-center mb-4 mx-auto ring-4 ring-danger-soft/60">
            <AlertCircle size={24} />
          </div>
          <h3 className="text-xl font-semibold text-plum text-center mb-2">{tr(S.booking.attention)}</h3>
          <p className="text-mauve text-center mb-6 leading-relaxed text-sm">{error}</p>
          <button onClick={() => setError(null)} className="w-full py-3.5 bg-plum text-white font-semibold hover:bg-rose-deep transition-colors duration-150">
            {tr(S.booking.dismiss)}
          </button>
        </div>
      </div>
    )
  }

  const renderHeader = () => {
    const progress = (step / totalSteps) * 100;
    const title = {
      clinic: tr(S.booking.tClinic), branch: tr(S.booking.t1), doctor: tr(S.booking.t2),
      time: tr(S.booking.t3), confirm: tr(S.booking.t4),
    }[current]
    const subtitle = {
      clinic: tr(S.booking.sClinic), branch: tr(S.booking.s1), doctor: tr(S.booking.s2),
      time: tr(S.booking.s3), confirm: tr(S.booking.s4),
    }[current]

    return (
      <div className="px-8 pt-8 pb-7 bg-white flex flex-col border-b border-line">
        {step === 1 && (
          <div className="flex mb-7 -mt-1">
            {[
              { key: 'book', label: tr(S.booking.tabNew) },
              { key: 'manage', label: tr(S.booking.tabManage) },
            ].map(tab => (
              <button
                key={tab.key}
                onClick={() => setView(tab.key)}
                className={`flex-1 pb-3 text-[11px] font-semibold uppercase tracking-[0.16em] border-b-2 transition-colors duration-300 ${
                  view === tab.key
                    ? 'border-plum text-plum'
                    : 'border-line text-mauve/70 hover:text-plum'
                }`}
              >
                {tab.label}
              </button>
            ))}
          </div>
        )}

        {view === 'book' ? (
          <div>
            <div className="flex items-baseline gap-3 text-[11px] font-semibold uppercase tracking-[0.2em] text-mauve">
              <span className="tabular-nums text-rose" dir="ltr">
                {String(step).padStart(2, '0')}/{String(totalSteps).padStart(2, '0')}
              </span>
              <span>{tr(S.booking.stepOf)}</span>
            </div>
            <h3 className="font-display text-4xl leading-[1.1] text-plum mt-4">{title}</h3>
            <p className="text-mauve text-sm mt-3 leading-relaxed">{subtitle}</p>

            {/* What was carried over from the page they came from, so the
                skipped steps are visible rather than silently assumed. */}
            {hasPrefill && step > 1 && (
              <div className="mt-5 flex items-start gap-3 rounded-2xl bg-blush/70 border border-line px-4 py-3">
                <Check size={14} strokeWidth={2.4} className="text-rose-deep mt-0.5 shrink-0" />
                <div className="flex-1 min-w-0">
                  <div className="text-[10px] font-medium uppercase tracking-[0.18em] text-mauve/70">
                    {tr(S.booking.preselected)}
                  </div>
                  <div className="text-sm font-medium text-plum truncate">
                    {[formData.doctor_id && getSelectedDoctorName(), formData.branch_id && getSelectedBranchName()]
                      .filter(Boolean).join(' · ')}
                  </div>
                </div>
                <button
                  type="button"
                  onClick={restartFlow}
                  className="text-[11px] font-medium uppercase tracking-[0.14em] text-rose-deep hover:text-plum transition-colors shrink-0"
                >
                  {tr(S.booking.changeSelection)}
                </button>
              </div>
            )}
            {/* Hairline progress — a rule that fills, not a candy bar. */}
            <div className="h-px w-full bg-line mt-7 overflow-hidden">
              <div
                className="h-full bg-plum transition-all duration-500 ease-out"
                style={{ width: `${progress}%` }}
              />
            </div>
          </div>
        ) : (
          <div>
            <h3 className="font-display text-4xl leading-[1.1] text-plum">{tr(S.booking.manageTitle)}</h3>
            <p className="text-mauve text-sm mt-3 leading-relaxed">{tr(S.booking.manageSub)}</p>
          </div>
        )}
      </div>
    )
  }

  const renderFooterNav = (disabled = false, primaryAction = handleNext, primaryLabel = tr(S.booking.next)) => (
    <div className="flex items-center gap-3 pt-6 mt-auto border-t border-line bg-white/50 backdrop-blur-sm sticky bottom-0 z-10">
      {step > 1 && (
        <button
          onClick={handleBack}
          className="w-14 h-14 rounded-full flex items-center justify-center text-mauve border border-line hover:border-rose-deep hover:text-rose-deep transition-colors shrink-0"
          title={tr(S.booking.goBack)}
        >
          <ArrowLeft size={18} strokeWidth={1.6} className="rtl:rotate-180" />
        </button>
      )}
      <button
        onClick={primaryAction}
        disabled={disabled}
        className="flex-1 h-14 rounded-full bg-plum text-ivory text-[12px] font-medium uppercase tracking-[0.16em] hover:bg-rose-deep disabled:opacity-30 disabled:cursor-not-allowed transition-colors flex items-center justify-center gap-3"
      >
        {loading ? <Loader2 className="animate-spin" size={18} /> : (<>{primaryLabel} <ArrowRight size={15} strokeWidth={1.8} className="rtl:rotate-180" /></>)}
      </button>
    </div>
  )

  // Step: pick the clinic. Only rendered when the site lists more than one.
  const renderStepClinic = () => (
    <div className="p-8 h-full flex flex-col animate-in fade-in slide-in-from-right-8 duration-300">
      <div className="flex-1 overflow-y-auto custom-scrollbar pe-2 -me-2">
        {initLoading ? (
          <div className="flex flex-col items-center justify-center py-20 text-mauve/70">
            <Loader2 className="animate-spin mb-2" size={32} />
            <span className="text-sm font-medium">{tr(S.booking.loadingClinicGroups)}</span>
          </div>
        ) : partners.length === 0 ? (
          <div className="text-center py-20 text-mauve/70">{tr(S.booking.noClinicGroups)}</div>
        ) : (
          <div className="grid gap-4">
            {partners.map(partner => {
              const selected = formData.partner_id === partner.id
              return (
                <button
                  key={partner.id}
                  onClick={() => selectPartner(partner.id)}
                  className={`group relative flex items-center p-5 rounded-2xl border-2 transition-all text-start w-full duration-200 ${
                    selected
                      ? 'border-plum bg-petal/40'
                      : 'border-line bg-white hover:border-rose'
                  }`}
                >
                  {partner.logo_path ? (
                    <img
                      src={`/storage/${partner.logo_path}`}
                      alt=""
                      className="w-12 h-12 object-contain bg-white rounded-xl border border-line me-4 shrink-0"
                    />
                  ) : (
                    <div className={`w-12 h-12 flex items-center justify-center me-4 shrink-0 transition-colors ${
                      selected ? 'bg-plum text-ivory' : 'bg-ivory text-mauve group-hover:bg-petal group-hover:text-rose-deep'
                    }`}>
                      <Building2 size={22} />
                    </div>
                  )}
                  <div className="flex-1 min-w-0">
                    <div className={`font-semibold text-lg transition-colors ${selected ? 'text-plum' : 'text-plum'}`}>
                      {t(partner.name, locale)}
                    </div>
                  </div>
                  {selected
                    ? <div className="text-rose-deep bg-white rounded-full p-1 ms-3 shrink-0"><Check size={16} strokeWidth={3} /></div>
                    : <ChevronRight size={18} className="text-mauve/40 ms-3 shrink-0 rtl:rotate-180" />}
                </button>
              )
            })}
          </div>
        )}
      </div>
      {renderFooterNav(!formData.partner_id)}
    </div>
  )

  // Step: branches (of the chosen clinic)
  const renderStep1 = () => (
    <div className="p-8 h-full flex flex-col animate-in fade-in slide-in-from-right-8 duration-300">
      <div className="flex-1 overflow-y-auto custom-scrollbar pe-2 -me-2">
        {branchesLoading ? (
          <div className="flex flex-col items-center justify-center py-20 text-mauve/70">
            <Loader2 className="animate-spin mb-2" size={32} />
            <span className="text-sm font-medium">{tr(S.booking.loadingClinics)}</span>
          </div>
        ) : branches.length === 0 ? (
          <div className="text-center py-20 text-mauve/70">{tr(S.booking.noClinics)}</div>
        ) : (
          <div className="grid gap-4">
            {branches.map(branch => (
              <button
                key={branch.id}
                onClick={() => setFormData(p => ({ ...p, branch_id: branch.id }))}
                className={`group relative flex items-start p-5 rounded-2xl border-2 transition-all text-start w-full duration-200 ${
                  formData.branch_id === branch.id
                    ? 'border-plum bg-petal/40'
                    : 'border-line bg-white hover:border-rose'
                }`}
              >
                <div className={`w-10 h-10 rounded-full flex items-center justify-center me-4 shrink-0 transition-colors ${
                  formData.branch_id === branch.id ? 'bg-plum text-ivory' : 'bg-ivory text-mauve group-hover:bg-petal group-hover:text-rose-deep'
                }`}>
                  <MapPin size={20} />
                </div>
                <div className="flex-1">
                  <div className={`font-semibold text-lg mb-1 transition-colors ${formData.branch_id === branch.id ? 'text-plum' : 'text-plum'}`}>
                    {t(branch.name, locale)}
                  </div>
                  <div className="text-sm text-mauve leading-relaxed">{t(branch.address, locale)}</div>
                </div>
                {formData.branch_id === branch.id && (
                  <div className="absolute top-5 end-5 text-rose-deep bg-white rounded-full p-1">
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
      <div className="flex-1 overflow-y-auto custom-scrollbar pe-2 -me-2">
        {doctorsLoading ? (
           <div className="flex flex-col items-center justify-center py-20 text-mauve/70">
             <Loader2 className="animate-spin mb-2" size={32} />
             <span className="text-sm font-medium">{tr(S.booking.findingSpecialists)}</span>
           </div>
        ) : doctors.length === 0 ? (
          <div className="text-center py-20 text-mauve/70">{tr(S.booking.noDoctors)}</div>
        ) : (
          <div className="grid gap-3">
            {doctors.map(doc => (
              <button
                key={doc.id}
                onClick={() => setFormData(p => ({ ...p, doctor_id: doc.id }))}
                className={`group flex items-center p-4 rounded-2xl border transition-all text-start w-full duration-200 ${
                  formData.doctor_id === doc.id
                    ? 'border-plum bg-petal/40'
                    : 'border-line bg-white hover:border-rose'
                }`}
              >
                <div className={`w-14 h-14 rounded-full me-4 overflow-hidden border-2 shrink-0 ${formData.doctor_id === doc.id ? 'border-plum' : 'border-line'}`}>
                  {doc.avatar_path ? (
                    <img
                      src={doc.avatar_path.startsWith('http') ? doc.avatar_path : `/storage/${doc.avatar_path}`}
                      className="w-full h-full object-cover"
                      onError={e => e.target.style.display='none'}
                      alt={doc.name}
                    />
                  ) : (
                    // Initials read as a considered mark; a generic person glyph
                    // reads as missing data — and these are the faces patients
                    // are choosing between.
                    <div className="w-full h-full bg-petal/70 flex items-center justify-center">
                      <span className="font-display text-lg leading-none text-rose-deep">
                        {monogramOf(doc.name)}
                      </span>
                    </div>
                  )}
                </div>
                <div className="flex-1 min-w-0">
                  <div className="font-semibold text-plum truncate text-lg">{doc.name}</div>
                  <div className="text-rose-deep text-sm font-medium flex items-center gap-1.5 mt-0.5">
                    <Stethoscope size={14} />
                    <span className="truncate">{doc.specialty}</span>
                  </div>
                </div>
                {formData.doctor_id === doc.id ? (
                   <Check className="text-rose-deep ms-2" strokeWidth={3} size={20} />
                ) : (
                   <ChevronRight className="text-mauve/40 ms-2 group-hover:text-rose rtl:rotate-180" size={20} />
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
      <div className="flex-1 overflow-y-auto custom-scrollbar pe-2 -me-2 space-y-6">

        {/* Date picker — our own calendar, not <input type="date">: the
            native popup is browser chrome and cannot be themed. */}
        <BookingCalendar
          value={formData.res_date}
          onChange={(v) => setFormData(p => ({ ...p, res_date: v, res_time: '' }))}
        />

        {/* Slots Area */}
        <div className="min-h-[200px]">
          {slotsLoading ? (
            <div className="flex flex-col items-center justify-center py-10 text-mauve/70 opacity-70">
              <Loader2 className="animate-spin mb-3" size={28} />
              <span className="text-sm">{tr(S.booking.checkingAvail)}</span>
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
                      className={`py-3 px-2 rounded-xl font-medium text-sm transition-all duration-200 ${
                        isSelected
                          ? 'bg-plum text-white scale-105'
                          : 'bg-white border border-line text-mauve hover:border-rose hover:text-rose-deep hover:bg-petal/50'
                      }`}
                    >
                      {lbl}
                    </button>
                  )
                })}
            </div>
          ) : formData.res_date ? (
            <div className="flex flex-col items-center justify-center py-10 rounded-2xl bg-warn-soft border border-warn/20 text-warn">
              <Clock className="mb-2 opacity-50" size={24} />
              <span className="font-semibold">{tr(S.booking.noSlots)}</span>
              <span className="text-xs mt-1 opacity-70">{tr(S.booking.noSlotsHint)}</span>
            </div>
          ) : (
            <div className="text-center py-10 text-mauve/70 flex flex-col items-center">
              <Calendar size={32} className="mb-2 opacity-20" />
              <span className="text-sm">{tr(S.booking.pickDate)}</span>
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
      <div className="flex-1 space-y-6 overflow-y-auto custom-scrollbar pe-2 -me-2">

        {/* Booking Recap Card */}
        <div className="bg-ivory p-5 rounded-2xl border border-line relative overflow-hidden">
          
          <h4 className="text-xs font-semibold uppercase tracking-wider text-mauve/70 mb-4 flex items-center gap-2">
            <Info size={14} /> {tr(S.booking.summary)}
          </h4>

          <div className="space-y-4">
             {STEPS.includes('clinic') && (
               <div className="flex items-start gap-3">
                  <Building2 className="text-rose mt-1" size={16} />
                  <div>
                     <div className="font-semibold text-plum text-sm">{getSelectedPartnerName()}</div>
                     <div className="text-xs text-mauve">{tr(S.booking.selectedGroup)}</div>
                  </div>
               </div>
             )}
             <div className="flex items-start gap-3">
                <MapPin className="text-rose mt-1" size={16} />
                <div>
                   <div className="font-semibold text-plum text-sm">{getSelectedBranchName()}</div>
                   <div className="text-xs text-mauve">{tr(S.booking.selectedClinic)}</div>
                </div>
             </div>
             <div className="flex items-start gap-3">
                <User className="text-rose mt-1" size={16} />
                <div>
                   <div className="font-semibold text-plum text-sm">{getSelectedDoctorName()}</div>
                   <div className="text-xs text-mauve">{tr(S.booking.specialist)}</div>
                </div>
             </div>
             {chosenOffer && (
               <div className="flex items-start gap-3">
                  <Sparkles className="text-rose mt-1" size={16} />
                  <div>
                     <div className="font-semibold text-plum text-sm">{chosenOffer.name}</div>
                     <div className="text-xs text-mauve">
                        {tr(S.booking.selectedOffer)}
                        {chosenOffer.has_discount && (
                          <>
                            {' · '}
                            <span className="text-rose-deep font-semibold tabular-nums" dir="ltr">
                              {formatPrice(chosenOffer.offer_price)} {tr(S.offers.currency)}
                            </span>
                          </>
                        )}
                     </div>
                  </div>
               </div>
             )}
             <div className="flex items-start gap-3">
                <CalendarDays className="text-rose mt-1" size={16} />
                <div>
                   <div className="font-semibold text-plum text-sm">
                      {formatDateDisplay(formData.res_date)} • {formatTimeDisplay(formData.res_time)}
                   </div>
                   <div className="text-xs text-mauve">{tr(S.booking.apptTime)}</div>
                </div>
             </div>
          </div>
        </div>

        {/* Input Fields */}
        <div className="space-y-4 pt-2">
          <div>
            <label className="block text-xs font-semibold text-mauve uppercase tracking-wider mb-2 ms-1">{tr(S.booking.patientName)}</label>
            <input
              type="text"
              value={formData.name}
              onChange={e => setFormData(p => ({ ...p, name: e.target.value }))}
              className="w-full h-14 px-5 rounded-2xl bg-white border-2 border-line focus:border-plum focus:ring-4 focus:ring-plum/10 font-semibold transition-all outline-none text-plum placeholder:text-mauve/40"
              placeholder={tr(S.booking.patientNamePh)}
            />
          </div>
          <div>
            <label className="block text-xs font-semibold text-mauve uppercase tracking-wider mb-2 ms-1">{tr(S.booking.mobile)}</label>
            <PhoneInput
              value={phoneState.number}
              onChange={e => setPhoneState(p => ({ ...p, number: e.target.value.replace(/\D/g, '') }))}
              code={phoneState.code}
              onCodeChange={c => setPhoneState(p => ({ ...p, code: c }))}
            />
          </div>
        </div>
      </div>
      {renderFooterNav(!formData.name || !phoneState.number || loading, handleSubmit, tr(S.booking.confirm))}
    </div>
  )

  const renderSuccess = () => (
    <div className="p-8 h-full flex flex-col items-center justify-center text-center animate-in zoom-in-95 duration-500">
        <div className="w-24 h-24 bg-plum text-ivory rounded-full flex items-center justify-center mb-8 relative">
           <Check size={48} strokeWidth={3} />
           <div className="absolute inset-0 rounded-full border border-rose/30 animate-ping opacity-20"></div>
        </div>
        <h3 className="text-3xl font-semibold mb-2 text-plum">{tr(S.booking.confirmedTitle)}</h3>
        <p className="text-mauve mb-8 max-w-[250px]">{tr(S.booking.confirmedSub)}</p>

        <div className="bg-ivory p-6 w-full rounded-2xl border border-line mb-8">
           <div className="text-xs font-semibold text-mauve/70 uppercase tracking-wider mb-1">{tr(S.booking.bookingRef)}</div>
           <div className="font-mono text-3xl font-semibold text-plum tracking-wider select-all" dir="ltr">{completedBooking?.booking_code}</div>
        </div>

        <button
          onClick={() => window.location.reload()}
          className="text-rose-deep font-semibold hover:bg-petal/50 px-6 py-3 transition-colors"
        >
          {tr(S.booking.bookAnother)}
        </button>
    </div>
  )

  const renderManage = () => manageSuccess ? (
    <div className="p-8 h-full flex flex-col items-center justify-center text-center animate-in zoom-in-95 duration-300">
        <div className="w-20 h-20 bg-danger-soft text-danger rounded-full flex items-center justify-center mb-6">
          <Trash2 size={32} />
        </div>
        <h3 className="text-2xl font-semibold text-plum mb-2">{tr(S.booking.cancelledTitle)}</h3>
        <p className="text-mauve mb-8">{tr(S.booking.cancelledSub)}</p>
        <button
          onClick={() => { setView('book'); setManageSuccess(false); }}
          className="mt-4 text-white bg-plum hover:bg-rose-deep px-8 py-3.5 font-semibold transition-all"
        >
          {tr(S.booking.tabNew)}
        </button>
    </div>
  ) : (
    <div className="p-8 h-full flex flex-col animate-in fade-in slide-in-from-right-8 duration-300">
        <div className="space-y-6 flex-1">
            <div className="bg-ivory p-6 rounded-2xl border border-line mb-6">
               <p className="text-sm text-mauve leading-relaxed">
                  {tr(S.booking.manageIntro)}
               </p>
            </div>
            <div>
               <label className="text-xs font-semibold text-mauve uppercase tracking-wider mb-2 ms-1 block">{tr(S.booking.mobile)}</label>
               <PhoneInput
                 value={phoneState.number}
                 onChange={e => setPhoneState(p => ({ ...p, number: e.target.value.replace(/\D/g, '') }))}
                 code={phoneState.code}
                 onCodeChange={c => setPhoneState(p => ({ ...p, code: c }))}
               />
            </div>
            <div>
               <label className="text-xs font-semibold text-mauve uppercase tracking-wider mb-2 ms-1 block">{tr(S.booking.refCode)}</label>
               <input
                 type="text"
                 value={bookingRef}
                 onChange={e => setBookingRef(e.target.value.toUpperCase())}
                 className="w-full h-14 px-5 rounded-2xl bg-white border-2 border-line focus:border-plum focus:ring-4 focus:ring-plum/10 font-semibold tracking-widest text-plum outline-none transition-all placeholder:font-normal placeholder:tracking-normal placeholder:text-mauve/40"
                 placeholder={tr(S.booking.refPh)}
                 dir="ltr"
               />
            </div>
        </div>
        <button
          onClick={handleCancelBooking}
          disabled={loading}
          className="w-full bg-plum text-ivory h-14 font-semibold mt-6 hover:bg-rose-deep transition-colors flex items-center justify-center gap-2"
        >
          {loading ? <Loader2 className="animate-spin" /> : <>{tr(S.booking.cancelBooking)} <ArrowRight size={18} className="rtl:rotate-180" /></>}
        </button>
    </div>
  )

  return (
    <div className="bg-white border border-line rounded-[2rem] shadow-[0_40px_80px_-50px_rgba(42,20,32,0.5)] overflow-hidden w-full max-w-[550px] mx-auto lg:mx-0 lg:ms-auto min-h-[700px] flex flex-col relative z-20 font-sans">
      {renderErrorModal()}
      {renderOtpModal()}
      {!isSuccess && renderHeader()}
      <div className="flex-1 relative bg-white h-full flex flex-col">
        {view === 'manage' ? renderManage() : (
          isSuccess ? renderSuccess()
            : current === 'clinic' ? renderStepClinic()
            : current === 'branch' ? renderStep1()
            : current === 'doctor' ? renderStep2()
            : current === 'time' ? renderStep3()
            : renderStep4()
        )}
      </div>
    </div>
  )
}

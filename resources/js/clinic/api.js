// --- Configuration ---
// No mock flag needed anymore, we always hit the server.

// --- Utils ---
export function getLocale() {
  if (typeof document === 'undefined') return 'en';
  const lang = document.documentElement.lang || 'en'
  return lang.toLowerCase().startsWith('ar') ? 'ar' : 'en'
}

export function t(value, locale) {
  if (value == null) return ''
  if (typeof value === 'string') return value
  if (typeof value === 'object') return value[locale] ?? value.en ?? value.ar ?? ''
  return String(value)
}

export function formatDateDisplay(dateStr) {
  if (!dateStr) return ''
  try {
    return new Date(dateStr).toLocaleDateString('en-US', {
      weekday: 'short', month: 'short', day: 'numeric'
    })
  } catch (e) { return dateStr }
}

export function formatTimeDisplay(timeStr) {
  if (!timeStr) return ''
  const [h, m] = timeStr.split(':')
  const hour = parseInt(h, 10)
  const ampm = hour >= 12 ? 'PM' : 'AM'
  const hour12 = hour % 12 || 12
  return `${hour12}:${m} ${ampm}`
}

export function qs(obj) {
  const p = new URLSearchParams()
  Object.entries(obj || {}).forEach(([k, v]) => {
    if (v === null || v === undefined || v === '') return
    p.set(k, String(v))
  })
  const s = p.toString()
  return s ? `?${s}` : ''
}

export const GULF_CODES = [
  { code: '+965', flag: '🇰🇼', name: 'KW' },
  { code: '+966', flag: '🇸🇦', name: 'SA' },
  { code: '+971', flag: '🇦🇪', name: 'AE' },
  { code: '+974', flag: '🇶🇦', name: 'QA' },
  { code: '+973', flag: '🇧🇭', name: 'BH' },
  { code: '+968', flag: '🇴🇲', name: 'OM' },
]

// --- API Service ---
export const Api = {
  getCsrfToken: () => {
      if (typeof document === 'undefined') return '';
      return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
  },

  getPartners: async () => {
    const res = await fetch('/clinic/api/partners', { headers: { 'Accept': 'application/json' } })
    if (!res.ok) throw new Error('Failed to fetch partners')
    return await res.json()
  },
  
  // Gets simple list for dropdowns
  getBranches: async (partnerId = null) => {
    let url = '/clinic/api/branches'
    if (partnerId) url += `?partner_id=${partnerId}`
    const res = await fetch(url, { headers: { 'Accept': 'application/json' } })
    if (!res.ok) throw new Error('Failed to fetch branches')
    return await res.json()
  },

  getDoctors: async (branchId) => {
    const res = await fetch(`/clinic/api/doctors?branch_id=${branchId}`, { headers: { 'Accept': 'application/json' } })
    if (!res.ok) throw new Error('Failed to fetch doctors')
    return await res.json()
  },

  getSlots: async (branchId, doctorId, date, partySize = 1) => {
    const query = new URLSearchParams({
      branch_id: branchId,
      doctor_id: doctorId,
      date,
      party_size: partySize
    }).toString()

    const res = await fetch(`/clinic/api/slots?${query}`, { headers: { 'Accept': 'application/json' } })
    if (!res.ok) throw new Error('Failed to fetch slots')

    const data = await res.json()
    // Laravel controller returns object/array of slots
    if (Array.isArray(data) && typeof data[0] === 'string') {
      return data.map(time => ({ value: time, label: formatTimeDisplay(time) }))
    }
    return data
  },

  submitBooking: async (payload) => {
    const res = await fetch('/clinic/api/bookings', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': Api.getCsrfToken()
      },
      body: JSON.stringify(payload)
    })

    const data = await res.json()
    if (!res.ok) {
      const err = new Error(data.message || 'Booking Failed')
      err.status = res.status
      err.code = data.code || null
      throw err
    }
    return data
  },

  requestBookingOtp: async (msisdn) => {
    const res = await fetch('/clinic/api/bookings/request-otp', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': Api.getCsrfToken()
      },
      body: JSON.stringify({ msisdn })
    })

    const data = await res.json()
    if (!res.ok) {
      const err = new Error(data.message || 'Failed to send verification code')
      err.status = res.status
      throw err
    }
    return data
  },

  cancelBooking: async (payload) => {
    const res = await fetch('/clinic/api/bookings/cancel', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': Api.getCsrfToken()
      },
      body: JSON.stringify(payload)
    })

    const data = await res.json()
    if (!res.ok) throw new Error(data.message || 'Cancellation Failed')
    return data
  },

  // --- Browsing APIs ---
  getServices: async () => {
    const res = await fetch('/clinic/api/services', { headers: { 'Accept': 'application/json' } })
    if (!res.ok) throw new Error('Failed to fetch services')
    return await res.json()
  },

  getBranchesIndex: async (filters = {}) => {
    const res = await fetch(`/clinic/api/branches/index${qs(filters)}`, { headers: { 'Accept': 'application/json' } })
    if (!res.ok) throw new Error('Failed to fetch clinics')
    return await res.json()
  },

  getBranchBySlug: async (slug) => {
    // Matches Laravel Route: /api/branches/{branch:slug}
    const res = await fetch(`/clinic/api/branches/${encodeURIComponent(slug)}`, { headers: { 'Accept': 'application/json' } })
    if (!res.ok) throw new Error('Failed to fetch clinic details')
    return await res.json()
  },

  getDoctorById: async (id) => {
    // Matches Laravel Route: /api/doctors/{doctor}
    const res = await fetch(`/clinic/api/doctors/${encodeURIComponent(id)}`, { headers: { 'Accept': 'application/json' } })
    if (!res.ok) throw new Error('Failed to fetch doctor details')
    return await res.json()
  },
}
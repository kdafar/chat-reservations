import React, { useEffect, useState } from 'react'
import { createRoot } from 'react-dom/client'
import { BrowserRouter, Routes, Route } from 'react-router-dom'
import { Navbar, Footer } from './components/Shared'
import { Hero, StatsSection, LandingServicesSection, InfoSection } from './components/Landing'
import { ClinicsPage, ClinicDetailsPage, ServicesPage, DoctorDetailsPage } from './pages/Browse'
import { Api } from './api'

function BookLanding() {
  const [stats, setStats] = useState(null)
  useEffect(() => { Api.getStats().then(setStats).catch(() => {}) }, [])
  return (
    <div className="min-h-screen bg-white font-sans text-slate-900">
      <Hero stats={stats} />
      <StatsSection stats={stats} />
      <LandingServicesSection />
      <InfoSection />
    </div>
  )
}

function App() {
  return (
    <BrowserRouter>
      <div className="min-h-screen bg-white font-sans text-slate-900 selection:bg-teal-100 selection:text-teal-900">
        <Navbar />
        <Routes>
          <Route path="/" element={<BookLanding />} />
          <Route path="/clinic/book" element={<BookLanding />} />
          <Route path="/clinic/clinics" element={<ClinicsPage />} />
          <Route path="/clinic/clinics/:slug" element={<ClinicDetailsPage />} />
          <Route path="/clinic/services" element={<ServicesPage />} />
          <Route path="/clinic/doctors/:id" element={<DoctorDetailsPage />} />
          <Route path="*" element={<BookLanding />} />
        </Routes>
        <Footer />
      </div>
    </BrowserRouter>
  )
}

const mount = document.getElementById('clinic-root') || document.getElementById('root') || document.body
const root = createRoot(mount)
root.render(<App />)
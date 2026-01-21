import React from 'react'
import { createRoot } from 'react-dom/client'
import { BrowserRouter, Routes, Route } from 'react-router-dom'
import { Navbar, Footer } from './components/Shared'
import { Hero, StatsSection, LandingServicesSection, InfoSection } from './components/Landing'
import { ClinicsPage, ClinicDetailsPage, ServicesPage, DoctorDetailsPage } from './pages/Browse'

function BookLanding() {
  return (
    <div className="min-h-screen bg-white font-sans text-slate-900">
      <Hero />
      <StatsSection />
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

const root = createRoot(document.getElementById('root') || document.body)
root.render(<App />)
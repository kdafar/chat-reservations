import React, { useEffect, useState } from 'react'
import { createRoot } from 'react-dom/client'
import { BrowserRouter, Routes, Route, useLocation } from 'react-router-dom'
import { Navbar, Footer } from './components/Shared'
import { Hero, StatsSection, LandingServicesSection, StandardSection, InfoSection } from './components/Landing'
import { ClinicsPage, ClinicDetailsPage, ServicesPage, DoctorDetailsPage } from './pages/Browse'
import { OffersSection, OffersPage } from './components/Offers'
import { GallerySection, GalleryPage } from './components/Gallery'
import { Api } from './api'

function BookLanding() {
  const [stats, setStats] = useState(null)
  useEffect(() => { Api.getStats().then(setStats).catch(() => {}) }, [])
  return (
    <div className="min-h-screen bg-ivory font-sans text-plum">
      <Hero stats={stats} />
      <StatsSection stats={stats} />
      <OffersSection />
      <LandingServicesSection />
      <GallerySection />
      <StandardSection />
      <InfoSection />
    </div>
  )
}

/**
 * Reset the scroll position on every route change.
 *
 * React Router keeps the window scroll across navigations, so clicking a link
 * in the fixed header while scrolled down landed you at the same offset on the
 * next page — usually somewhere in the footer. A hash (#contact, #book) still
 * wins: we scroll to that element instead of the top.
 */
function ScrollToTop() {
  const { pathname, hash } = useLocation()

  useEffect(() => {
    if (hash) {
      // The target may not have rendered yet (data still loading), so give it
      // a couple of frames before falling back to the top of the page.
      let tries = 0
      const tick = () => {
        const el = document.getElementById(hash.slice(1))
        if (el) { el.scrollIntoView(); return }
        if (tries++ < 20) setTimeout(tick, 60)
      }
      tick()
      return
    }
    window.scrollTo({ top: 0, left: 0, behavior: 'auto' })
  }, [pathname, hash])

  return null
}

function App() {
  return (
    <BrowserRouter>
      <div className="min-h-screen bg-ivory font-sans text-plum selection:bg-petal selection:text-plum">
        <ScrollToTop />
        <Navbar />
        <Routes>
          <Route path="/" element={<BookLanding />} />
          <Route path="/clinic/book" element={<BookLanding />} />
          <Route path="/clinic/clinics" element={<ClinicsPage />} />
          <Route path="/clinic/clinics/:slug" element={<ClinicDetailsPage />} />
          <Route path="/clinic/services" element={<ServicesPage />} />
          <Route path="/clinic/offers" element={<OffersPage />} />
          <Route path="/clinic/gallery" element={<GalleryPage />} />
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
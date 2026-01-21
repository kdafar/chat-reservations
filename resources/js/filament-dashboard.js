if (!window.__execDashListenerRegistered) {
  window.__execDashListenerRegistered = true;

  window.addEventListener('dashboard-updated', (e) => {
    console.log('[dashboard-updated] fired', e.detail);
    window.__execDash = e.detail?.dashboardData ?? null;

    // Delay resize until after Alpine/DOM/mutation observers finish re-init.
    // Two RAFs is a common safe pattern for ECharts + SPA updates.
    requestAnimationFrame(() => {
      requestAnimationFrame(() => {
        window.dispatchEvent(new Event('resize'));
      });
    });
  });

  document.addEventListener('livewire:navigated', () => {
    console.log('[livewire:navigated] execdash listener active');
  });
}

window.addEventListener('filament-scroll-to-relations', () => {
  // Filament v3 container class is typically this:
  const el =
    document.querySelector(".fi-resource-relation-managers") ||
    document.querySelector("[data-filament-relation-managers]") ||
    document.querySelector("#relation-managers");

  if (!el) return;

  // If the page is still hydrating, wait a tick
  setTimeout(() => {
    el.scrollIntoView({ behavior: "smooth", block: "start" });
  }, 50);
});

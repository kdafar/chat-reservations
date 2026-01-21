// resources/js/filament/admin.js

window.addEventListener('filament-scroll-to-relations', () => {
  const el =
    document.querySelector('.fi-resource-relation-managers') ||
    document.querySelector('[data-filament-relation-managers]') ||
    document.querySelector('#relation-managers');

  if (!el) return;

  setTimeout(() => {
    el.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }, 50);
});

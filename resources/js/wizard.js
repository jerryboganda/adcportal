/**
 * ADC Booking Wizard — Alpine + Fetch replacement for jQuery 700-line monolith
 * Phase 1 stub: exposes legacy globals for backward compat while new logic is built.
 * Full extraction in Phase 3 will move form_layout/layout.blade.php inline <script> here.
 */
console.log('[ADC] wizard.js loaded — Alpine + Bootstrap via Vite');

// Legacy globals preserved for current jQuery flow (will be removed Phase 3)
window.ADCWizard = {
  // Placeholder for fetchServicesByCategory -> fetch API
  fetchServicesByCategory(categoryId, businessSlug) {
    return fetch(`/get-services-by-category?category_id=${categoryId}&business_slug=${businessSlug}`)
      .then(r => r.json());
  },
  // Placeholder for Alpine-driven step state
  state: {
    step: 1,
    category: null,
    service: null,
    staff: null,
    location: null,
    date: null,
    slot: null,
    mode: 'new-user'
  }
};

// Alpine component for future wizard
document.addEventListener('alpine:init', () => {
  Alpine.data('bookingWizard', () => ({
    step: 1,
    servicePrices: {},
    next() { this.step++; },
    back() { this.step--; },
    validateStep1() { return true; },
    validateStep2() { return true; }
  }));
});

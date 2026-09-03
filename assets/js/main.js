/**
 * main.js — CampusPark vanilla JS
 * No inline script logic in PHP files; all behaviour is wired here.
 * Functions are called at the bottom via DOMContentLoaded, gated by
 * data-page attribute on <body> so only relevant code runs per page.
 */

'use strict';

/* ==========================================================================
   Rate Calculator (landing page)
   ========================================================================== */

function initRateCalculator() {
    var zoneSelect     = document.getElementById('calcZone');
    var durationSelect = document.getElementById('calcDuration');
    var output         = document.getElementById('rateOutput');

    if (!zoneSelect || !durationSelect || !output) return;

    var rates = [4, 2, 1]; // Premium, Standard, Economy — $/hr

    function update() {
        var rate     = rates[zoneSelect.selectedIndex] || 2;
        var duration = parseInt(durationSelect.value, 10) || 2;
        var total    = (rate * duration).toFixed(2);
        output.textContent = '$' + total;
    }

    zoneSelect.addEventListener('change',     update);
    durationSelect.addEventListener('change', update);
    update(); // initial render
}

/* ==========================================================================
   Booking modal (book-slot page)
   ========================================================================== */

/**
 * Wires every .slot-card--available to open the confirmation modal,
 * and populates the modal fields with that slot's data attributes.
 */
function initBookingModal() {
    const overlay   = document.getElementById('bookingModal');
    const closeBtn  = document.getElementById('modalClose');
    const slotCards = document.querySelectorAll('.slot-card--available');

    if (!overlay) return;

    // Hidden form inputs populated when a slot is selected
    const inputSlotId   = document.getElementById('inputSlotId');
    const inputSlotCode = document.getElementById('inputSlotCode');

    // Modal display fields
    const modalSlotCode = document.getElementById('modalSlotCode');
    const modalZone     = document.getElementById('modalZone');
    const modalDate     = document.getElementById('modalDate');

    function openModal(card) {
        inputSlotId.value   = card.dataset.slotId;
        inputSlotCode.value = card.dataset.slotCode;
        modalSlotCode.textContent = card.dataset.slotCode;
        modalZone.textContent     = card.dataset.zone;
        // Reflect the currently selected date from the date picker
        const datePicker = document.getElementById('bookingDate');
        modalDate.textContent = datePicker ? formatDate(datePicker.value) : '—';
        overlay.classList.add('is-open');
        overlay.setAttribute('aria-hidden', 'false');
        closeBtn.focus();
    }

    function closeModal() {
        overlay.classList.remove('is-open');
        overlay.setAttribute('aria-hidden', 'true');
    }

    slotCards.forEach(function(card) {
        card.addEventListener('click',  function() { openModal(card); });
        card.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                openModal(card);
            }
        });
        card.setAttribute('tabindex', '0');
        card.setAttribute('role', 'button');
    });

    closeBtn.addEventListener('click', closeModal);

    // Close on overlay background click
    overlay.addEventListener('click', function(e) {
        if (e.target === overlay) closeModal();
    });

    // Close on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && overlay.classList.contains('is-open')) {
            closeModal();
        }
    });
}

/* ==========================================================================
   Date picker — reload page when date changes so slot availability refreshes
   ========================================================================== */

function initDatePicker() {
    const picker = document.getElementById('bookingDate');
    if (!picker) return;

    picker.addEventListener('change', function() {
        // Submit the form to reload slot availability for the new date
        const form = document.getElementById('dateFilterForm');
        if (form) form.submit();
    });
}

/* ==========================================================================
   Client-side form validation (auth pages)
   Server-side is authoritative; this is UX sugar only.
   ========================================================================== */

function initFormValidation() {
    const form = document.getElementById('authForm');
    if (!form) return;

    form.addEventListener('submit', function(e) {
        let valid = true;

        // Clear previous client-side errors
        form.querySelectorAll('.form-error[data-client]').forEach(function(el) {
            el.textContent = '';
        });
        form.querySelectorAll('.form-input').forEach(function(el) {
            el.classList.remove('form-input--error');
        });

        const emailInput = form.querySelector('[name="email"]');
        const passInput  = form.querySelector('[name="password"]');
        const confirmInput = form.querySelector('[name="confirm_password"]');
        const nameInput  = form.querySelector('[name="full_name"]');

        if (nameInput && nameInput.value.trim().length < 2) {
            showFieldError(nameInput, 'Please enter your full name.');
            valid = false;
        }

        if (emailInput && !isValidEmail(emailInput.value.trim())) {
            showFieldError(emailInput, 'Please enter a valid email address.');
            valid = false;
        }

        if (passInput && passInput.value.length < 8) {
            showFieldError(passInput, 'Password must be at least 8 characters.');
            valid = false;
        }

        if (confirmInput && passInput && confirmInput.value !== passInput.value) {
            showFieldError(confirmInput, 'Passwords do not match.');
            valid = false;
        }

        if (!valid) e.preventDefault();
    });
}

function showFieldError(input, message) {
    input.classList.add('form-input--error');
    // Find a sibling .form-error[data-client] element to display the message
    const errorEl = input.parentElement.querySelector('.form-error[data-client]');
    if (errorEl) errorEl.textContent = message;
}

function isValidEmail(value) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
}

/* ==========================================================================
   Helpers
   ========================================================================== */

function formatDate(iso) {
    if (!iso) return '—';
    const d = new Date(iso + 'T00:00:00');
    return d.toLocaleDateString('en-US', { weekday: 'short', year: 'numeric', month: 'short', day: 'numeric' });
}

/* ==========================================================================
   Boot — call the right functions based on the page
   ========================================================================== */

document.addEventListener('DOMContentLoaded', function() {
    const page = document.body.dataset.page || '';

    if (page === 'book-slot') {
        initBookingModal();
        initDatePicker();
    }

    if (page === 'signup' || page === 'login') {
        initFormValidation();
    }

    if (page === 'landing') {
        initRateCalculator();
    }
});

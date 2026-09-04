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

    var rates = [4, 2, 1]; // Premium ($4/hr), Standard ($2/hr), Economy ($1/hr)

    function update() {
        var rateIndex = zoneSelect.selectedIndex;
        var rate = rates[rateIndex] !== undefined ? rates[rateIndex] : 2;

        var val = durationSelect.value;
        if (val === 'semester' || val === 'pass') {
            output.textContent = '$150.00';
            return;
        }

        var duration = parseInt(val, 10) || 2;
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
/* ==========================================================================
   Mobile Navigation Menu Toggle
   ========================================================================== */

function initMobileMenu() {
    // 1. landing.html mobile drawer
    const landingBtn  = document.getElementById('mobileMenuBtn');
    const landingMenu = document.getElementById('mobileNavMenu');

    if (landingBtn && landingMenu) {
        landingBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            const isHidden = landingMenu.classList.contains('hidden');
            if (isHidden) {
                landingMenu.classList.remove('hidden');
                landingMenu.classList.add('flex');
                landingBtn.setAttribute('aria-expanded', 'true');
            } else {
                landingMenu.classList.add('hidden');
                landingMenu.classList.remove('flex');
                landingBtn.setAttribute('aria-expanded', 'false');
            }
        });

        landingMenu.querySelectorAll('a').forEach(function(link) {
            link.addEventListener('click', function() {
                landingMenu.classList.add('hidden');
                landingMenu.classList.remove('flex');
                landingBtn.setAttribute('aria-expanded', 'false');
            });
        });
    }

    // 2. header.php mobile drawer
    const phpBtn  = document.getElementById('navbarHamburger');
    const phpMenu = document.getElementById('navbarMobileMenu');

    if (phpBtn && phpMenu) {
        phpBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            const isOpen = phpMenu.classList.contains('is-open');
            if (isOpen) {
                phpMenu.classList.remove('is-open');
                phpMenu.setAttribute('aria-hidden', 'true');
                phpBtn.setAttribute('aria-expanded', 'false');
            } else {
                phpMenu.classList.add('is-open');
                phpMenu.setAttribute('aria-hidden', 'false');
                phpBtn.setAttribute('aria-expanded', 'true');
            }
        });
    }

    // Close active mobile menus when clicking outside
    document.addEventListener('click', function(e) {
        if (landingBtn && landingMenu && !landingBtn.contains(e.target) && !landingMenu.contains(e.target)) {
            landingMenu.classList.add('hidden');
            landingMenu.classList.remove('flex');
            landingBtn.setAttribute('aria-expanded', 'false');
        }
        if (phpBtn && phpMenu && !phpBtn.contains(e.target) && !phpMenu.contains(e.target)) {
            phpMenu.classList.remove('is-open');
            phpMenu.setAttribute('aria-hidden', 'true');
            phpBtn.setAttribute('aria-expanded', 'false');
        }
    });
}

/* ==========================================================================
   Dark Mode Theme Toggle
   ========================================================================== */

function initThemeToggle() {
    const toggleBtns = document.querySelectorAll('.theme-toggle-btn');
    if (!toggleBtns.length) return;

    function updateUI(isDark) {
        toggleBtns.forEach(function(btn) {
            const iconSpan = btn.classList.contains('material-symbols-outlined')
                ? btn
                : btn.querySelector('.material-symbols-outlined');
            if (iconSpan) {
                iconSpan.textContent = isDark ? 'light_mode' : 'dark_mode';
            }
            const labelSpan = btn.querySelector('.theme-toggle-label');
            if (labelSpan) {
                labelSpan.textContent = isDark ? 'Light Mode' : 'Dark Mode';
            }
        });
    }

    const isDark = document.documentElement.classList.contains('dark');
    updateUI(isDark);

    toggleBtns.forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const currentlyDark = document.documentElement.classList.contains('dark');
            if (currentlyDark) {
                document.documentElement.classList.remove('dark');
                localStorage.setItem('theme', 'light');
                updateUI(false);
                window.dispatchEvent(new CustomEvent('themeChange', { detail: { isDark: false } }));
            } else {
                document.documentElement.classList.add('dark');
                localStorage.setItem('theme', 'dark');
                updateUI(true);
                window.dispatchEvent(new CustomEvent('themeChange', { detail: { isDark: true } }));
            }
        });
    });
}

/* ==========================================================================
   Interactive Campus Parking Map (Leaflet.js)
   ========================================================================== */

function initCampusMap() {
    var mapEl = document.getElementById('campusMap');
    if (!mapEl || typeof L === 'undefined') return;

    // Center coordinates for campus (UC Berkeley / University Glade Area)
    var campusCenter = [37.8719, -122.2585];
    var map = L.map('campusMap', {
        center: campusCenter,
        zoom: 16,
        minZoom: 14,
        maxZoom: 18,
        zoomControl: false,
        attributionControl: false
    });

    // Theme-aware tile layers: CartoDB Dark Matter & Voyager
    var darkTiles = L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', {
        subdomains: 'abcd',
        maxZoom: 19
    });
    var lightTiles = L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
        subdomains: 'abcd',
        maxZoom: 19
    });

    var isDark = document.documentElement.classList.contains('dark');
    var currentTileLayer = isDark ? darkTiles : lightTiles;
    currentTileLayer.addTo(map);

    // Switch tiles when navbar theme toggle is clicked
    window.addEventListener('themeChange', function(e) {
        map.removeLayer(currentTileLayer);
        currentTileLayer = e.detail && e.detail.isDark ? darkTiles : lightTiles;
        currentTileLayer.addTo(map);
    });

    // Campus Parking Zones Data
    var zones = [
        {
            id: 'A',
            name: 'Zone A — Core Campus',
            landmark: 'Main Library & Student Union',
            rate: '$4.00/hr',
            totalSpots: 45,
            availableSpots: 8,
            statusText: 'Filling Fast',
            badgeBg: '#FFF1F2',
            badgeColor: '#9F3C27',
            color: '#E06C53',
            walkTime: '2 min walk to Lecture Halls',
            center: [37.8724, -122.2598],
            polygon: [
                [37.8732, -122.2610],
                [37.8735, -122.2588],
                [37.8718, -122.2585],
                [37.8715, -122.2607]
            ]
        },
        {
            id: 'B',
            name: 'Zone B — Outer Lots',
            landmark: 'Science & Engineering Complex',
            rate: '$2.00/hr',
            totalSpots: 120,
            availableSpots: 42,
            statusText: 'Good Availability',
            badgeBg: '#F5F3FF',
            badgeColor: '#6D28D9',
            color: '#8B5CF6',
            walkTime: '5 min walk to STEM Labs',
            center: [37.8748, -122.2570],
            polygon: [
                [37.8756, -122.2582],
                [37.8758, -122.2558],
                [37.8739, -122.2555],
                [37.8738, -122.2579]
            ]
        },
        {
            id: 'C',
            name: 'Zone C — Stadium & Athletics',
            landmark: 'East Campus Sports Pavilion',
            rate: '$1.00/hr',
            totalSpots: 200,
            availableSpots: 115,
            statusText: 'High Availability',
            badgeBg: '#F0FDF4',
            badgeColor: '#047857',
            color: '#10B981',
            walkTime: '8 min walk (Campus Shuttle every 5m)',
            center: [37.8702, -122.2530],
            polygon: [
                [37.8712, -122.2545],
                [37.8714, -122.2515],
                [37.8690, -122.2512],
                [37.8688, -122.2542]
            ]
        },
        {
            id: 'D',
            name: 'Zone D — Health & Medical',
            landmark: 'West Campus Health Center',
            rate: '$3.00/hr',
            totalSpots: 60,
            availableSpots: 14,
            statusText: 'Moderate',
            badgeBg: '#FFFBEB',
            badgeColor: '#B45309',
            color: '#F59E0B',
            walkTime: '4 min walk to Medical Wing & Clinic',
            center: [37.8698, -122.2642],
            polygon: [
                [37.8706, -122.2655],
                [37.8708, -122.2628],
                [37.8688, -122.2625],
                [37.8686, -122.2652]
            ]
        }
    ];

    var zoneMarkers = {};

    zones.forEach(function(zone) {
        // Boundary polygon overlay
        var polygonLayer = L.polygon(zone.polygon, {
            color: zone.color,
            weight: 2,
            opacity: 0.85,
            fillColor: zone.color,
            fillOpacity: 0.20,
            dashArray: '4, 6'
        }).addTo(map);

        // Custom pulsing radar marker HTML
        var markerHtml = `
            <div class="map-radar-marker" aria-label="${zone.name}">
                <div class="radar-beacon" style="background-color: ${zone.color};"></div>
                <div class="radar-core" style="background-color: ${zone.color};"></div>
                <div class="radar-pill">
                    <span style="width:7px; height:7px; border-radius:50%; background:${zone.color};"></span>
                    <span>Zone ${zone.id} &bull; ${zone.availableSpots} Free</span>
                </div>
            </div>
        `;

        var customIcon = L.divIcon({
            html: markerHtml,
            className: '',
            iconSize: [30, 30],
            iconAnchor: [15, 15],
            popupAnchor: [0, -28]
        });

        var usedSpots = zone.totalSpots - zone.availableSpots;
        var occupancyPct = Math.round((usedSpots / zone.totalSpots) * 100);

        var popupContent = `
            <div class="campus-map-popup">
                <div class="campus-map-popup-header">
                    <div>
                        <div class="campus-map-popup-title">${zone.name}</div>
                        <div class="campus-map-popup-subtitle">${zone.landmark}</div>
                    </div>
                    <span class="campus-map-popup-badge" style="background:${zone.badgeBg}; color:${zone.badgeColor};">
                        ${zone.statusText}
                    </span>
                </div>
                <div class="campus-map-popup-stat-grid">
                    <div>
                        <div class="campus-map-popup-stat-label">Hourly Rate</div>
                        <div class="campus-map-popup-stat-val">${zone.rate}</div>
                    </div>
                    <div>
                        <div class="campus-map-popup-stat-label">Available Slots</div>
                        <div class="campus-map-popup-stat-val" style="color:${zone.color};">${zone.availableSpots} / ${zone.totalSpots}</div>
                    </div>
                </div>
                <div>
                    <div style="display:flex; justify-content:space-between; font-size:11px; margin-bottom:3px; color:#94A3B8;">
                        <span>Capacity Filled</span>
                        <span><strong>${occupancyPct}%</strong></span>
                    </div>
                    <div class="campus-map-popup-progress-track">
                        <div class="campus-map-popup-progress-bar" style="width:${occupancyPct}%; background:${zone.color};"></div>
                    </div>
                </div>
                <div class="campus-map-popup-walk">
                    <span class="material-symbols-outlined" style="font-size:15px; color:${zone.color};">directions_walk</span>
                    <span>${zone.walkTime}</span>
                </div>
                <a href="/parking-system/public/book-slot.php" class="campus-map-popup-btn">
                    <span>Reserve in Zone ${zone.id}</span>
                    <span class="material-symbols-outlined" style="font-size:16px;">arrow_forward</span>
                </a>
            </div>
        `;

        var marker = L.marker(zone.center, { icon: customIcon }).addTo(map);
        marker.bindPopup(popupContent, { maxWidth: 320 });
        zoneMarkers[zone.id] = marker;

        polygonLayer.on('click', function() {
            marker.openPopup();
        });
    });

    // Custom Map Controls (+, -, Recenter)
    var zoomInBtn = document.getElementById('mapZoomIn');
    var zoomOutBtn = document.getElementById('mapZoomOut');
    var recenterBtn = document.getElementById('mapRecenter');

    if (zoomInBtn) {
        zoomInBtn.addEventListener('click', function(e) {
            e.preventDefault();
            map.zoomIn();
        });
    }
    if (zoomOutBtn) {
        zoomOutBtn.addEventListener('click', function(e) {
            e.preventDefault();
            map.zoomOut();
        });
    }
    if (recenterBtn) {
        recenterBtn.addEventListener('click', function(e) {
            e.preventDefault();
            map.flyTo(campusCenter, 16, { duration: 1.2 });
            setActiveZoneButton('all');
        });
    }

    // Filter Buttons logic
    var filterBtns = document.querySelectorAll('.map-zone-btn');
    function setActiveZoneButton(zoneId) {
        filterBtns.forEach(function(btn) {
            var isCurrent = btn.dataset.zone === zoneId;
            btn.classList.toggle('map-zone-btn--active', isCurrent);
            btn.setAttribute('aria-selected', isCurrent ? 'true' : 'false');
        });
    }

    filterBtns.forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            var zoneId = btn.dataset.zone;
            setActiveZoneButton(zoneId);

            if (zoneId === 'all') {
                map.flyTo(campusCenter, 16, { duration: 1.2 });
                map.closePopup();
            } else if (zoneMarkers[zoneId]) {
                var targetZone = zones.find(function(z) { return z.id === zoneId; });
                if (targetZone) {
                    map.flyTo(targetZone.center, 17, { duration: 1.2 });
                    setTimeout(function() {
                        zoneMarkers[zoneId].openPopup();
                    }, 800);
                }
            }
        });
    });
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
    initThemeToggle();
    initMobileMenu();

    const page = document.body.dataset.page || '';

    if (page === 'book-slot') {
        initBookingModal();
        initDatePicker();
    }

    if (page === 'signup' || page === 'login') {
        initFormValidation();
    }

    if (page === 'landing' || document.getElementById('campusMap')) {
        initRateCalculator();
        initCampusMap();
    }
});

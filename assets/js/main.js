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
   Booking modal — time-block picker (book-slot page)
   ========================================================================== */

/**
 * Wires every .slot-card--available[data-slot-id] to open the confirmation
 * modal and renders 9 time-block toggle buttons based on the card's
 * data-blocked attribute. Enforces contiguous selection.
 */
function initBookingModal() {
    var overlay  = document.getElementById('bookingModal');
    var closeBtn = document.getElementById('modalClose');
    if (!overlay) return;

    // Hidden form inputs
    var inputSlotId    = document.getElementById('inputSlotId');
    var inputStartTime = document.getElementById('inputStartTime');
    var inputEndTime   = document.getElementById('inputEndTime');
    var inputDuration  = document.getElementById('inputDurationHours');

    // Modal display elements
    var modalSlotCode              = document.getElementById('modalSlotCode');
    var modalZone                  = document.getElementById('modalZone');
    var modalDate                  = document.getElementById('modalDate');
    var existingBookingsContainer  = document.getElementById('existingBookingsContainer');
    var existingBookingsCount      = document.getElementById('existingBookingsCount');
    var pickerStartTime            = document.getElementById('pickerStartTime');
    var pickerEndTime              = document.getElementById('pickerEndTime');
    var modalConflictAlert         = document.getElementById('modalConflictAlert');
    var modalConflictMsg           = document.getElementById('modalConflictMsg');
    var timeBlockSummary           = document.getElementById('timeBlockSummary');
    var modalDurationText          = document.getElementById('modalDurationText');
    var modalPointCost             = document.getElementById('modalPointCost');
    var modalUserBalance           = document.getElementById('modalUserBalance');
    var modalRemainingBalance      = document.getElementById('modalRemainingBalance');
    var modalPointsWarning         = document.getElementById('modalPointsWarning');
    var modalWarningCost           = document.getElementById('modalWarningCost');
    var modalSubmitBtn             = document.getElementById('modalSubmitBtn');

    var userPoints = parseFloat(overlay.dataset.userPoints) || 0;
    var blockedRanges = []; // [{start, end, mine}]

    function pad2(n) {
        return n < 10 ? '0' + n : '' + n;
    }

    function timeToSeconds(t) {
        if (!t) return 0;
        var parts = t.split(':');
        var h = parseInt(parts[0], 10) || 0;
        var m = parseInt(parts[1], 10) || 0;
        var s = parseInt(parts[2], 10) || 0;
        return h * 3600 + m * 60 + s;
    }

    function secondsToTime(sec) {
        sec = Math.max(0, Math.min(86399, sec));
        var h = Math.floor(sec / 3600);
        var m = Math.floor((sec % 3600) / 60);
        var s = sec % 60;
        return pad2(h) + ':' + pad2(m) + ':' + pad2(s);
    }

    function formatTime12(t) {
        if (!t) return '—';
        var parts = t.split(':');
        var h = parseInt(parts[0], 10) || 0;
        var m = parseInt(parts[1], 10) || 0;
        var s = parseInt(parts[2], 10) || 0;
        var ampm = h >= 12 ? 'PM' : 'AM';
        var h12 = h % 12;
        if (h12 === 0) h12 = 12;
        if (s > 0) {
            return h12 + ':' + pad2(m) + ':' + pad2(s) + ' ' + ampm;
        }
        return h12 + ':' + pad2(m) + ' ' + ampm;
    }

    function normalizeTimeInput(val) {
        if (!val) return '';
        var parts = val.trim().split(':');
        if (parts.length === 2) {
            return pad2(parseInt(parts[0], 10) || 0) + ':' + pad2(parseInt(parts[1], 10) || 0) + ':00';
        }
        if (parts.length >= 3) {
            return pad2(parseInt(parts[0], 10) || 0) + ':' + pad2(parseInt(parts[1], 10) || 0) + ':' + pad2(parseInt(parts[2], 10) || 0);
        }
        return val;
    }

    function renderExistingBookings() {
        if (!existingBookingsContainer) return;
        existingBookingsContainer.innerHTML = '';

        if (!blockedRanges || blockedRanges.length === 0) {
            if (existingBookingsCount) existingBookingsCount.textContent = 'None';
            var emptyNotice = document.createElement('div');
            emptyNotice.className = 'no-bookings-notice';
            emptyNotice.innerHTML = '<span class="material-symbols-outlined" style="font-size:16px;color:var(--clr-success);vertical-align:middle;margin-right:4px;">check_circle</span> No bookings on this slot for this date. Fully open!';
            existingBookingsContainer.appendChild(emptyNotice);
            return;
        }

        if (existingBookingsCount) {
            existingBookingsCount.textContent = blockedRanges.length + (blockedRanges.length > 1 ? ' bookings' : ' booking');
        }

        var list = document.createElement('div');
        list.className = 'existing-bookings-list';

        blockedRanges.forEach(function(r) {
            var chip = document.createElement('div');
            chip.className = 'booking-chip ' + (r.mine ? 'booking-chip--mine' : 'booking-chip--other');
            chip.innerHTML = '<span class="material-symbols-outlined" style="font-size:14px;vertical-align:middle;margin-right:4px;">schedule</span> ' +
                             formatTime12(r.start) + ' – ' + formatTime12(r.end) +
                             ' <span class="booking-chip-tag">' + (r.mine ? 'Your Booking' : 'Booked') + '</span>';
            list.appendChild(chip);
        });

        existingBookingsContainer.appendChild(list);
    }

    function validateAndUpdate() {
        if (!pickerStartTime || !pickerEndTime) return;

        var startVal = normalizeTimeInput(pickerStartTime.value);
        var endVal   = normalizeTimeInput(pickerEndTime.value);

        if (inputStartTime) inputStartTime.value = startVal;
        if (inputEndTime)   inputEndTime.value   = endVal;

        if (modalUserBalance) {
            modalUserBalance.textContent = (userPoints % 1 === 0 ? userPoints : userPoints.toFixed(2)) + ' points';
        }

        // Check if values entered
        if (!startVal || !endVal) {
            setSubmitState(false, 'Please enter both start and end times.');
            return;
        }

        var startSec = timeToSeconds(startVal);
        var endSec   = timeToSeconds(endVal);

        // Basic sequence check
        if (startSec >= endSec) {
            showConflict('End time must be strictly after start time.');
            setSubmitState(false);
            return;
        }

        // Check past time if booking for today
        var datePicker = document.getElementById('bookingDate');
        var selectedDate = datePicker ? datePicker.value : '';
        var serverToday = overlay ? (overlay.dataset.serverToday || '') : '';
        var serverTime  = overlay ? (overlay.dataset.serverTime  || '') : '';

        var now = new Date();
        var y = now.getFullYear();
        var m = pad2(now.getMonth() + 1);
        var d = pad2(now.getDate());
        var clientToday = y + '-' + m + '-' + d;
        var isToday = (selectedDate && (selectedDate === serverToday || selectedDate === clientToday));

        if (isToday) {
            var clientNowSec = now.getHours() * 3600 + now.getMinutes() * 60 + now.getSeconds();
            var srvNowSec    = serverTime ? timeToSeconds(serverTime) : clientNowSec;
            var currentSec   = Math.max(clientNowSec, srvNowSec);

            if (endSec <= currentSec) {
                showConflict('This time range has already ended for today.');
                setSubmitState(false);
                return;
            }
        }

        // Check overlaps against existing bookings on this slot
        var conflictingRange = null;
        for (var i = 0; i < blockedRanges.length; i++) {
            var r = blockedRanges[i];
            var rStart = timeToSeconds(r.start);
            var rEnd   = timeToSeconds(r.end);
            if (startSec < rEnd && endSec > rStart) {
                conflictingRange = r;
                break;
            }
        }

        if (conflictingRange) {
            showConflict('Conflict! This slot is already booked from ' +
                         formatTime12(conflictingRange.start) + ' to ' + formatTime12(conflictingRange.end) + '.');
            setSubmitState(false);
            return;
        }

        // Valid selection! Clear conflict
        hideConflict();

        // Calculate duration & cost
        var durSec = endSec - startSec;
        var durHours = Math.max(1, Math.ceil(durSec / 3600));
        var cost = durHours * 10;
        var remaining = userPoints - cost;

        if (inputDuration) inputDuration.value = durHours;

        // Human-friendly duration string
        var durText = '';
        if (durSec < 60) {
            durText = durSec + 's';
        } else if (durSec < 3600) {
            var mins = Math.floor(durSec / 60);
            var secs = durSec % 60;
            durText = secs > 0 ? mins + 'm ' + secs + 's' : mins + 'm';
        } else {
            var hrs = Math.floor(durSec / 3600);
            var remMins = Math.floor((durSec % 3600) / 60);
            durText = remMins > 0 ? hrs + ' hr ' + remMins + ' min' : hrs + ' hr' + (hrs > 1 ? 's' : '');
        }

        if (modalDurationText) modalDurationText.textContent = durText;
        if (modalPointCost) modalPointCost.textContent = cost + ' points';
        if (timeBlockSummary) {
            timeBlockSummary.textContent = formatTime12(startVal) + ' – ' + formatTime12(endVal) + ' (' + durText + ' · ' + cost + ' pts)';
        }

        if (remaining < 0) {
            if (modalRemainingBalance) {
                modalRemainingBalance.textContent = 'Insufficient (' + cost + ' needed)';
                modalRemainingBalance.style.color = 'var(--clr-error)';
            }
            if (modalPointsWarning) {
                modalPointsWarning.style.display = 'flex';
                if (modalWarningCost) modalWarningCost.textContent = cost;
            }
            setSubmitState(false);
        } else {
            if (modalRemainingBalance) {
                modalRemainingBalance.textContent = (remaining % 1 === 0 ? remaining : remaining.toFixed(2)) + ' points';
                modalRemainingBalance.style.color = 'var(--clr-success)';
            }
            if (modalPointsWarning) modalPointsWarning.style.display = 'none';
            setSubmitState(true);
        }
    }

    function showConflict(msg) {
        if (modalConflictAlert && modalConflictMsg) {
            modalConflictMsg.textContent = msg;
            modalConflictAlert.style.display = 'flex';
        }
        if (timeBlockSummary) timeBlockSummary.textContent = '';
        if (modalDurationText) modalDurationText.textContent = '—';
        if (modalPointCost) modalPointCost.textContent = '—';
        if (modalRemainingBalance) {
            modalRemainingBalance.textContent = '— points';
            modalRemainingBalance.style.color = 'var(--clr-text-muted)';
        }
    }

    function hideConflict() {
        if (modalConflictAlert) {
            modalConflictAlert.style.display = 'none';
        }
    }

    function setSubmitState(enabled, summaryMsg) {
        if (modalSubmitBtn) {
            modalSubmitBtn.disabled = !enabled;
            modalSubmitBtn.style.opacity = enabled ? '1' : '0.5';
            modalSubmitBtn.style.cursor  = enabled ? 'pointer' : 'not-allowed';
        }
        if (summaryMsg && timeBlockSummary) {
            timeBlockSummary.textContent = summaryMsg;
        }
    }

    // Quick preset buttons for instant test setups
    document.querySelectorAll('.btn-preset').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var preset = btn.getAttribute('data-preset');
            var now = new Date();
            var curSec = now.getHours() * 3600 + now.getMinutes() * 60 + now.getSeconds();

            if (preset === 'now') {
                pickerStartTime.value = secondsToTime(curSec);
                var endSec = timeToSeconds(pickerEndTime.value);
                if (!endSec || endSec <= curSec) {
                    pickerEndTime.value = secondsToTime(curSec + 60);
                }
            } else {
                var startSec = timeToSeconds(pickerStartTime.value);
                if (!startSec) {
                    startSec = curSec;
                    pickerStartTime.value = secondsToTime(startSec);
                }
                var addSec = 60;
                if (preset === '30s') addSec = 30;
                else if (preset === '1m') addSec = 60;
                else if (preset === '5m') addSec = 300;
                else if (preset === '1h') addSec = 3600;

                pickerEndTime.value = secondsToTime(startSec + addSec);
            }
            validateAndUpdate();
        });
    });

    if (pickerStartTime) {
        pickerStartTime.addEventListener('input', validateAndUpdate);
        pickerStartTime.addEventListener('change', validateAndUpdate);
    }
    if (pickerEndTime) {
        pickerEndTime.addEventListener('input', validateAndUpdate);
        pickerEndTime.addEventListener('change', validateAndUpdate);
    }

    function openModal(card) {
        if (!card.dataset.slotId) return;  // safety guard for non-bookable cards

        if (inputSlotId)   inputSlotId.value         = card.dataset.slotId;
        if (modalSlotCode) modalSlotCode.textContent  = card.dataset.slotCode || '—';
        if (modalZone)     modalZone.textContent      = card.dataset.zone     || '—';

        var datePicker = document.getElementById('bookingDate');
        if (modalDate) modalDate.textContent = datePicker ? formatDate(datePicker.value) : '—';

        try {
            blockedRanges = JSON.parse(card.dataset.blocked || '[]');
        } catch (e) {
            blockedRanges = [];
        }

        renderExistingBookings();

        // Suggest default start and end times:
        // Set start time to current time, and end time to start time + 5 min (easy for testing)
        var now = new Date();
        var curSec = now.getHours() * 3600 + now.getMinutes() * 60 + now.getSeconds();
        var startSec = curSec;
        var endSec = Math.min(86399, startSec + 300); // +5 minutes

        pickerStartTime.value = secondsToTime(startSec);
        pickerEndTime.value   = secondsToTime(endSec);

        hideConflict();
        validateAndUpdate();

        overlay.classList.add('is-open');
        overlay.setAttribute('aria-hidden', 'false');
        if (closeBtn) closeBtn.focus();
    }

    function closeModal() {
        overlay.classList.remove('is-open');
        overlay.setAttribute('aria-hidden', 'true');
    }

    // Attach click handler to all available slot cards that carry a slot-id
    document.querySelectorAll('.slot-card--available[data-slot-id]').forEach(function(card) {
        card.addEventListener('click', function() { openModal(card); });
        card.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); openModal(card); }
        });
        card.setAttribute('tabindex', '0');
        card.setAttribute('role', 'button');
    });

    // When a user who already has an active booking tries to click a slot,
    // draw attention to the styled warning banner instead of failing silently or using browser alert()
    document.querySelectorAll('.slot-card[data-has-active-booking="true"]').forEach(function(card) {
        function notifyActiveBooking(e) {
            e.preventDefault();
            var banner = document.getElementById('activeBookingWarning');
            if (banner) {
                banner.scrollIntoView({ behavior: 'smooth', block: 'center' });
                banner.focus();
                banner.style.transition = 'box-shadow 200ms ease, transform 200ms ease';
                banner.style.boxShadow = '0 0 0 4px rgba(185, 28, 28, 0.4)';
                banner.style.transform = 'scale(1.01)';
                setTimeout(function() {
                    banner.style.boxShadow = '';
                    banner.style.transform = '';
                }, 1200);
            }
        }
        card.addEventListener('click', notifyActiveBooking);
        card.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') { notifyActiveBooking(e); }
        });
    });

    if (closeBtn) closeBtn.addEventListener('click', closeModal);

    overlay.addEventListener('click', function(e) {
        if (e.target === overlay) closeModal();
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && overlay.classList.contains('is-open')) closeModal();
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
        form.querySelectorAll('.form-input, .auth-input-pill').forEach(function(el) {
            el.classList.remove('form-input--error');
        });

        const isSignup     = !!form.querySelector('[name="confirm_password"]');
        const emailInput   = form.querySelector('[name="email"]');
        const passInput    = form.querySelector('[name="password"]');
        const confirmInput = form.querySelector('[name="confirm_password"]');
        const nameInput    = form.querySelector('[name="full_name"]');

        if (isSignup) {
            if (nameInput && nameInput.value.trim().length < 2) {
                showFieldError(nameInput, 'Please enter your full name (at least 2 characters).');
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
        } else {
            // Login: check non-empty
            if (emailInput && emailInput.value.trim() === '') {
                showFieldError(emailInput, 'Please enter your university email.');
                valid = false;
            }
            if (passInput && passInput.value === '') {
                showFieldError(passInput, 'Please enter your password.');
                valid = false;
            }
        }

        if (!valid) e.preventDefault();
    });
}

function showFieldError(input, message) {
    input.classList.add('form-input--error');
    // Find .form-error[data-client] inside the parent form-group
    const group = input.closest('.form-group') || input.parentElement;
    const errorEl = group ? group.querySelector('.form-error[data-client]') : null;
    if (errorEl) {
        errorEl.textContent = message;
        errorEl.style.display = 'block';
    }
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

    // Clean, free OpenStreetMap tiles (zero API key, zero watermark)
    var osmTiles = L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19
    }).addTo(map);

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
            badgeBg: '#ECFEFF',
            badgeColor: '#0891B2',
            color: '#06B6D4',
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
            badgeBg: '#EEF2FF',
            badgeColor: '#4F46E5',
            color: '#6366F1',
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
                <a href="${window.location.pathname.includes('/public/') ? 'book-slot.php' : 'public/book-slot.php'}" class="campus-map-popup-btn">
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
   Cancel Booking Modal (dashboard page)
   ========================================================================== */

function initCancelBookingModal() {
    var overlay = document.getElementById('cancelBookingModal');
    if (!overlay) return;

    var closeBtn        = document.getElementById('cancelModalClose');
    var dismissBtn      = document.getElementById('cancelModalDismissBtn');
    var bookingIdInput  = document.getElementById('cancelModalBookingId');
    var codeDisplay     = document.getElementById('cancelModalBookingCode');
    var slotZoneDisplay = document.getElementById('cancelModalSlotZone');
    var dateTimeDisplay = document.getElementById('cancelModalDateTime');
    var pointsDisplay   = document.getElementById('cancelModalPoints');
    var pointsNotice    = document.getElementById('cancelModalPointsNotice');

    function openModal(btn) {
        var bookingId = btn.dataset.bookingId || '';
        var code      = btn.dataset.bookingCode || ('#CP-' + bookingId);
        var slot      = btn.dataset.slotCode || '—';
        var zone      = btn.dataset.zone || '';
        var date      = btn.dataset.date || '—';
        var time      = btn.dataset.time || '';
        var points    = btn.dataset.points || '10';

        if (bookingIdInput) bookingIdInput.value = bookingId;
        if (codeDisplay)    codeDisplay.textContent = code;
        if (slotZoneDisplay) {
            slotZoneDisplay.textContent = slot + (zone ? ' (' + zone + ')' : '');
        }
        if (dateTimeDisplay) {
            dateTimeDisplay.textContent = date + (time ? ' · ' + time : '');
        }
        if (pointsDisplay) {
            pointsDisplay.textContent = points + ' pts';
        }
        if (pointsNotice) {
            pointsNotice.textContent = points + ' points';
        }

        overlay.classList.add('is-open');
        overlay.setAttribute('aria-hidden', 'false');
        if (dismissBtn) dismissBtn.focus();
    }

    function closeModal() {
        overlay.classList.remove('is-open');
        overlay.setAttribute('aria-hidden', 'true');
    }

    document.querySelectorAll('.btn-cancel-action').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            openModal(btn);
        });
    });

    if (closeBtn) {
        closeBtn.addEventListener('click', closeModal);
    }
    if (dismissBtn) {
        dismissBtn.addEventListener('click', closeModal);
    }

    overlay.addEventListener('click', function(e) {
        if (e.target === overlay) {
            closeModal();
        }
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && overlay.classList.contains('is-open')) {
            closeModal();
        }
    });
}

/* ==========================================================================
   Boot — call the right functions based on the page
   ========================================================================== */

document.addEventListener('DOMContentLoaded', function() {
    initThemeToggle();
    initMobileMenu();

    const page = document.body.dataset.page || '';

    if (page === 'dashboard' || document.getElementById('cancelBookingModal')) {
        initCancelBookingModal();
    }

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

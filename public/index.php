<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

// Redirect authenticated users straight to the dashboard
$user = current_user();
if (!empty($user['id'])) {
    header('Location: /parking-system/public/dashboard.php');
    exit;
}

$page_title = 'Reserve Campus Parking';
$body_page  = 'landing';

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Animated mesh gradient (position:fixed in CSS — sits behind the hero viewport) -->
<div class="hero-bg-mesh" aria-hidden="true">
    <shader-art autoplay class="w-full h-full">
        <script data-size="2" name="position" type="buffer">[-1,1,-1,-1,1,-1,1,-1,1,1,-1,1]</script>
        <script data-size="2" name="uv"       type="buffer">[ 0,1, 0, 0,1, 0,1, 0,1,1, 0,1]</script>
        <script type="vert">
            precision highp float;
            attribute vec2 position; attribute vec2 uv; varying vec2 v_texCoord;
            void main(){ gl_Position=vec4(position,0.,1.); v_texCoord=uv; }
        </script>
        <script type="frag">
            precision highp float;
            varying vec2 v_texCoord; uniform float u_time;
            void main(){
                vec2 uv=v_texCoord;
                vec3 base=vec3(0.98,0.99,1.0);
                vec3 violet=vec3(0.96,0.95,1.0);
                vec3 teal=vec3(0.94,0.99,0.96);
                float t=u_time*0.3;
                vec2 p1=vec2(0.3+0.2*cos(t),0.3+0.2*sin(t*0.8));
                vec2 p2=vec2(0.7+0.2*sin(t*0.9),0.7+0.2*cos(t*1.1));
                float d1=smoothstep(0.8,0.,distance(uv,p1));
                float d2=smoothstep(0.8,0.,distance(uv,p2));
                vec3 c=mix(base,violet,d1*0.5);
                c=mix(c,teal,d2*0.4);
                gl_FragColor=vec4(c,1.);
            }
        </script>
    </shader-art>
    <script src="https://unpkg.com/shader-art" type="module"></script>
</div>

<!-- Sections 1–4 inside max-w-7xl — pt-24 clears the fixed 64px navbar -->
<div class="pt-24 pb-16 px-margin-mobile md:px-margin-desktop w-full max-w-7xl mx-auto flex flex-col gap-xl">

    <!-- ── 1. Hero ────────────────────────────────────────────── -->
    <section class="flex flex-col md:flex-row items-center justify-between gap-xl"
             aria-labelledby="heroTitle">

        <div class="flex-1 flex flex-col gap-lg items-start">

            <h1 class="hero-display" id="heroTitle">
                Reserve Campus Parking
                <span>In Seconds</span>
            </h1>

            <p class="hero-body max-w-lg">
                Skip the circling. Guarantee your spot before you arrive on campus
                with real-time availability and seamless digital check-in.
            </p>

            <div class="flex flex-wrap items-center gap-md mt-sm">
                <a href="/parking-system/public/signup.php"
                   class="btn btn-primary btn-lg flex items-center gap-sm">
                    Book a Slot Now
                    <span class="material-symbols-outlined" style="font-size:18px;">arrow_forward</span>
                </a>
                <a href="/parking-system/public/login.php"
                   class="btn btn-secondary btn-lg flex items-center gap-sm">
                    <span class="material-symbols-outlined" style="font-size:18px;">login</span>
                    Log In
                </a>
            </div>

            <!-- Trust metrics -->
            <div class="flex items-center gap-xl mt-md pt-md w-full max-w-md"
                 style="border-top: 1px solid rgba(221,192,186,.5);"
                 role="list" aria-label="Platform statistics">
                <div role="listitem" class="flex flex-col">
                    <span class="hero-stat-value">5,000+</span>
                    <span class="hero-stat-label">Students</span>
                </div>
                <div aria-hidden="true" style="width:1px;height:48px;background:rgba(221,192,186,.5);"></div>
                <div role="listitem" class="flex flex-col">
                    <span class="hero-stat-value">98%</span>
                    <span class="hero-stat-label">On-Time Rate</span>
                </div>
                <div aria-hidden="true" style="width:1px;height:48px;background:rgba(221,192,186,.5);"></div>
                <div role="listitem" class="flex flex-col">
                    <span class="hero-stat-value">3 Zones</span>
                    <span class="hero-stat-label">Coverage</span>
                </div>
            </div>

        </div>

        <!-- Hero image -->
        <div class="flex-1 w-full hero-img-wrap">
            <img src="https://lh3.googleusercontent.com/aida-public/AB6AXuBVcpT5Q5BSSDNcqeQt1bvesnbnz6CiboPjSSL1h9QtCoEQbyBD9LdeSsyjPUAscmucS9U9I9gw9z5P4FiFYqsY8PWW_ArZduN7WldHx7K8zn5VoozM915gbw0vn-HOh__MHfW-pcHyN33r_nMdHBS5PVzoeg5FfLFJf2CD7tqn12-arqh9sPZf1aYyDw7OOjCs1gtV-gXBZwJSi5GTR9mib9acgA6n-9psv4_RjapoNPyK9X9ijM8"
                 alt="3D isometric illustration of a smart campus parking lot">
            <div class="hero-img-overlay"></div>
        </div>

    </section>

    <!-- ── 2. How It Works ────────────────────────────────────── -->
    <section class="flex flex-col gap-lg mt-xl" aria-labelledby="howWorksTitle">

        <div class="text-center max-w-2xl mx-auto mb-md">
            <h2 class="section-title mb-sm" id="howWorksTitle">How It Works</h2>
            <p class="hero-body" style="font-size:16px;">
                Three simple steps to secure your parking spot and get to class on time.
            </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-lg" role="list">

            <article class="step-card step-card-violet" role="listitem">
                <div class="step-icon" style="color:var(--clr-secondary);">
                    <span class="material-symbols-outlined" style="font-size:30px;">search</span>
                </div>
                <h3 class="step-title">1. Find a Spot</h3>
                <p class="step-desc">
                    Check real-time availability across all campus lots
                    and choose your preferred zone.
                </p>
            </article>

            <article class="step-card step-card-emerald" role="listitem">
                <div class="step-icon" style="color:var(--clr-success);">
                    <span class="material-symbols-outlined" style="font-size:30px;">event_available</span>
                </div>
                <h3 class="step-title">2. Reserve Instantly</h3>
                <p class="step-desc">
                    Book your space for an hour, a day, or an entire semester
                    with just a few clicks.
                </p>
            </article>

            <article class="step-card step-card-terra" role="listitem">
                <div class="step-icon" style="color:var(--clr-primary);">
                    <span class="material-symbols-outlined" style="font-size:30px;">directions_car</span>
                </div>
                <h3 class="step-title">3. Park &amp; Go</h3>
                <p class="step-desc">
                    Drive up, scan your digital permit via mobile check-in,
                    and head straight to class.
                </p>
            </article>

        </div>

    </section>

    <!-- ── 3. Interactive Campus Map ─────────────────── -->
    <section class="section-dark mt-xl flex flex-col gap-lg" aria-labelledby="mapTitle">

        <div class="flex flex-col md:flex-row md:items-end justify-between gap-md">
            <div>
                <p class="section-eyebrow" style="color:var(--clr-secondary);">Live Availability</p>
                <h2 id="mapTitle" style="color:#fff; font-size:32px; font-weight:700; margin-top:4px;">
                    Interactive Campus Map
                </h2>
                <p style="color:#d1d5db; font-size:15px; margin-top:4px;">
                    Explore designated campus zones, check real-time capacity, and reserve parking in seconds.
                </p>
            </div>

            <!-- Zone Filter Navigation Pills -->
            <div class="map-filter-bar" role="tablist" aria-label="Parking Zone Filters">
                <button class="map-zone-btn map-zone-btn--active" data-zone="all" role="tab" aria-selected="true">
                    <span class="map-zone-dot" style="background:#fff;"></span> All Zones
                </button>
                <button class="map-zone-btn" data-zone="A" role="tab" aria-selected="false">
                    <span class="map-zone-dot" style="background:#E06C53;"></span> Zone A (Core)
                </button>
                <button class="map-zone-btn" data-zone="B" role="tab" aria-selected="false">
                    <span class="map-zone-dot" style="background:#8B5CF6;"></span> Zone B (Outer)
                </button>
                <button class="map-zone-btn" data-zone="C" role="tab" aria-selected="false">
                    <span class="map-zone-dot" style="background:#10B981;"></span> Zone C (Stadium)
                </button>
                <button class="map-zone-btn" data-zone="D" role="tab" aria-selected="false">
                    <span class="map-zone-dot" style="background:#F59E0B;"></span> Zone D (Medical)
                </button>
            </div>
        </div>

        <!-- Interactive Map Container -->
        <div class="map-wrapper">
            <!-- Leaflet Map Mount Point -->
            <div id="campusMap" role="region" aria-label="Interactive campus parking map"></div>

            <!-- Live Telemetry HUD Card -->
            <div class="map-hud-card" aria-live="polite">
                <div class="flex items-center justify-between gap-3 mb-1">
                    <span class="map-hud-live-indicator"><span class="map-hud-pulse"></span>LIVE OCCUPANCY</span>
                    <span style="font-size:11px; opacity:.75; font-weight:600;">Campus ParkNet</span>
                </div>
                <div class="flex items-baseline gap-2">
                    <span style="font-size:24px; font-weight:700; color:#fff;" id="mapHudAvailable">179</span>
                    <span style="font-size:12px; color:rgba(255,255,255,.8);">/ 425 Spots Free</span>
                </div>
                <div class="w-full bg-white/10 rounded-full h-1.5 mt-2 overflow-hidden">
                    <div class="bg-gradient-to-r from-emerald-400 to-teal-300 h-full rounded-full" style="width: 42%;"></div>
                </div>
                <div class="flex items-center justify-between text-[11px] text-white/80 mt-2 pt-2 border-t border-white/10">
                    <span>Avg. Rate: <strong style="color:#fff;">$2.50/hr</strong></span>
                    <span>Shuttle: <strong style="color:#10B981;">Active (5m)</strong></span>
                </div>
            </div>

            <!-- Custom Map Control Overlay -->
            <div class="map-controls" aria-label="Map view controls">
                <button id="mapZoomIn" class="map-ctrl-btn" aria-label="Zoom in" title="Zoom in">
                    <span class="material-symbols-outlined">add</span>
                </button>
                <button id="mapZoomOut" class="map-ctrl-btn" aria-label="Zoom out" title="Zoom out">
                    <span class="material-symbols-outlined">remove</span>
                </button>
                <button id="mapRecenter" class="map-ctrl-btn" style="margin-top:6px;" aria-label="Recenter campus map" title="Recenter Campus">
                    <span class="material-symbols-outlined">near_me</span>
                </button>
            </div>
        </div>

    </section>

    <!-- ── 4. Feature Bento Grid ─────────────────────────────── -->
    <section class="flex flex-col gap-lg mt-xl" aria-labelledby="featuresTitle">

        <h2 class="section-title mb-sm" id="featuresTitle">Why use CampusPark?</h2>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-gutter">

            <div class="feature-card" style="border-top-color:var(--clr-secondary);">
                <div class="feature-card-icon" style="color:var(--clr-secondary);">
                    <span class="material-symbols-outlined">satellite_alt</span>
                </div>
                <h3 class="feature-card-title">Real-time Availability</h3>
                <p class="feature-card-desc">
                    Live updates on spot occupancy across all campus lots,
                    preventing wasted time.
                </p>
            </div>

            <div class="feature-card" style="border-top-color:var(--clr-success);">
                <div class="feature-card-icon" style="color:var(--clr-success);">
                    <span class="material-symbols-outlined">smartphone</span>
                </div>
                <h3 class="feature-card-title">Mobile Check-In</h3>
                <p class="feature-card-desc">
                    Contactless entry using your phone. Just drive up
                    and scan your digital permit.
                </p>
            </div>

            <div class="feature-card" style="border-top-color:var(--clr-amber);">
                <div class="feature-card-icon" style="color:var(--clr-amber);">
                    <span class="material-symbols-outlined">notifications_active</span>
                </div>
                <h3 class="feature-card-title">Smart Notifications</h3>
                <p class="feature-card-desc">
                    Get alerts when your reservation is expiring or if new spots
                    open in preferred zones.
                </p>
            </div>

            <div class="feature-card" style="border-top-color:var(--clr-primary);">
                <div class="feature-card-icon" style="color:var(--clr-primary);">
                    <span class="material-symbols-outlined">gavel</span>
                </div>
                <h3 class="feature-card-title">Penalty Prevention</h3>
                <p class="feature-card-desc">
                    Automated reminders and grace periods help you avoid
                    costly campus parking tickets.
                </p>
            </div>

        </div>

    </section>

</div><!-- /max-w-7xl -->

<!-- ── 5. Testimonials (full-width) ──────────────────────────── -->
<section class="testimonials-bg w-full py-xl" aria-labelledby="testimonialsTitle">
    <div class="max-w-7xl mx-auto px-margin-mobile md:px-margin-desktop flex flex-col gap-lg">

        <h2 class="section-title text-center mb-sm" id="testimonialsTitle">What Students Say</h2>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-lg max-w-5xl mx-auto w-full">

            <div class="testimonial-card">
                <p class="testimonial-quote">
                    "I used to spend 30 minutes looking for parking before class.
                    Now I book my spot from my dorm and drive straight there.
                    Absolute game changer."
                </p>
                <div class="flex items-center gap-md mt-auto pt-4">
                    <div class="testimonial-avatar" style="border-color:var(--clr-secondary);">AJ</div>
                    <div>
                        <p class="testimonial-name">Alex Johnson</p>
                        <p class="testimonial-role">Senior, Engineering</p>
                    </div>
                </div>
            </div>

            <div class="testimonial-card">
                <p class="testimonial-quote">
                    "The penalty prevention notifications have saved me so much money
                    in parking tickets. Highly recommend to every student on campus."
                </p>
                <div class="flex items-center gap-md mt-auto pt-4">
                    <div class="testimonial-avatar" style="border-color:var(--clr-success);">SL</div>
                    <div>
                        <p class="testimonial-name">Sarah Lee</p>
                        <p class="testimonial-role">Junior, Business</p>
                    </div>
                </div>
            </div>

        </div>

    </div>
</section>

<!-- ── 6. Rate Calculator ─────────────────────────────────────── -->
<section class="w-full flex justify-center py-xl px-margin-mobile md:px-margin-desktop"
         aria-labelledby="rateCalcTitle">

    <div class="section-dark-gradient w-full max-w-3xl">

        <h2 id="rateCalcTitle" style="color:#fff; font-size:30px; font-weight:700; margin-bottom:4px;">
            Quick Rate Calculator
        </h2>
        <p style="color:#d1d5db; font-size:15px; margin-bottom:var(--sp-lg);">
            Estimate your parking cost based on zone and duration.
        </p>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-lg mb-md">
            <div class="form-group" style="margin-bottom:0;">
                <label class="form-label" style="color:#d1d5db;" for="calcZone">Select Zone</label>
                <select id="calcZone" class="form-input form-input--dark">
                    <option value="4">Premium (Core Campus) — $4/hr</option>
                    <option value="2">Standard (Outer Lots) — $2/hr</option>
                    <option value="1">Economy (Stadium) — $1/hr</option>
                </select>
            </div>
            <div class="form-group" style="margin-bottom:0;">
                <label class="form-label" style="color:#d1d5db;" for="calcDuration">Duration</label>
                <select id="calcDuration" class="form-input form-input--dark">
                    <option value="2">2 Hours</option>
                    <option value="4">4 Hours</option>
                    <option value="8">Full Day (8 Hours)</option>
                    <option value="semester">Semester Pass ($150 Flat)</option>
                </select>
            </div>
        </div>

        <div class="rate-result mt-md">
            <span class="rate-result-label">Estimated Cost:</span>
            <span class="rate-result-value" id="rateOutput">$8.00</span>
        </div>

    </div>

</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>


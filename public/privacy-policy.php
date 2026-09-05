<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

$page_title = 'Privacy Policy';
$body_page  = 'privacy-policy';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-3xl mx-auto px-6 py-16">

    <!-- Page Hero -->
    <div class="mb-10">
        <div class="flex items-center gap-2 mb-4">
            <span class="material-symbols-outlined" style="font-size:20px; color:var(--clr-primary, #0891B2);">shield</span>
            <span style="font-size:13px; font-weight:600; color:var(--clr-primary, #0891B2); letter-spacing:0.05em; text-transform:uppercase;">Legal</span>
        </div>
        <h1 style="font-size:2.25rem; font-weight:700; line-height:1.2; margin-bottom:12px;">Privacy Policy</h1>
        <p style="color:var(--clr-text-muted, #64748b); font-size:15px;">
            Last updated: <strong><?= date('F j, Y') ?></strong>
        </p>
        <p style="color:var(--clr-text-muted, #64748b); font-size:15px; margin-top:8px; line-height:1.7;">
            CampusPark ("we", "our", or "us") is committed to protecting your privacy. This Privacy Policy explains
            how we collect, use, disclose, and safeguard your information when you use our campus parking reservation
            platform. Please read this policy carefully.
        </p>
    </div>

    <div style="display:flex; flex-direction:column; gap:2.5rem;">

        <!-- Section 1 -->
        <section>
            <h2 style="font-size:1.25rem; font-weight:700; margin-bottom:10px; display:flex; align-items:center; gap:8px;">
                <span class="material-symbols-outlined" style="font-size:20px; color:var(--clr-primary, #0891B2);">database</span>
                1. Information We Collect
            </h2>
            <p style="color:var(--clr-text-muted, #475569); line-height:1.75; margin-bottom:10px;">
                We collect information you provide directly when you register and use CampusPark:
            </p>
            <ul style="list-style:none; padding:0; display:flex; flex-direction:column; gap:8px;">
                <?php
                $items = [
                    ['person', '<strong>Account Information:</strong> Full name, university email address, and hashed password created at signup.'],
                    ['directions_car', '<strong>Vehicle Information:</strong> License plate number(s) and vehicle type associated with your account.'],
                    ['event', '<strong>Booking Data:</strong> Parking zone selections, reservation dates and times, and booking history.'],
                    ['wifi', '<strong>Usage Data:</strong> Pages visited, features used, and browser/device type for performance monitoring.'],
                    ['schedule', '<strong>Log Data:</strong> IP address, timestamps, and session identifiers for security and auditing purposes.'],
                ];
                foreach ($items as [$icon, $text]): ?>
                <li style="display:flex; align-items:flex-start; gap:10px; padding:12px 16px; background:var(--clr-surface-container-low, #f1f5f9); border-radius:10px;">
                    <span class="material-symbols-outlined" style="font-size:18px; color:var(--clr-primary, #0891B2); margin-top:2px; flex-shrink:0;"><?= $icon ?></span>
                    <span style="font-size:14px; color:var(--clr-text, #0f172a); line-height:1.6;"><?= $text ?></span>
                </li>
                <?php endforeach; ?>
            </ul>
        </section>

        <hr style="border:none; border-top:1px solid var(--clr-outline-variant, #e2e8f0);">

        <!-- Section 2 -->
        <section>
            <h2 style="font-size:1.25rem; font-weight:700; margin-bottom:10px; display:flex; align-items:center; gap:8px;">
                <span class="material-symbols-outlined" style="font-size:20px; color:var(--clr-primary, #0891B2);">manage_search</span>
                2. How We Use Your Information
            </h2>
            <p style="color:var(--clr-text-muted, #475569); line-height:1.75; margin-bottom:10px;">
                We use the information we collect to:
            </p>
            <ul style="padding-left:1.5rem; display:flex; flex-direction:column; gap:6px; color:var(--clr-text-muted, #475569); font-size:14px; line-height:1.75;">
                <li>Create and manage your CampusPark account and authenticate your sessions.</li>
                <li>Process and confirm parking slot reservations across campus zones.</li>
                <li>Display real-time parking availability and live occupancy data.</li>
                <li>Send booking confirmations and important account notifications.</li>
                <li>Prevent fraud, enforce our Terms of Service, and maintain system security.</li>
                <li>Analyse anonymised, aggregated usage to improve platform features.</li>
                <li>Comply with applicable university regulations and legal obligations.</li>
            </ul>
        </section>

        <hr style="border:none; border-top:1px solid var(--clr-outline-variant, #e2e8f0);">

        <!-- Section 3 -->
        <section>
            <h2 style="font-size:1.25rem; font-weight:700; margin-bottom:10px; display:flex; align-items:center; gap:8px;">
                <span class="material-symbols-outlined" style="font-size:20px; color:var(--clr-primary, #0891B2);">share</span>
                3. Sharing Your Information
            </h2>
            <p style="color:var(--clr-text-muted, #475569); line-height:1.75; margin-bottom:10px;">
                We do <strong>not</strong> sell, trade, or rent your personal information to third parties. We may share
                data only in the following limited circumstances:
            </p>
            <ul style="padding-left:1.5rem; display:flex; flex-direction:column; gap:6px; color:var(--clr-text-muted, #475569); font-size:14px; line-height:1.75;">
                <li><strong>University Administration:</strong> Aggregated, anonymised parking usage reports may be shared with campus facilities management.</li>
                <li><strong>Service Providers:</strong> Trusted technical partners who assist with hosting and infrastructure, bound by confidentiality agreements.</li>
                <li><strong>Legal Requirements:</strong> When required by law, court order, or university policy to protect safety or prevent harm.</li>
                <li><strong>With Your Consent:</strong> Any other sharing will only occur with your explicit permission.</li>
            </ul>
        </section>

        <hr style="border:none; border-top:1px solid var(--clr-outline-variant, #e2e8f0);">

        <!-- Section 4 -->
        <section>
            <h2 style="font-size:1.25rem; font-weight:700; margin-bottom:10px; display:flex; align-items:center; gap:8px;">
                <span class="material-symbols-outlined" style="font-size:20px; color:var(--clr-primary, #0891B2);">cookie</span>
                4. Cookies &amp; Sessions
            </h2>
            <p style="color:var(--clr-text-muted, #475569); line-height:1.75;">
                CampusPark uses PHP sessions (stored server-side) and a minimal <code style="background:var(--clr-surface-container, #e2e8f0); padding:1px 5px; border-radius:4px; font-size:13px;">localStorage</code>
                entry to remember your theme preference (light/dark). We do not use third-party advertising cookies.
                Disabling cookies will prevent login sessions from functioning correctly.
            </p>
        </section>

        <hr style="border:none; border-top:1px solid var(--clr-outline-variant, #e2e8f0);">

        <!-- Section 5 -->
        <section>
            <h2 style="font-size:1.25rem; font-weight:700; margin-bottom:10px; display:flex; align-items:center; gap:8px;">
                <span class="material-symbols-outlined" style="font-size:20px; color:var(--clr-primary, #0891B2);">lock</span>
                5. Data Security
            </h2>
            <p style="color:var(--clr-text-muted, #475569); line-height:1.75;">
                We implement industry-standard safeguards including bcrypt password hashing, HTTPS-only transmission,
                parameterised SQL queries to prevent injection attacks, and server-side session validation.
                While no system is perfectly secure, we continuously review our practices and address vulnerabilities promptly.
            </p>
        </section>

        <hr style="border:none; border-top:1px solid var(--clr-outline-variant, #e2e8f0);">

        <!-- Section 6 -->
        <section>
            <h2 style="font-size:1.25rem; font-weight:700; margin-bottom:10px; display:flex; align-items:center; gap:8px;">
                <span class="material-symbols-outlined" style="font-size:20px; color:var(--clr-primary, #0891B2);">verified_user</span>
                6. Your Rights
            </h2>
            <p style="color:var(--clr-text-muted, #475569); line-height:1.75; margin-bottom:10px;">
                As a CampusPark user you have the right to:
            </p>
            <ul style="padding-left:1.5rem; display:flex; flex-direction:column; gap:6px; color:var(--clr-text-muted, #475569); font-size:14px; line-height:1.75;">
                <li><strong>Access:</strong> Request a copy of the personal data we hold about you.</li>
                <li><strong>Correction:</strong> Ask us to update inaccurate or incomplete information.</li>
                <li><strong>Deletion:</strong> Request deletion of your account and associated personal data.</li>
                <li><strong>Portability:</strong> Receive your data in a structured, machine-readable format.</li>
                <li><strong>Objection:</strong> Object to specific processing activities where applicable.</li>
            </ul>
            <p style="color:var(--clr-text-muted, #475569); line-height:1.75; margin-top:10px;">
                To exercise any of these rights, please contact your campus IT or parking administration office.
            </p>
        </section>

        <hr style="border:none; border-top:1px solid var(--clr-outline-variant, #e2e8f0);">

        <!-- Section 7 -->
        <section>
            <h2 style="font-size:1.25rem; font-weight:700; margin-bottom:10px; display:flex; align-items:center; gap:8px;">
                <span class="material-symbols-outlined" style="font-size:20px; color:var(--clr-primary, #0891B2);">schedule</span>
                7. Data Retention
            </h2>
            <p style="color:var(--clr-text-muted, #475569); line-height:1.75;">
                We retain your account information for as long as your account is active. Booking records are kept
                for a minimum of one academic year for audit and dispute-resolution purposes. Upon account deletion,
                personal identifiers are removed within 30 days, though anonymised aggregated data may be retained
                indefinitely for analytical purposes.
            </p>
        </section>

        <hr style="border:none; border-top:1px solid var(--clr-outline-variant, #e2e8f0);">

        <!-- Section 8 -->
        <section>
            <h2 style="font-size:1.25rem; font-weight:700; margin-bottom:10px; display:flex; align-items:center; gap:8px;">
                <span class="material-symbols-outlined" style="font-size:20px; color:var(--clr-primary, #0891B2);">update</span>
                8. Changes to This Policy
            </h2>
            <p style="color:var(--clr-text-muted, #475569); line-height:1.75;">
                We may update this Privacy Policy from time to time. When we do, we will revise the "Last updated"
                date at the top of this page and, where appropriate, notify registered users via their account dashboard.
                Continued use of CampusPark after changes constitutes acceptance of the revised policy.
            </p>
        </section>

        <hr style="border:none; border-top:1px solid var(--clr-outline-variant, #e2e8f0);">

        <!-- Section 9 - Contact -->
        <section style="padding:24px; background:var(--clr-surface-container-low, #f1f5f9); border-radius:16px;">
            <h2 style="font-size:1.25rem; font-weight:700; margin-bottom:8px; display:flex; align-items:center; gap:8px;">
                <span class="material-symbols-outlined" style="font-size:20px; color:var(--clr-primary, #0891B2);">contact_support</span>
                9. Contact Us
            </h2>
            <p style="color:var(--clr-text-muted, #475569); line-height:1.75; font-size:14px;">
                If you have questions or concerns about this Privacy Policy or our data practices, please contact:
            </p>
            <address style="font-style:normal; margin-top:10px; font-size:14px; color:var(--clr-text, #0f172a); line-height:1.8;">
                <strong>CampusPark — Privacy Office</strong><br>
                Campus Facilities &amp; Parking Management<br>
                University Administration Building<br>
                Email: <a href="mailto:privacy@campuspark.edu" style="color:var(--clr-primary, #0891B2);">privacy@campuspark.edu</a>
            </address>
        </section>

    </div><!-- /.content sections -->

    <!-- Back link -->
    <div class="mt-10">
        <a href="/parking-system/public/index.php"
           style="display:inline-flex; align-items:center; gap:6px; font-size:14px; font-weight:600; color:var(--clr-primary, #0891B2); text-decoration:none;">
            <span class="material-symbols-outlined" style="font-size:18px;">arrow_back</span>
            Back to Home
        </a>
    </div>

</div><!-- /.max-w-3xl -->

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

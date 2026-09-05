<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

$page_title = 'Terms of Service';
$body_page  = 'terms-of-service';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-3xl mx-auto px-6 py-16">

    <!-- Page Hero -->
    <div class="mb-10">
        <div class="flex items-center gap-2 mb-4">
            <span class="material-symbols-outlined" style="font-size:20px; color:var(--clr-primary, #0891B2);">gavel</span>
            <span style="font-size:13px; font-weight:600; color:var(--clr-primary, #0891B2); letter-spacing:0.05em; text-transform:uppercase;">Legal</span>
        </div>
        <h1 style="font-size:2.25rem; font-weight:700; line-height:1.2; margin-bottom:12px;">Terms of Service</h1>
        <p style="color:var(--clr-text-muted, #64748b); font-size:15px;">
            Last updated: <strong><?= date('F j, Y') ?></strong>
        </p>
        <p style="color:var(--clr-text-muted, #64748b); font-size:15px; margin-top:8px; line-height:1.7;">
            Please read these Terms of Service ("Terms") carefully before using the CampusPark platform.
            By creating an account or using any part of the service, you agree to be bound by these Terms.
            If you do not agree, you may not use CampusPark.
        </p>
    </div>

    <!-- Quick-summary banner -->
    <div style="background:linear-gradient(135deg, #0891B2 0%, #0e7490 100%); color:#fff; border-radius:16px; padding:20px 24px; margin-bottom:2.5rem; display:flex; align-items:flex-start; gap:14px;">
        <span class="material-symbols-outlined" style="font-size:24px; flex-shrink:0; margin-top:2px;">info</span>
        <div>
            <p style="font-weight:700; font-size:15px; margin-bottom:4px;">Plain-language summary</p>
            <p style="font-size:13px; opacity:0.9; line-height:1.65;">
                Use CampusPark only for legitimate campus parking purposes, follow campus rules, keep your credentials
                secure, and respect other users. We may suspend accounts that violate these Terms.
            </p>
        </div>
    </div>

    <div style="display:flex; flex-direction:column; gap:2.5rem;">

        <!-- Section 1 -->
        <section>
            <h2 style="font-size:1.25rem; font-weight:700; margin-bottom:10px; display:flex; align-items:center; gap:8px;">
                <span class="material-symbols-outlined" style="font-size:20px; color:var(--clr-primary, #0891B2);">handshake</span>
                1. Acceptance of Terms
            </h2>
            <p style="color:var(--clr-text-muted, #475569); line-height:1.75;">
                By registering for or using CampusPark you confirm that you are at least 18 years old (or the age
                of majority in your jurisdiction), that you are a currently enrolled student, faculty member, or
                authorised staff of the affiliated university, and that you have the legal capacity to enter into
                these Terms.
            </p>
        </section>

        <hr style="border:none; border-top:1px solid var(--clr-outline-variant, #e2e8f0);">

        <!-- Section 2 -->
        <section>
            <h2 style="font-size:1.25rem; font-weight:700; margin-bottom:10px; display:flex; align-items:center; gap:8px;">
                <span class="material-symbols-outlined" style="font-size:20px; color:var(--clr-primary, #0891B2);">description</span>
                2. The Service
            </h2>
            <p style="color:var(--clr-text-muted, #475569); line-height:1.75; margin-bottom:10px;">
                CampusPark provides an online platform that allows authorised university members to:
            </p>
            <ul style="padding-left:1.5rem; display:flex; flex-direction:column; gap:6px; color:var(--clr-text-muted, #475569); font-size:14px; line-height:1.75;">
                <li>Browse real-time parking availability across campus zones.</li>
                <li>Reserve specific parking slots in advance or for immediate use.</li>
                <li>Manage, modify, or cancel existing reservations via the dashboard.</li>
                <li>View occupancy history and receive parking-related notifications.</li>
            </ul>
            <p style="color:var(--clr-text-muted, #475569); line-height:1.75; margin-top:10px;">
                We reserve the right to modify, suspend, or discontinue any part of the service at any time, with or
                without notice, without liability to you.
            </p>
        </section>

        <hr style="border:none; border-top:1px solid var(--clr-outline-variant, #e2e8f0);">

        <!-- Section 3 -->
        <section>
            <h2 style="font-size:1.25rem; font-weight:700; margin-bottom:10px; display:flex; align-items:center; gap:8px;">
                <span class="material-symbols-outlined" style="font-size:20px; color:var(--clr-primary, #0891B2);">badge</span>
                3. Account Registration &amp; Security
            </h2>
            <p style="color:var(--clr-text-muted, #475569); line-height:1.75; margin-bottom:10px;">
                When creating an account you agree to:
            </p>
            <ul style="padding-left:1.5rem; display:flex; flex-direction:column; gap:6px; color:var(--clr-text-muted, #475569); font-size:14px; line-height:1.75;">
                <li>Provide accurate, complete, and current registration information.</li>
                <li>Use only your official university email address for registration.</li>
                <li>Maintain the confidentiality of your password and not share account access with others.</li>
                <li>Notify us immediately of any unauthorised use of your account.</li>
                <li>Accept responsibility for all activities that occur under your account.</li>
            </ul>
            <p style="color:var(--clr-text-muted, #475569); line-height:1.75; margin-top:10px;">
                Accounts are personal and non-transferable. Creating multiple accounts to circumvent restrictions
                is strictly prohibited.
            </p>
        </section>

        <hr style="border:none; border-top:1px solid var(--clr-outline-variant, #e2e8f0);">

        <!-- Section 4 -->
        <section>
            <h2 style="font-size:1.25rem; font-weight:700; margin-bottom:10px; display:flex; align-items:center; gap:8px;">
                <span class="material-symbols-outlined" style="font-size:20px; color:var(--clr-primary, #0891B2);">local_parking</span>
                4. Reservation Rules
            </h2>
            <p style="color:var(--clr-text-muted, #475569); line-height:1.75; margin-bottom:10px;">
                When making a parking reservation you agree that:
            </p>
            <ul style="list-style:none; padding:0; display:flex; flex-direction:column; gap:8px;">
                <?php
                $rules = [
                    ['check_circle', 'You will only book slots for vehicles registered under your account.'],
                    ['check_circle', 'You will arrive within the reservation window; no-show slots may be released after 15 minutes.'],
                    ['check_circle', 'You will comply with all posted campus traffic and parking regulations.'],
                    ['check_circle', 'You will not sub-let, transfer, or sell your reserved slot to another person.'],
                    ['check_circle', 'Cancellations must be made at least 30 minutes before the start time to avoid penalties.'],
                    ['check_circle', 'You will not attempt to reserve slots on behalf of third parties or for commercial purposes.'],
                ];
                foreach ($rules as [$icon, $text]): ?>
                <li style="display:flex; align-items:flex-start; gap:10px; padding:12px 16px; background:var(--clr-surface-container-low, #f1f5f9); border-radius:10px;">
                    <span class="material-symbols-outlined" style="font-size:18px; color:#10B981; margin-top:2px; flex-shrink:0;"><?= $icon ?></span>
                    <span style="font-size:14px; color:var(--clr-text, #0f172a); line-height:1.6;"><?= $text ?></span>
                </li>
                <?php endforeach; ?>
            </ul>
        </section>

        <hr style="border:none; border-top:1px solid var(--clr-outline-variant, #e2e8f0);">

        <!-- Section 5 -->
        <section>
            <h2 style="font-size:1.25rem; font-weight:700; margin-bottom:10px; display:flex; align-items:center; gap:8px;">
                <span class="material-symbols-outlined" style="font-size:20px; color:var(--clr-primary, #0891B2);">block</span>
                5. Prohibited Conduct
            </h2>
            <p style="color:var(--clr-text-muted, #475569); line-height:1.75; margin-bottom:10px;">
                You must not:
            </p>
            <ul style="padding-left:1.5rem; display:flex; flex-direction:column; gap:6px; color:var(--clr-text-muted, #475569); font-size:14px; line-height:1.75;">
                <li>Use the platform for any unlawful purpose or in violation of university policy.</li>
                <li>Attempt to gain unauthorised access to the platform's systems or other users' accounts.</li>
                <li>Submit false or misleading vehicle information to obtain reservations.</li>
                <li>Interfere with or disrupt the platform's infrastructure or servers.</li>
                <li>Scrape, copy, or mirror the platform's data without written permission.</li>
                <li>Use automated scripts or bots to make bulk or fraudulent reservations.</li>
                <li>Harass, threaten, or harm other users or campus parking staff.</li>
            </ul>
        </section>

        <hr style="border:none; border-top:1px solid var(--clr-outline-variant, #e2e8f0);">

        <!-- Section 6 -->
        <section>
            <h2 style="font-size:1.25rem; font-weight:700; margin-bottom:10px; display:flex; align-items:center; gap:8px;">
                <span class="material-symbols-outlined" style="font-size:20px; color:var(--clr-primary, #0891B2);">copyright</span>
                6. Intellectual Property
            </h2>
            <p style="color:var(--clr-text-muted, #475569); line-height:1.75;">
                All content, trademarks, logos, and software comprising CampusPark are owned by or licensed to us
                and are protected by applicable intellectual property laws. You are granted a limited, non-exclusive,
                non-transferable licence to access and use the platform solely for the purposes described in these Terms.
                No other rights are granted.
            </p>
        </section>

        <hr style="border:none; border-top:1px solid var(--clr-outline-variant, #e2e8f0);">

        <!-- Section 7 -->
        <section>
            <h2 style="font-size:1.25rem; font-weight:700; margin-bottom:10px; display:flex; align-items:center; gap:8px;">
                <span class="material-symbols-outlined" style="font-size:20px; color:var(--clr-primary, #0891B2);">money_off</span>
                7. Disclaimers &amp; Limitation of Liability
            </h2>
            <p style="color:var(--clr-text-muted, #475569); line-height:1.75; margin-bottom:10px;">
                CampusPark is provided on an <strong>"as is"</strong> and <strong>"as available"</strong> basis without
                warranties of any kind, express or implied. We do not warrant that:
            </p>
            <ul style="padding-left:1.5rem; display:flex; flex-direction:column; gap:6px; color:var(--clr-text-muted, #475569); font-size:14px; line-height:1.75;">
                <li>The service will be uninterrupted, error-free, or secure at all times.</li>
                <li>Parking availability data will always be perfectly accurate in real time.</li>
                <li>A reserved slot will be physically available due to unforeseen circumstances (e.g. maintenance).</li>
            </ul>
            <p style="color:var(--clr-text-muted, #475569); line-height:1.75; margin-top:10px;">
                To the fullest extent permitted by law, CampusPark and its operators shall not be liable for any
                indirect, incidental, special, or consequential damages arising from your use of the service, including
                parking fines or penalties issued by campus security for violations of campus regulations.
            </p>
        </section>

        <hr style="border:none; border-top:1px solid var(--clr-outline-variant, #e2e8f0);">

        <!-- Section 8 -->
        <section>
            <h2 style="font-size:1.25rem; font-weight:700; margin-bottom:10px; display:flex; align-items:center; gap:8px;">
                <span class="material-symbols-outlined" style="font-size:20px; color:var(--clr-primary, #0891B2);">person_off</span>
                8. Termination
            </h2>
            <p style="color:var(--clr-text-muted, #475569); line-height:1.75;">
                We reserve the right to suspend or terminate your account at our discretion, without prior notice,
                if we believe you have violated these Terms, engaged in fraudulent activity, or posed a risk to other
                users or the platform. Upon termination, your right to access the service ceases immediately.
                You may also delete your account at any time via the account settings page.
            </p>
        </section>

        <hr style="border:none; border-top:1px solid var(--clr-outline-variant, #e2e8f0);">

        <!-- Section 9 -->
        <section>
            <h2 style="font-size:1.25rem; font-weight:700; margin-bottom:10px; display:flex; align-items:center; gap:8px;">
                <span class="material-symbols-outlined" style="font-size:20px; color:var(--clr-primary, #0891B2);">privacy_tip</span>
                9. Privacy
            </h2>
            <p style="color:var(--clr-text-muted, #475569); line-height:1.75;">
                Your use of CampusPark is also governed by our
                <a href="/parking-system/public/privacy-policy.php" style="color:var(--clr-primary, #0891B2); font-weight:600;">Privacy Policy</a>,
                which is incorporated into these Terms by reference. By using the service you consent to the
                collection and use of your information as described therein.
            </p>
        </section>

        <hr style="border:none; border-top:1px solid var(--clr-outline-variant, #e2e8f0);">

        <!-- Section 10 -->
        <section>
            <h2 style="font-size:1.25rem; font-weight:700; margin-bottom:10px; display:flex; align-items:center; gap:8px;">
                <span class="material-symbols-outlined" style="font-size:20px; color:var(--clr-primary, #0891B2);">update</span>
                10. Changes to These Terms
            </h2>
            <p style="color:var(--clr-text-muted, #475569); line-height:1.75;">
                We may revise these Terms at any time. The revised Terms will be posted on this page with an updated
                "Last updated" date. We will notify active users of material changes via the dashboard notification
                system. Your continued use of CampusPark after changes are posted constitutes your acceptance of
                the updated Terms.
            </p>
        </section>

        <hr style="border:none; border-top:1px solid var(--clr-outline-variant, #e2e8f0);">

        <!-- Section 11 -->
        <section>
            <h2 style="font-size:1.25rem; font-weight:700; margin-bottom:10px; display:flex; align-items:center; gap:8px;">
                <span class="material-symbols-outlined" style="font-size:20px; color:var(--clr-primary, #0891B2);">balance</span>
                11. Governing Law
            </h2>
            <p style="color:var(--clr-text-muted, #475569); line-height:1.75;">
                These Terms shall be governed by and construed in accordance with the laws of the jurisdiction in
                which the university is located, without regard to its conflict of law provisions. Any disputes
                arising under these Terms shall first be subject to good-faith negotiation and, if unresolved,
                to the exclusive jurisdiction of the competent courts of that jurisdiction.
            </p>
        </section>

        <hr style="border:none; border-top:1px solid var(--clr-outline-variant, #e2e8f0);">

        <!-- Section 12 - Contact -->
        <section style="padding:24px; background:var(--clr-surface-container-low, #f1f5f9); border-radius:16px;">
            <h2 style="font-size:1.25rem; font-weight:700; margin-bottom:8px; display:flex; align-items:center; gap:8px;">
                <span class="material-symbols-outlined" style="font-size:20px; color:var(--clr-primary, #0891B2);">contact_support</span>
                12. Contact Us
            </h2>
            <p style="color:var(--clr-text-muted, #475569); line-height:1.75; font-size:14px;">
                If you have any questions about these Terms of Service, please reach out to:
            </p>
            <address style="font-style:normal; margin-top:10px; font-size:14px; color:var(--clr-text, #0f172a); line-height:1.8;">
                <strong>CampusPark — Legal &amp; Compliance</strong><br>
                Campus Facilities &amp; Parking Management<br>
                University Administration Building<br>
                Email: <a href="mailto:legal@campuspark.edu" style="color:var(--clr-primary, #0891B2);">legal@campuspark.edu</a>
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

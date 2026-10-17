<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="includes/css/styles.css?v=20261018-about-cta-2">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <title>About CourtConnect Bataan</title>
</head>
<body class="about-page">
    <?php include 'includes/navigation/header.php'; ?>

    <main>
        <section class="about-hero" aria-labelledby="aboutTitle">
            <div class="about-hero-copy">
                <p class="about-eyebrow"><span>ABOUT COURTCONNECT</span><span>01 / BATAAN</span></p>
                <h1 id="aboutTitle">Making Court Reservations Easier in Bataan</h1>
                <p class="about-lead">CourtConnect Bataan helps players discover participating badminton and pickleball facilities, check court availability, and reserve a time more conveniently.</p>
                <div class="about-actions">
                    <a class="about-primary-button" href="facility.php">Find a Court <span aria-hidden="true">→</span></a>
                    <a class="about-hero-link" href="adminregister.php">For facility owners <span aria-hidden="true">↗</span></a>
                </div>
            </div>
            <div class="about-racket-graphic" role="group" aria-label="Pickleball paddle and badminton racket">
                <span class="about-racket-caption">READY FOR EVERY RALLY</span>
                <div class="about-racket-pair">
                    <figure class="about-racket-item">
                        <img src="assets/images/about/pickleball-paddle.png" alt="Blue pickleball paddle">
                        <figcaption>PICKLEBALL</figcaption>
                    </figure>
                    <figure class="about-racket-item">
                        <img src="assets/images/about/badminton-racket.png" alt="Yellow and black badminton racket">
                        <figcaption>BADMINTON</figcaption>
                    </figure>
                </div>
                <span class="about-racket-coordinate">COURTCONNECT <i>·</i> BATAAN</span>
            </div>
        </section>

        <section class="about-section about-intro" aria-labelledby="whoWeAre">
            <div class="about-section-index">
                <span>01</span>
                <p class="about-eyebrow">WHO WE ARE</p>
                <h2 id="whoWeAre">Connecting players with participating local courts.</h2>
            </div>
            <p>CourtConnect Bataan is a web-based court reservation and management system for participating badminton and pickleball facilities in selected municipalities of Bataan. Players can explore facilities, review court schedules, and manage their reservations online. Authorized facility administrators can organize court schedules and bookings, and review payment records through their facility workspace.</p>
        </section>

        <section class="about-section about-purpose" aria-label="Our mission and vision">
            <article class="about-purpose-card">
                <span class="about-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none"><path d="M12 21s8-4 8-11V5l-8-3-8 3v5c0 7 8 11 8 11Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="m9 12 2 2 4-4" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </span>
                <p class="about-eyebrow">OUR MISSION</p>
                <h2>Make every booking easier to manage</h2>
                <p>To provide a reliable, secure, and user-friendly platform that makes booking badminton and pickleball courts more convenient for players and easier to manage for participating facilities in Bataan.</p>
            </article>
            <article class="about-purpose-card">
                <span class="about-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.7"/><path d="M3.5 12h17M12 3c2.1 2.3 3.2 5.3 3.2 9s-1.1 6.7-3.2 9c-2.1-2.3-3.2-5.3-3.2-9S9.9 5.3 12 3Z" stroke="currentColor" stroke-width="1.5"/></svg>
                </span>
                <p class="about-eyebrow">OUR VISION</p>
                <h2>A trusted local platform for court reservations</h2>
                <p>To help players and facility operators across Bataan manage bookings more efficiently through accessible digital technology.</p>
            </article>
        </section>

        <section class="about-section about-challenge" aria-labelledby="challengeTitle">
            <div class="about-section-index">
                <span>02</span>
                <p class="about-eyebrow">WHY COURTCONNECT</p>
                <h2 id="challengeTitle">Bringing scattered booking details into clearer view.</h2>
            </div>
            <div class="about-challenge-copy">
                <p>When reservations rely on separate messages, calls, or walk-ins, it can be harder for players to check availability and for facilities to keep booking details organized. Manually tracking changes, cancellations, and payment records can add more work.</p>
                <p>CourtConnect brings participating facilities and reservation information together in one web-based service. Players can review listed court schedules, while facility administrators can manage bookings and related records from their workspace.</p>
            </div>
        </section>

        <section class="about-section about-offers" aria-labelledby="offersTitle">
            <div class="about-section-heading about-offers-heading">
                <span class="about-section-index-number">03</span>
                <div>
                <p class="about-eyebrow">WHAT COURTCONNECT OFFERS</p>
                    <h2 id="offersTitle">A practical toolkit for the court community.</h2>
                </div>
            </div>
            <div class="about-feature-grid">
                <article class="about-feature-card">
                    <span class="about-feature-number">01</span>
                    <h3>Find Courts</h3>
                    <p>Browse participating badminton and pickleball facilities in Bataan.</p>
                    <a href="facility.php">Browse facilities <span aria-hidden="true">→</span></a>
                </article>
                <article class="about-feature-card">
                    <span class="about-feature-number">02</span>
                    <h3>Check Availability</h3>
                    <p>Review listed court schedules and available time slots before requesting a reservation.</p>
                </article>
                <article class="about-feature-card">
                    <span class="about-feature-number">03</span>
                    <h3>Manage Reservations</h3>
                    <p>View your booking details and keep track of reservations through the website.</p>
                    <a href="mybookings.php">View my bookings <span aria-hidden="true">→</span></a>
                </article>
                <article class="about-feature-card">
                    <span class="about-feature-number">04</span>
                    <h3>Facility Management</h3>
                    <p>Authorized administrators can manage court schedules, bookings, and payment records.</p>
                    <a href="signin.php">Facility sign in <span aria-hidden="true">→</span></a>
                </article>
            </div>
        </section>

        <section class="about-coverage" aria-labelledby="coverageTitle">
            <svg class="about-coverage-map" viewBox="0 0 220 190" role="img" aria-label="Illustrated map of Bataan">
                <path class="about-coverage-map-shape" d="M115 9 145 19 158 39 151 53 169 68 161 83 175 98 160 111 168 128 153 138 157 154 143 169 128 179 111 172 95 179 82 165 67 158 71 142 55 130 66 117 54 102 67 90 60 75 76 65 72 50 90 41 94 26 107 23Z"/>
                <path class="about-coverage-map-lines" d="m95 42 48 13m-67 17 69 14m-75 18 76 13m-68 17 62 13m-48 17 42 9"/>
                <circle cx="117" cy="34" r="4"/>
                <circle cx="147" cy="88" r="4"/>
                <circle cx="103" cy="138" r="4"/>
                <circle cx="132" cy="162" r="4"/>
            </svg>
            <div>
                <p class="about-eyebrow">OUR COVERAGE</p>
                <h2 id="coverageTitle">Rooted in Bataan</h2>
                <p>CourtConnect focuses on participating badminton and pickleball facilities in selected municipalities of Bataan. Listings depend on facilities that have joined the platform.</p>
            </div>
        </section>

        <section class="about-final-cta" aria-labelledby="aboutCtaTitle">
            <div>
                <p class="about-eyebrow">YOUR NEXT GAME STARTS HERE</p>
                <h2 id="aboutCtaTitle">Ready to Find Your Next Court?</h2>
                <p>Explore participating badminton and pickleball facilities in Bataan and find a court that fits your schedule.</p>
            </div>
            <div class="about-actions">
                <a class="about-cta-button about-cta-button-primary" href="facility.php">Explore Courts <span aria-hidden="true">→</span></a>
                <a class="about-cta-button about-cta-button-secondary" href="adminregister.php">Register your facility <span aria-hidden="true">→</span></a>
            </div>
        </section>
    </main>

    <?php include 'includes/navigation/footer.php'; ?>
</body>
</html>

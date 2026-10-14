<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$isLoggedIn = !empty($_SESSION["logged_in"]);
$displayName = trim($_SESSION["first_name"] ?? "");
$ownerOnboardingHeader = !empty($ownerOnboardingHeader);
?>
<script src="includes/js/toast.js" defer></script>
<script src="includes/js/admin_dialog.js" defer></script>
<?php if ($ownerOnboardingHeader): ?>
<header class="owner-onboarding-header">
    <a class="brand" href="index.php" aria-label="CourtConnect home">
        <img class="brand-logo" src="uploads/courtconnect%20applogo.png" alt="CourtConnect logo">
        <span class="brand-name"><strong>Court</strong><em>Connect</em></span>
    </a>
    <div class="owner-onboarding-header-actions">
        <?php if ($isLoggedIn): ?>
            <span><?= htmlspecialchars($displayName ?: "Facility owner", ENT_QUOTES, "UTF-8") ?></span>
            <a href="logout.php" data-confirm-logout>Log out</a>
        <?php else: ?>
            <span>Already have an account?</span>
            <a href="adminsignin.php">Sign in</a>
        <?php endif; ?>
    </div>
</header>
<?php else: ?>
<header class="site-header">
    <nav class="navigation" aria-label="Main navigation">
        <a class="brand" href="index.php" aria-label="CourtConnect home">
            <img class="brand-logo" src="uploads/courtconnect%20applogo.png" alt="CourtConnect logo">
            <span class="brand-name"><strong>Court</strong><em>Connect</em></span>
        </a>
        <div class="nav-links">
            <a href="index.php">Home</a>
            <a href="facility.php">Find a court</a>
            <a href="mybookings.php">My bookings</a>
            <a href="about.php">About us</a>
            <?php if ($isLoggedIn): ?>
                <span class="nav-user">Hi, <?php echo htmlspecialchars($displayName ?: "Player", ENT_QUOTES, "UTF-8"); ?></span>
                <a class="nav-signin" href="logout.php" data-confirm-logout>Log out <span aria-hidden="true">&rarr;</span></a>
            <?php else: ?>
                <a class="nav-signin" href="signin.php">Sign in <span aria-hidden="true">&rarr;</span></a>
            <?php endif; ?>
        </div>
    </nav>
</header>
<?php endif; ?>
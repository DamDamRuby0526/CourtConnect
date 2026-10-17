<?php if (!empty($courtconnectDashboardLayout)): ?>
    <?php
    if ((int) ($_SESSION["role_id"] ?? 0) === 5 && !isset($courtconnectPendingCount)) {
        $pendingApplicationsResult = $conn->query("SELECT COUNT(*) AS total FROM admins WHERE status = 'Pending'");
        if ($pendingApplicationsResult) {
            $courtconnectPendingCount = (int) $pendingApplicationsResult->fetch_assoc()["total"];
        } else {
            error_log("Unable to load pending facility application count: " . $conn->error);
            $courtconnectPendingCount = 0;
        }
    }
    ?>
    <aside class="owner-sidebar">
        <a class="owner-brand" href="<?= (int) ($_SESSION["role_id"] ?? 0) === 5 ? "superadmin.php" : "reviewregistrations.php" ?>"><span><strong>CourtConnect</strong><small><?= (int) ($_SESSION["role_id"] ?? 0) === 5 ? "Superadmin" : "Facility Applications" ?></small></span></a>
        <nav class="owner-nav" aria-label="Staff workspace">
            <?php if ((int) ($_SESSION["role_id"] ?? 0) === 4): ?>
                <span class="owner-nav-label">FACILITY REVIEW</span>
                <a class="<?= ($courtconnectNavPage ?? "") === "applications" ? "active" : "" ?>" href="reviewregistrations.php">
                    <span aria-hidden="true">&#10003;</span>Facility Applications
                    <b class="staff-pending-badge"><?= (int) ($courtconnectPendingCount ?? 0) ?></b>
                </a>
            <?php elseif ((int) ($_SESSION["role_id"] ?? 0) === 5): ?>
                <span class="owner-nav-label">SYSTEM ADMINISTRATION</span>
                <a class="<?= ($courtconnectNavPage ?? "") === "users" ? "active" : "" ?>" href="manageusers.php"><span aria-hidden="true">&#9786;</span>Users</a>
                <a class="<?= ($courtconnectNavPage ?? "") === "applications" ? "active" : "" ?>" href="reviewregistrations.php"><span aria-hidden="true">&#10003;</span>Facility Applications<b class="staff-pending-badge"><?= (int) ($courtconnectPendingCount ?? 0) ?></b></a>
                <a class="<?= ($courtconnectNavPage ?? "") === "facilities" ? "active" : "" ?>" href="managefacilities.php"><span aria-hidden="true">&#9881;</span>Facilities</a>
            <?php endif; ?>
        </nav>
        <div class="owner-sidebar-user">
            <span class="owner-user-avatar" aria-hidden="true"><?= htmlspecialchars(strtoupper(substr($_SESSION["first_name"] ?? "S", 0, 1)), ENT_QUOTES, "UTF-8") ?></span>
            <span><strong><?= htmlspecialchars(trim(($_SESSION["first_name"] ?? "") . " " . ($_SESSION["last_name"] ?? "Staff")), ENT_QUOTES, "UTF-8") ?></strong><small><?= htmlspecialchars($_SESSION["email"] ?? "CourtConnect staff", ENT_QUOTES, "UTF-8") ?></small></span>
            <a href="../logout.php" data-confirm-logout aria-label="Log out" title="Log out">&#8594;</a>
        </div>
    </aside>
<?php elseif (!empty($ownerDashboardLayout)): ?>
    <aside class="owner-sidebar">
        <a class="owner-brand" href="admindex.php">
            <?php if (!empty($facilityLogo)): ?>
                <img class="admin-facility-logo" src="../uploads/facility/<?= rawurlencode(basename($facilityLogo)) ?>" alt="">
            <?php endif; ?>
            <span><strong>CourtConnect</strong><small><?= htmlspecialchars((string) ($facilityName ?? "Facility owner"), ENT_QUOTES, "UTF-8") ?></small></span>
        </a>
        <nav class="owner-nav" aria-label="Owner workspace">
            <span class="owner-nav-label">WORKSPACE</span>
            <a class="<?= ($ownerNavPage ?? "") === "upcoming" ? "active" : "" ?>" href="admindex.php"><span aria-hidden="true">&#9719;</span>Upcoming reservations</a>
            <a class="<?= ($ownerNavPage ?? "") === "bookings" ? "active" : "" ?>" href="managebookings.php"><span class="nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="17" rx="2"></rect><path d="M16 2v4M8 2v4M3 10h18M9 16l2 2 4-4"></path></svg></span>Bookings</a>
            <a class="<?= ($ownerNavPage ?? "") === "payments" ? "active" : "" ?>" href="managepayments.php"><span aria-hidden="true">&#36;</span>Payments</a>
            <a class="<?= ($ownerNavPage ?? "") === "reports" ? "active" : "" ?>" href="managereports.php"><span class="nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"></path><path d="M18 17V9M13 17V5M8 17v-3"></path></svg></span>Sales Reports</a>
            <span class="owner-nav-label">OWNER SETTINGS</span>
            <div class="owner-nav-submenu">
                <a class="<?= ($ownerNavPage ?? "") === "facility" ? "active" : "" ?>" href="managecourts.php"><span aria-hidden="true">&#9881;</span>Facility settings</a>
                <a class="<?= ($ownerNavPage ?? "") === "accounts" ? "active" : "" ?>" href="manageadmins.php"><span aria-hidden="true">&#9786;</span>Admin accounts</a>
            </div>
        </nav>
        <div class="owner-sidebar-user">
            <span class="owner-user-avatar" aria-hidden="true"><?= htmlspecialchars(strtoupper(substr($_SESSION["first_name"] ?? "O", 0, 1)), ENT_QUOTES, "UTF-8") ?></span>
            <span><strong><?= htmlspecialchars(trim(($_SESSION["first_name"] ?? "") . " " . ($_SESSION["last_name"] ?? "Owner")), ENT_QUOTES, "UTF-8") ?></strong><small><?= htmlspecialchars($_SESSION["email"] ?? "Facility owner", ENT_QUOTES, "UTF-8") ?></small></span>
            <a href="../logout.php" data-confirm-logout aria-label="Log out" title="Log out">&#8594;</a>
        </div>
    </aside>
<?php elseif (!empty($adminDashboardLayout)): ?>
    <aside class="admin-sidebar">
        <a class="admin-brand" href="admindex.php">
            <?php if (!empty($facilityLogo)): ?>
                <img class="admin-facility-logo" src="../uploads/facility/<?= rawurlencode(basename($facilityLogo)) ?>" alt="">
            <?php endif; ?>
            <span class="admin-brand-copy"><strong>CourtConnect</strong><small><?= htmlspecialchars((string) ($facilityName ?? "Club admin"), ENT_QUOTES, "UTF-8") ?></small></span>
        </a>
        <nav class="admin-nav" aria-label="Admin workspace">
            <span class="admin-nav-label">WORKSPACE</span>
            <a class="<?= ($adminNavPage ?? "") === "upcoming" ? "active" : "" ?>" href="admindex.php"><span aria-hidden="true">&#9719;</span>Upcoming reservations</a>
            <a class="<?= ($adminNavPage ?? "") === "bookings" ? "active" : "" ?>" href="managebookings.php"><span class="nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="17" rx="2"></rect><path d="M16 2v4M8 2v4M3 10h18M9 16l2 2 4-4"></path></svg></span>Bookings</a>
            <a class="<?= ($adminNavPage ?? "") === "payments" ? "active" : "" ?>" href="managepayments.php"><span aria-hidden="true">&#36;</span>Payments</a>
            <a class="<?= ($adminNavPage ?? "") === "reports" ? "active" : "" ?>" href="managereports.php"><span class="nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"></path><path d="M18 17V9M13 17V5M8 17v-3"></path></svg></span>Sales Reports</a>
            <?php if ((int) ($_SESSION["role_id"] ?? 0) === 3): ?>
                <span class="admin-nav-label">OWNER SETTINGS</span>
                <div class="admin-nav-submenu">
                    <a href="managecourts.php"><span aria-hidden="true">&#9881;</span>Facility settings</a>
                    <a href="manageadmins.php"><span aria-hidden="true">&#9786;</span>Admin accounts</a>
                </div>
            <?php endif; ?>
        </nav>
        <div class="admin-sidebar-user">
            <span class="admin-user-avatar" aria-hidden="true"><?= htmlspecialchars(strtoupper(substr($_SESSION["first_name"] ?? "A", 0, 1)), ENT_QUOTES, "UTF-8") ?></span>
            <span class="admin-user-copy"><strong><?= htmlspecialchars(trim(($_SESSION["first_name"] ?? "") . " " . ($_SESSION["last_name"] ?? "Admin")), ENT_QUOTES, "UTF-8") ?></strong><small><?= htmlspecialchars($_SESSION["email"] ?? "Facility admin", ENT_QUOTES, "UTF-8") ?></small></span>
            <a href="../logout.php" data-confirm-logout aria-label="Log out" title="Log out">&#8594;</a>
        </div>
    </aside>
<?php else: ?>
    <header>
        <nav class="navigation">
            <ul>
                <p>CourtConnect Staff</p>
                <li><a href="managereports.php">Reports</a></li>
                <li><a href="manageusers.php">Users</a></li>
                <?php if ((int) ($_SESSION["role_id"] ?? 0) === 4): ?>
                    <li><a href="reviewregistrations.php">Facility Applications</a></li>
                <?php endif; ?>
            </ul>
        </nav>
    </header>
<?php endif; ?>

<script src="../includes/js/admin_dialog.js"></script>
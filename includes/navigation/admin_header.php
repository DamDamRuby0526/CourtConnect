<?php if (!empty($ownerDashboardLayout)): ?>
    <aside class="owner-sidebar">
        <a class="owner-brand" href="admindex.php">
            <span class="owner-brand-mark" aria-hidden="true">CC</span>
            <span><strong>CourtConnect</strong><small>FACILITY OWNER</small></span>
        </a>
        <nav class="owner-nav" aria-label="Owner workspace">
            <span class="owner-nav-label">WORKSPACE</span>
            <a class="<?= ($ownerNavPage ?? "") === "upcoming" ? "active" : "" ?>" href="admindex.php"><span aria-hidden="true">&#9719;</span>Upcoming reservations</a>
            <a class="<?= ($ownerNavPage ?? "") === "bookings" ? "active" : "" ?>" href="managebookings.php"><span aria-hidden="true">&#9633;</span>Bookings</a>
            <a class="<?= ($ownerNavPage ?? "") === "payments" ? "active" : "" ?>" href="managepayments.php"><span aria-hidden="true">&#36;</span>Payments</a>
            <span class="owner-nav-label">OWNER SETTINGS</span>
            <div class="owner-nav-submenu">
                <a class="<?= ($ownerNavPage ?? "") === "facility" ? "active" : "" ?>" href="managecourts.php"><span aria-hidden="true">&#9638;</span>Facility settings</a>
                <a class="<?= ($ownerNavPage ?? "") === "accounts" ? "active" : "" ?>" href="manageadmins.php"><span aria-hidden="true">&#9786;</span>Admin accounts</a>
            </div>
        </nav>
        <div class="owner-sidebar-user">
            <span class="owner-user-avatar" aria-hidden="true"><?= htmlspecialchars(strtoupper(substr($_SESSION["first_name"] ?? "O", 0, 1)), ENT_QUOTES, "UTF-8") ?></span>
            <span><strong><?= htmlspecialchars(trim(($_SESSION["first_name"] ?? "") . " " . ($_SESSION["last_name"] ?? "Owner")), ENT_QUOTES, "UTF-8") ?></strong><small><?= htmlspecialchars($_SESSION["email"] ?? "Facility owner", ENT_QUOTES, "UTF-8") ?></small></span>
            <a href="../logout.php" aria-label="Log out" title="Log out">&#8594;</a>
        </div>
    </aside>
<?php elseif (!empty($adminDashboardLayout)): ?>
    <aside class="admin-sidebar">
        <a class="admin-brand" href="admindex.php">
            <span class="admin-brand-mark" aria-hidden="true">CC</span>
            <span class="admin-brand-copy"><strong>CourtConnect</strong><small><?= htmlspecialchars((string) ($facilityName ?? "Club admin"), ENT_QUOTES, "UTF-8") ?></small></span>
        </a>
        <nav class="admin-nav" aria-label="Admin workspace">
            <span class="admin-nav-label">WORKSPACE</span>
            <a class="<?= ($adminNavPage ?? "") === "upcoming" ? "active" : "" ?>" href="admindex.php"><span aria-hidden="true">&#9719;</span>Upcoming reservations</a>
            <a class="<?= ($adminNavPage ?? "") === "bookings" ? "active" : "" ?>" href="managebookings.php"><span aria-hidden="true">&#9633;</span>Bookings</a>
            <a class="<?= ($adminNavPage ?? "") === "payments" ? "active" : "" ?>" href="managepayments.php"><span aria-hidden="true">&#36;</span>Payments</a>
            <?php if ((int) ($_SESSION["role_id"] ?? 0) === 3): ?>
                <span class="admin-nav-label">OWNER SETTINGS</span>
                <div class="admin-nav-submenu">
                    <a href="managecourts.php"><span aria-hidden="true">&#9638;</span>Facility settings</a>
                    <a href="manageadmins.php"><span aria-hidden="true">&#9786;</span>Admin accounts</a>
                </div>
            <?php endif; ?>
        </nav>
        <div class="admin-sidebar-user">
            <span class="admin-user-avatar" aria-hidden="true"><?= htmlspecialchars(strtoupper(substr($_SESSION["first_name"] ?? "A", 0, 1)), ENT_QUOTES, "UTF-8") ?></span>
            <span class="admin-user-copy"><strong><?= htmlspecialchars(trim(($_SESSION["first_name"] ?? "") . " " . ($_SESSION["last_name"] ?? "Admin")), ENT_QUOTES, "UTF-8") ?></strong><small><?= htmlspecialchars($_SESSION["email"] ?? "Facility admin", ENT_QUOTES, "UTF-8") ?></small></span>
            <a href="../logout.php" aria-label="Log out" title="Log out">&#8594;</a>
        </div>
    </aside>
<?php else: ?>
    <header>
        <nav class="navigation">
            <ul>
                <p>Admin Dashboard</p>
                <li><a href="managereports.php">Reports</a></li>
                <li><a href="managebookings.php">Bookings</a></li>
                <li><a href="manageusers.php">Users</a></li>
                <li><a href="managefacilities.php">Facility</a></li>
                <?php if ((int) ($_SESSION["role_id"] ?? 0) === 4): ?>
                    <li><a href="reviewregistrations.php">Facility Applications</a></li>
                <?php endif; ?>
                <li><a href="admindex.php">Dashboard</a></li>
            </ul>
        </nav>
    </header>
<?php endif; ?>
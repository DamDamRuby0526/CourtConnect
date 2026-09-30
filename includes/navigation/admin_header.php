<?php if (!empty($ownerDashboardLayout)): ?>
    <aside class="owner-sidebar">
        <a class="owner-brand" href="admindex.php">
            <span class="owner-brand-mark" aria-hidden="true">CC</span>
            <span><strong>CourtConnect</strong><small>FACILITY OWNER</small></span>
        </a>
        <nav class="owner-nav" aria-label="Owner workspace">
            <span class="owner-nav-label">MANAGE</span>
            <a href="admindex.php"><span aria-hidden="true">&#9633;</span>Bookings &amp; payments</a>
            <a class="active" href="managecourts.php"><span aria-hidden="true">&#9638;</span>Court settings</a>
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
            <span class="admin-brand-copy"><strong>CourtConnect</strong><small><?= dashboardEscape($facilityName ?? "Club admin") ?></small></span>
        </a>
        <nav class="admin-nav" aria-label="Admin workspace">
            <span class="admin-nav-label">WORKSPACE</span>
            <a class="active" href="#bookings" data-admin-view="bookings"><span aria-hidden="true">▤</span>Bookings</a>
            <a href="#payments" data-admin-view="payments"><span aria-hidden="true">＄</span>Payments</a>
            <a href="#upcoming" data-admin-view="upcoming"><span aria-hidden="true">◷</span>Upcoming reservations</a>
            <?php if ((int) ($_SESSION["role_id"] ?? 0) === 3): ?>
                <span class="admin-nav-label">OWNER SETTINGS</span>
                <a href="managecourts.php"><span aria-hidden="true">&#9638;</span>Court settings</a>
            <?php endif; ?>
        </nav>
        <div class="admin-sidebar-user">
            <span class="admin-user-avatar" aria-hidden="true"><?= dashboardEscape(strtoupper(substr($_SESSION["first_name"] ?? "A", 0, 1))) ?></span>
            <span class="admin-user-copy"><strong><?= dashboardEscape(trim(($_SESSION["first_name"] ?? "") . " " . ($_SESSION["last_name"] ?? "Admin"))) ?></strong><small><?= dashboardEscape($_SESSION["email"] ?? "Facility admin") ?></small></span>
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
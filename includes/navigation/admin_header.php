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
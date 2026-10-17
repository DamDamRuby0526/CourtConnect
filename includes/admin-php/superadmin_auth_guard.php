<?php
require_once __DIR__ . "/staff_role_guard.php";
requireStaffRole([5], (bool) ($staffRoleGuardApi ?? true));

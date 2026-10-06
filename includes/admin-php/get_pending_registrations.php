<?php
require_once __DIR__ . "/courtconnect_auth_guard.php";

$sql = "SELECT a.admin_id, a.user_id, a.government_id_img, a.business_reg_img,
               a.mayors_permit_img, a.proof_of_property_img,
               u.first_name, u.last_name, u.email, u.phone_number,
               f.facility_name, f.sport_type, f.address, f.municipality, f.facility_img
        FROM admins a
        JOIN users u ON u.user_id = a.user_id
        JOIN facilities f ON f.facility_id = a.facility_id
        WHERE a.status = 'Pending'
        ORDER BY a.created_at ASC";
$result = $conn->query($sql);
$registrations = [];

while ($row = $result->fetch_assoc()) {
    $registrations[] = $row;
}

echo json_encode(["success" => true, "registrations" => $registrations]);
$conn->close();

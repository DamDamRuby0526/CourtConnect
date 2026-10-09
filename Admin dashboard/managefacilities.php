<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../includes/css/styles.css" />
    <script src="../includes/js/admin_managefacilities.js" defer></script>
    <title>CourtConnect Bataan</title>
</head>

<body>

    <?php include '../includes/navigation/admin_header.php'; ?>

    <h2>Manage Facilities</h2>

    <button type="button" id="add-btn">Add New Facility</button>
    <div id="grid-container">
    </div>

    <!-- facility picture not matching sa filename randomizer shenanigan sa includes/upload_helper.php -->


    <!-- modal popup -->
    <div class="modal-container" id="modal_container">
        <div class="modal">
            <form id="facilityForm" enctype="multipart/form-data">
                <label for="facility_name">Facility Name</label><br>
                <input type="text" id="facility_name" name="facility_name" required><br>
                <label for="address">Address</label><br>
                <textarea id="address" name="address" required></textarea><br>
                <label for="municipality">Municipality</label><br>
                <select id="municipality" name="municipality" required>
                    <option value="Abucay">Abucay</option>
                    <option value="Bagac">Bagac</option>
                    <option value="Balanga City">Balanga City</option>
                    <option value="Dinalupihan">Dinalupihan</option>
                    <option value="Hermosa">Hermosa</option>
                    <option value="Limay">Limay</option>
                    <option value="Mariveles">Mariveles</option>
                    <option value="Morong">Morong</option>
                    <option value="Orani">Orani</option>
                    <option value="Orion">Orion</option>
                    <option value="Pilar">Pilar</option>
                    <option value="Samal">Samal</option>
                </select><br>
                <label for="sport_type">Sport Type</label><br>
                <select id="sport_type" name="sport_type" required>
                    <option value="Badminton">Badminton</option>
                    <option value="Pickleball">Pickleball</option>
                    <option value="Both">Both</option>
                </select><br>
                <label for="opening_time">Opening Time</label><br>
                <input type="time" id="opening_time" name="opening_time" required><br>
                <label for="closing_time">Closing Time</label><br>
                <input type="time" id="closing_time" name="closing_time" required><br>
                <label for="facility_img">Facility Image</label><br>
                <img id="facility_img_preview" src="" alt="" style="max-width:150px; display:none;"><br>
                <input type="file" id="facility_img" name="facility_img" accept="image/png, image/jpeg, image/webp"><br>
                <small id="facility_img_hint"></small><br>
                <label for="qr_img">QR Code Image</label><br>
                <img id="qr_img_preview" src="" alt="" style="max-width:150px; display:none;"><br>
                <input type="file" id="qr_img" name="qr_img" accept="image/png, image/jpeg, image/webp"><br>
                <small id="qr_img_hint"></small><br>
                <button type="button" class="save-btn">Save</button>
                <button type="button" class="cancel-btn">Cancel</button>
            </form>
        </div>
    </div>

</body>

<?php include '../includes/navigation/admin_footer.php'; ?>

</html>
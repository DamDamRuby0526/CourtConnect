// display facilities on the homepage
document.addEventListener("DOMContentLoaded", loadFacilities);

async function loadFacilities() {
    const container = document.getElementById("facilities-container");

    try {
        const response = await fetch("includes/backend-api/load_facilities.php");
        const data = await response.json();

        if (!data.success) {
            alert("Failed to load facilities.");
            return;
        }

        container.querySelectorAll(".item").forEach(card => card.remove());

        for (const facility of data.facilities) {
            const card = document.createElement("div");
            card.className = "item";

            if (facility.facility_img) {
                const img = document.createElement("img");
                img.src = "uploads/facility/" + facility.facility_img;
                img.alt = facility.facility_name;
                card.append(img);
            }

            const name = document.createElement("p");
            name.textContent = facility.facility_name;
            card.append(name);

            const location = document.createElement("p");
            location.textContent = `Location: ${facility.municipality}`;
            card.append(location);

            const sport = document.createElement("p");
            sport.textContent = `Sport: ${facility.sport_type}`;
            card.append(sport);

            const hours = document.createElement("p");
            hours.textContent = `Hours: ${facility.opening_time} - ${facility.closing_time}`;
            card.append(hours);

            const viewBtn = document.createElement("button");
            viewBtn.textContent = "View Courts";
            viewBtn.className = "view-btn";
            viewBtn.dataset.facilityId = facility.facility_id;
            card.append(viewBtn);

            container.append(card);
        }

    } catch (error) {
        console.error("Error loading facilities:", error);
    }
}

// navigate to read-only court listing for the selected facility
document.addEventListener("click", (e) => {
    if (e.target.classList.contains("view-btn")) {
        const facilityId = e.target.dataset.facilityId;
        window.location.href = `courts.php?facility_id=${facilityId}`;
    }
});
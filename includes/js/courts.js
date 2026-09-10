// display courts INSIDE a facility
document.addEventListener("DOMContentLoaded", loadCourts);

function getFacilityId() {
    const params = new URLSearchParams(window.location.search);
    return params.get("facility_id");
}

async function loadCourts() {
    const container = document.getElementById("courts-container");
    const facilityId = getFacilityId();

    if (!facilityId) {
        container.textContent = "No facility selected.";
        return;
    }

    try {
        const response = await fetch(
            `includes/backend-api/load_courts.php?facility_id=${facilityId}`
        );
        const data = await response.json();

        if (!data.success) {
            alert(data.message || "Failed to load courts.");
            return;
        }

        if (data.facility_name) {
            document.getElementById("facility-name").textContent = data.facility_name;
        }

        container.querySelectorAll(".item").forEach(card => card.remove());

        if (data.courts.length === 0) {
            const empty = document.createElement("p");
            empty.textContent = "No courts available for this facility yet.";
            container.append(empty);
            return;
        }

        for (const court of data.courts) {
            const card = document.createElement("div");
            card.className = "item";

            const name = document.createElement("p");
            name.textContent = court.court_no;
            card.append(name);

            const rate = document.createElement("p");
            rate.textContent = `Rate: ₱${court.court_rate} / hour`;
            card.append(rate);

            const desc = document.createElement("p");
            desc.textContent = `Description: ${court.description}`;
            card.append(desc);

            container.append(card);
        }

    } catch (error) {
        console.error("Error loading courts:", error);
    }
}
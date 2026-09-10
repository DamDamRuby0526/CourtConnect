// display courts INSIDE a facility
document.addEventListener("DOMContentLoaded", loadCourts);

// search for facility_id in URL
function getFacilityId() {
  const params = new URLSearchParams(window.location.search);
  return params.get("facility_id");
}

async function loadCourts() {
  const grid = document.getElementById("grid-container");
  const facilityId = getFacilityId();

  if (!facilityId) {
    alert("No facility selected");
    return;
  }

  try {
    const response = await fetch(
      `../includes/admin-php/get_court.php?facility_id=${facilityId}`,
    );
    const data = await response.json();

    if (!data.success) {
      alert("Failed to load courts.");
      return;
    }

    if (data.facility_name) {
      document.getElementById("facility-name").textContent = data.facility_name;
    }

    grid.replaceChildren();

    if (data.courts.length === 0) {
      const empty = document.createElement("p");
      empty.textContent = "No courts available for this facility yet.";
      grid.append(empty);
      return;
    }

    for (const court of data.courts) {
      const item = document.createElement("div");
      item.className = "item";

      const name = document.createElement("p");
      name.textContent = court.court_no;

      const rate = document.createElement("p");
      rate.textContent = `Rate: ₱${court.court_rate} / hour`;

      const desc = document.createElement("p");
      desc.textContent = `Description: ${court.description}`;

      const editBtn = document.createElement("button");
      editBtn.type = "button";
      editBtn.className = "edit-btn";
      editBtn.textContent = "Manage Court";
      editBtn.dataset.courtId = court.court_id;

      const openBtn = document.createElement("button");
      openBtn.type = "button";
      openBtn.className = "schedule-btn";
      openBtn.textContent = "Manage Schedule";
      openBtn.dataset.courtId = court.court_id;

      item.append(name, rate, desc, editBtn, openBtn);
      grid.append(item);
    }
  } catch (error) {
    console.error("Error loading courts:", error);
  }
}

// open modal for ADD
document.getElementById("add-btn").addEventListener("click", () => {
  const courtForm = document.getElementById("courtForm");
  const modalContainer = document.getElementById("modal_container");
  const facilityId = getFacilityId();
  courtForm.reset();
  courtForm.dataset.facilityId = facilityId;
  modal_container.classList.add("show");
});

// save button
document.addEventListener("click", async (e) => {
  if (e.target.classList.contains("save-btn")) {
    const form = document.getElementById("courtForm");
    const facilityId = form.dataset.facilityId;

    const payload = {
      facility_id: facilityId,
      court_no: document.getElementById("court_no").value,
      court_rate: document.getElementById("court_rate").value,
      description: document.getElementById("description").value,
      slot_duration: document.getElementById("slot_duration").value,
      court_status: document.getElementById("court_status").value,
    };

    const res = await fetch("../includes/admin-php/add_courts.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify(payload),
    });

    const result = await res.json();

    if (!result.success) {
      alert(result.message);
      return;
    }

    const modalContainer = document.getElementById("modal_container");
    modalContainer.classList.remove("show");

    location.reload();
  }
});

// schedule page
document.addEventListener("click", (e) => {
  if (!e.target.classList.contains("schedule-btn")) return;

  const courtId = e.target.dataset.courtId;

  window.location.href = `manageschedules.php?court_id=${courtId}`;
});


// cancel
document.addEventListener("click", (e) => {
  if (e.target.classList.contains("cancel-btn")) {
    modal_container.classList.remove("show");
  }
});

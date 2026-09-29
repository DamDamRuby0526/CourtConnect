// display courts INSIDE a facility
document.addEventListener("DOMContentLoaded", loadCourts);

function getFacilityId() {
  const params = new URLSearchParams(window.location.search);
  return params.get("facility_id");
}

async function loadCourts() {
  const grid = document.getElementById("grid-container");
  const facilityId = getFacilityId();

  if (!facilityId) {
    container.textContent = "No facility selected.";
    return;
  }

  try {
    const response = await fetch(
      `includes/backend-api/load_courts.php?facility_id=${facilityId}`,
    );
    const data = await response.json();

    if (!data.success) {
      alert(data.message || "Failed to load courts.");
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

      const bookBtn = document.createElement("button");
      bookBtn.type = "button";
      bookBtn.className = "book-btn";
      bookBtn.textContent = "Book now";
      bookBtn.dataset.courtId = court.court_id;

      item.append(name, rate, desc, bookBtn);
      grid.append(item);
    }
  } catch (error) {
    console.error("Error loading courts:", error);
  }
}

function getScheduleId() {
  const params = new URLSearchParams(window.location.search);
  return params.get("schedule_id");
}

// open modal for booking
//check if user is logged in first
//then move to login/register first
document.addEventListener("click", async (e) => {
  if (e.target.classList.contains("book-btn")) {
    const bookForm = document.getElementById("bookForm");
    const modalContainer = document.getElementById("modal_container");
    const scheduleId = getScheduleId();
    bookForm.reset();
    bookForm.dataset.scheduleId = scheduleId
    modal_container.classList.add("show");
  }
});

// save button
document.addEventListener("click", async (e) => {
  if (e.target.classList.contains("save-btn")) {
    const form = document.getElementById("bookForm");
    const courtId = form.dataset.courtId;

    const payload = {
  

    };

    const res = await fetch("includes/backend-api/load_schedules.php", {
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

// cancel
document.addEventListener("click", (e) => {
  if (e.target.classList.contains("cancel-btn")) {
    modal_container.classList.remove("show");
  }
});

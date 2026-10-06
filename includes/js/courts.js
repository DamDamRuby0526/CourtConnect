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

      const image = document.createElement("img");
      image.className = "court-preview";
      image.src = court.court_img
        ? `uploads/court/${encodeURIComponent(court.court_img)}`
        : "uploads/badminton-pickleball-court.svg";
      image.alt = court.court_img
        ? `Court ${court.court_no}`
        : "CourtConnect court";

      const name = document.createElement("p");
      name.textContent = `Court Number: ${court.court_no}`;

      const rate = document.createElement("p");
      rate.textContent = `Rate: ₱${court.court_rate} / hour`;

      const desc = document.createElement("p");
      desc.textContent = `Description: ${court.description}`;

      const bookBtn = document.createElement("button");
      bookBtn.type = "button";
      bookBtn.className = "book-btn";
      bookBtn.textContent = "Book now";
      bookBtn.dataset.courtId = court.court_id;

      item.append(image, name, rate, desc, bookBtn);
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

// open modal and load schedules for the clicked court
document.addEventListener("click", async (e) => {
  if (e.target.classList.contains("book-btn")) {
    const courtId = e.target.dataset.courtId;
    const courtNo = e.target.dataset.courtNo;
    selectedCourtRate = parseFloat(e.target.dataset.courtRate);

    document.getElementById("bookForm").dataset.courtId = courtId;
    document.getElementById("booking_court_id").value = courtId;
    document.getElementById("booking-total").textContent = "";

    const select = document.getElementById("scheduleSelect");
    select.innerHTML =
      '<option value="" disabled selected>Loading schedules...</option>';

    modal_container.classList.add("show");

    try {
      const response = await fetch(
        `includes/backend-api/load_schedules.php?court_id=${courtId}`,
      );
      const data = await response.json();

      if (!data.success) {
        select.innerHTML =
          '<option value="" disabled selected>Unable to load schedules</option>';
        return;
      }

      if (data.schedules.length === 0) {
        select.innerHTML =
          '<option value="" disabled selected>No available slots</option>';
        return;
      }

      select.innerHTML =
        '<option value="" disabled selected>Select a slot</option>';

      for (const schedule of data.schedules) {
        const option = document.createElement("option");
        option.value = schedule.schedule_id;
        option.textContent = `${schedule.court_date} — ${schedule.court_time}`;
        select.append(option);
      }
    } catch (error) {
      console.error("Error loading schedules:", error);
      select.innerHTML =
        '<option value="" disabled selected>Unable to load schedules</option>';
    }
  }
});

// show the running total as the user picks a slot
document.addEventListener("change", (e) => {
  if (e.target.id === "scheduleSelect") {
    document.getElementById("booking-total").textContent = selectedCourtRate
      ? `Total: ₱${selectedCourtRate.toFixed(2)}`
      : "";
  }
});

// cancel button
document.addEventListener("click", (e) => {
  if (e.target.classList.contains("cancel-btn")) {
    modal_container.classList.remove("show");
  }
});

// save button — submit the booking
document.addEventListener("click", async (e) => {
  if (e.target.classList.contains("save-btn")) {
    const form = document.getElementById("bookForm");
    const courtId = form.dataset.courtId;
    const scheduleId = document.getElementById("scheduleSelect").value;

    if (!scheduleId) {
      alert("Please select a time slot.");
      return;
    }

    const payload = {
      court_id: courtId,
      schedule_id: scheduleId,
    };

    try {
      const response = await fetch("includes/backend-api/make_booking.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload),
      });

      const result = await response.json();

      if (!result.success) {
        alert(result.message || "Booking failed.");
        return;
      }

      alert("Booking confirmed!");
      modal_container.classList.remove("show");
    } catch (error) {
      console.error("Error creating booking:", error);
      alert("Something went wrong. Please try again.");
    }
  }
});

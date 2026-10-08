const dashboardCards = [...document.querySelectorAll("[data-dashboard-panel]")];
const manualBookingModal = document.getElementById("manualBookingModal");
const manualBookingForm = document.getElementById("manualBookingForm");
const availableSlotsList = document.getElementById("availableSlotsList");
const availableSlotsStatus = document.getElementById("availableSlotsStatus");
const availableSlotCount = document.getElementById("availableSlotCount");
const manualCustomerSelect = document.getElementById("manualCustomerId");
let availableSlots = [];
let customers = [];
let slotsLoaded = false;

document.getElementById("refreshBookings").addEventListener("click", () => location.reload());

function setManualBookingOpen(open) {
  manualBookingModal.classList.toggle("show", open);
  manualBookingModal.setAttribute("aria-hidden", String(!open));
  if (open) manualCustomerSelect.focus();
}

function formatSlotDate(dateString) {
  const [year, month, day] = dateString.split("-").map(Number);
  return new Date(year, month - 1, day).toLocaleDateString(undefined, {
    weekday: "short",
    month: "short",
    day: "numeric",
    year: "numeric",
  });
}

function formatSlotTime(timeString) {
  const [hours, minutes] = timeString.split(":").map(Number);
  return new Date(2000, 0, 1, hours, minutes).toLocaleTimeString(undefined, {
    hour: "numeric",
    minute: "2-digit",
  });
}

function makeTextElement(tagName, className, text) {
  const element = document.createElement(tagName);
  element.className = className;
  element.textContent = text;
  return element;
}

function populateCustomers() {
  for (const customer of customers) {
    const option = document.createElement("option");
    option.value = customer.user_id;
    option.textContent = `${customer.first_name} ${customer.last_name} (${customer.email})`;
    manualCustomerSelect.append(option);
  }
}

function renderAvailableSlots() {
  availableSlotsList.replaceChildren();
  availableSlotCount.textContent = `${availableSlots.length} ${availableSlots.length === 1 ? "time" : "times"}`;

  if (availableSlots.length === 0) {
    availableSlotsStatus.textContent = "No upcoming available court times.";
    availableSlotsStatus.hidden = false;
    return;
  }

  availableSlotsStatus.hidden = true;
  for (const slot of availableSlots) {
    const row = document.createElement("article");
    row.className = "dashboard-available-row";
    const details = document.createElement("div");
    details.className = "dashboard-available-details";
    details.append(
      makeTextElement("strong", "", `Court ${slot.court_no}`),
      makeTextElement("span", "", `${formatSlotDate(slot.court_date)} · ${formatSlotTime(slot.court_time)}`),
      makeTextElement("small", "", `${slot.slot_duration} min · ₱${Number(slot.court_rate).toFixed(2)}`),
    );

    const bookButton = document.createElement("button");
    bookButton.type = "button";
    bookButton.className = "admin-book-slot-button";
    bookButton.dataset.scheduleId = slot.schedule_id;
    bookButton.textContent = "Book slot";
    row.append(details, bookButton);
    availableSlotsList.append(row);
  }
}

async function loadAvailableSlots() {
  availableSlotsStatus.hidden = false;
  availableSlotsStatus.textContent = "Loading available court times...";
  try {
    const response = await fetch("../includes/admin-php/dashboard_reservations.php");
    const result = await response.json();
    if (!result.success) throw new Error(result.message || "Unable to load available court times.");
    availableSlots = result.slots;
    customers = result.customers;
    populateCustomers();
    slotsLoaded = true;
    renderAvailableSlots();
  } catch (error) {
    availableSlotsStatus.textContent = error.message || "Unable to load available court times.";
    availableSlotCount.textContent = "Unavailable";
  }
}

function openBookingForSlot(scheduleId) {
  const slot = availableSlots.find((item) => String(item.schedule_id) === scheduleId);
  if (!slot) return;
  document.getElementById("manualScheduleId").value = slot.schedule_id;
  document.getElementById("manualBookingSlot").textContent =
    `Court ${slot.court_no} · ${formatSlotDate(slot.court_date)} at ${formatSlotTime(slot.court_time)} · ₱${Number(slot.court_rate).toFixed(2)}`;
  document.getElementById("manualBookingStatus").textContent = "";
  document.getElementById("manualBookingStatus").classList.remove("is-error");
  manualBookingForm.reset();
  document.getElementById("manualScheduleId").value = slot.schedule_id;
  setManualBookingOpen(true);
}

dashboardCards.forEach((card) => {
  card.addEventListener("click", async () => {
    const panel = document.getElementById(card.dataset.dashboardPanel);
    const shouldOpen = panel.hidden;
    dashboardCards.forEach((item) => {
      item.setAttribute("aria-expanded", "false");
      document.getElementById(item.dataset.dashboardPanel).hidden = true;
    });
    if (shouldOpen) {
      panel.hidden = false;
      card.setAttribute("aria-expanded", "true");
      if (panel.id === "availableCourtsPanel" && !slotsLoaded) await loadAvailableSlots();
    }
  });
});

availableSlotsList.addEventListener("click", (event) => {
  const button = event.target.closest("[data-schedule-id]");
  if (button) openBookingForSlot(button.dataset.scheduleId);
});

document.getElementById("cancelManualBooking").addEventListener("click", () => setManualBookingOpen(false));
manualBookingModal.addEventListener("click", (event) => {
  if (event.target === manualBookingModal) setManualBookingOpen(false);
});
document.addEventListener("keydown", (event) => {
  if (event.key === "Escape" && manualBookingModal.classList.contains("show")) setManualBookingOpen(false);
});

manualBookingForm.addEventListener("submit", async (event) => {
  event.preventDefault();
  const submitButton = document.getElementById("submitManualBooking");
  const status = document.getElementById("manualBookingStatus");
  submitButton.disabled = true;
  status.classList.remove("is-error");
  status.textContent = "Creating booking...";
  try {
    const response = await fetch("../includes/admin-php/dashboard_reservations.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(Object.fromEntries(new FormData(manualBookingForm))),
    });
    const result = await response.json();
    if (!result.success) throw new Error(result.message || "Unable to create this booking.");
    status.textContent = result.message;
    window.location.reload();
  } catch (error) {
    status.textContent = error.message || "Unable to create this booking.";
    status.classList.add("is-error");
  } finally {
    submitButton.disabled = false;
  }
});
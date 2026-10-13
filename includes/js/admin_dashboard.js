const dashboardCards = [...document.querySelectorAll("[data-dashboard-panel]")];
const todayBookingsPanel = document.getElementById("todayBookingsPanel");
const todayBookingsList = document.getElementById("todayBookingsList");
const todayBookingCount = document.getElementById("todayBookingCount");
const todayBookingSummary = document.getElementById("todayBookingSummary");
const availableCourtsPanel = document.getElementById("availableCourtsPanel");
const manualBookingModal = document.getElementById("manualBookingModal");
const manualBookingForm = document.getElementById("manualBookingForm");
const arrivedConfirmationModal = document.getElementById("arrivedConfirmationModal");
const cancelBookingModal = document.getElementById("cancelBookingModal");
const availableSlotsList = document.getElementById("availableSlotsList");
const availableSlotsStatus = document.getElementById("availableSlotsStatus");
const availableSlotCount = document.getElementById("availableSlotCount");
const manualPaymentMethod = document.getElementById("manualPaymentMethod");
const manualGcashQrField = document.getElementById("manualGcashQrField");
const manualGcashQr = document.getElementById("manualGcashQr");
const manualGcashReferenceField = document.getElementById("manualGcashReferenceField");
const manualGcashReference = document.getElementById("manualGcashReference");
let availableSlots = [];
let todayBookings = window.initialTodayBookings || [];
let pendingAttendanceBookingId = null;
let pendingCancelBookingId = null;

function getBookingSlotStart(booking) {
  if (!booking || !booking.court_date || !booking.court_time) return null;
  const value = `${booking.court_date}T${booking.court_time}`;
  const timestamp = new Date(value.replace(" ", "T"));
  return Number.isNaN(timestamp.getTime()) ? null : timestamp;
}

function getBookingAvailabilityTooltip(booking) {
  const slotStart = getBookingSlotStart(booking);
  if (!slotStart) return "";
  return `Available from ${slotStart.toLocaleTimeString([], { hour: "numeric", minute: "2-digit" })}`;
}

function updateBookingAttendanceState(bookingId, attendanceState) {
  const booking = todayBookings.find((item) => String(item.booking_id) === String(bookingId));
  if (!booking) return;

  if (attendanceState === "arrived") {
    booking.arrived_at = new Date().toISOString();
    booking.did_not_arrive_at = null;
  } else if (attendanceState === "no_show") {
    booking.did_not_arrive_at = new Date().toISOString();
    booking.arrived_at = null;
  }

  renderTodayBookings();
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

function setActivePanel(panelId) {
  for (const card of dashboardCards) {
    const active = card.dataset.dashboardPanel === panelId;
    card.classList.toggle("is-active", active);
    card.setAttribute("aria-expanded", String(active));
    document.getElementById(card.dataset.dashboardPanel).hidden = !active;
  }
}

function renderTodayBookings() {
  todayBookingsList.replaceChildren();
  todayBookingCount.textContent = todayBookings.length;
  todayBookingSummary.textContent = `${todayBookings.length} ${todayBookings.length === 1 ? "booking" : "bookings"}`;

  if (todayBookings.length === 0) {
    const emptyState = document.createElement("div");
    emptyState.className = "dashboard-empty-state";
    emptyState.append(makeTextElement("p", "dashboard-empty", "No reservations yet today"));
    const action = makeTextElement("button", "admin-book-slot-button", "View available courts");
    action.type = "button";
    action.addEventListener("click", () => setActivePanel("availableCourtsPanel"));
    emptyState.append(action);
    todayBookingsList.append(emptyState);
    return;
  }

  const bookingsByCourt = new Map();
  for (const booking of todayBookings) {
    if (!bookingsByCourt.has(String(booking.court_no))) {
      bookingsByCourt.set(String(booking.court_no), []);
    }
    bookingsByCourt.get(String(booking.court_no)).push(booking);
  }

  for (const [courtNo, bookings] of bookingsByCourt) {
    const court = document.createElement("section");
    court.className = "dashboard-court-card";
    court.append(makeTextElement("h3", "dashboard-court-heading", `Court ${courtNo}`));
    const rows = document.createElement("div");
    rows.className = "dashboard-court-bookings";

    for (const booking of bookings) {
      const row = document.createElement("article");
      row.className = "dashboard-today-row";
      const time = makeTextElement("time", "dashboard-booking-time", formatSlotTime(booking.court_time));
      time.dateTime = `${booking.court_date}T${booking.court_time}`;
      const customer = document.createElement("div");
      customer.className = "dashboard-booking-customer";
      customer.append(
        makeTextElement("strong", "", booking.customer_name || "Customer"),
        makeTextElement("small", "", booking.display_phone || ""),
      );
      const badges = document.createElement("div");
      badges.className = "dashboard-booking-badges";
      const bookingType = booking.booking_type === "walk_in" ? "Walk-in" : "Online";
      badges.append(makeTextElement("span", `dashboard-badge booking-type-${booking.booking_type}`, bookingType));

      const isPaid = String(booking.payment_status).toLowerCase() === "paid";
      badges.append(makeTextElement("span", `dashboard-badge payment-${isPaid ? "paid" : "pending"}`, isPaid ? "Paid" : "Pending"));

      const attendance = booking.did_not_arrive_at
        ? { label: "Did not arrive", className: "attendance-status attendance-status-no-show" }
        : booking.arrived_at
          ? { label: "Arrived", className: "attendance-status attendance-status-arrived" }
          : { label: "Not yet", className: "attendance-status attendance-status-not-recorded" };
      badges.append(makeTextElement("span", `dashboard-badge ${attendance.className}`, attendance.label));

      const amount = makeTextElement("strong", "dashboard-booking-amount", `₱${Number(booking.total_amount).toFixed(2)}`);
      const actions = document.createElement("div");
      actions.className = "dashboard-booking-actions";
      if (!booking.arrived_at && !booking.did_not_arrive_at) {
        const arrived = makeTextElement("button", "dashboard-arrived-button", "Mark arrived");
        arrived.type = "button";
        arrived.dataset.bookingId = booking.booking_id;
        const slotStart = getBookingSlotStart(booking);
        if (slotStart && slotStart > new Date()) {
          arrived.title = getBookingAvailabilityTooltip(booking);
        }
        actions.append(arrived);
      }
      const cancel = makeTextElement("button", "dashboard-cancel-button", "Cancel");
      cancel.type = "button";
      cancel.dataset.bookingId = booking.booking_id;
      const slotStart = getBookingSlotStart(booking);
      if (slotStart && slotStart > new Date()) {
        cancel.title = getBookingAvailabilityTooltip(booking);
      }
      actions.append(cancel);

      row.append(time, customer, badges, amount, actions);
      rows.append(row);
    }
    court.append(rows);
    todayBookingsList.append(court);
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
  const slotsByCourt = new Map();
  for (const slot of availableSlots) {
    const courtNo = String(slot.court_no);
    if (!slotsByCourt.has(courtNo)) slotsByCourt.set(courtNo, new Map());
    const slotsByDate = slotsByCourt.get(courtNo);
    if (!slotsByDate.has(slot.court_date)) slotsByDate.set(slot.court_date, []);
    slotsByDate.get(slot.court_date).push(slot);
  }

  for (const [courtNo, slots] of slotsByCourt) {
    const court = document.createElement("section");
    court.className = "dashboard-available-court";
    const heading = document.createElement("header");
    heading.className = "dashboard-available-court-heading";
    heading.append(
      makeTextElement("h3", "", `Court ${courtNo}`),
      makeTextElement("span", "", `${[...slots.values()].reduce((total, dateSlots) => total + dateSlots.length, 0)} available`),
    );
    court.append(heading);

    for (const [courtDate, dateSlots] of slots) {
      const day = document.createElement("section");
      day.className = "dashboard-available-day";
      day.append(makeTextElement("h4", "", formatSlotDate(courtDate)));
      const list = document.createElement("div");
      list.className = "dashboard-available-slot-grid";
      for (const slot of dateSlots) {
        const card = document.createElement("article");
        card.className = "dashboard-available-slot";
        const time = makeTextElement("strong", "dashboard-available-slot-time", formatSlotTime(slot.court_time));
        const meta = document.createElement("div");
        meta.className = "dashboard-available-slot-meta";
        meta.append(
          makeTextElement("span", "", `${slot.slot_duration} min`),
          makeTextElement("span", "", `₱${Number(slot.court_rate).toFixed(2)}`),
        );
        const bookButton = makeTextElement("button", "admin-book-slot-button", "Book slot");
        bookButton.type = "button";
        bookButton.dataset.scheduleId = slot.schedule_id;
        card.append(time, meta, bookButton);
        list.append(card);
      }
      day.append(list);
      court.append(day);
    }
    availableSlotsList.append(court);
  }
}

function updateManualPaymentFields() {
  const needsQrImage = manualPaymentMethod.value === "Gcash";
  manualGcashQrField.hidden = !needsQrImage;
  manualGcashReferenceField.hidden = !needsQrImage;
  manualGcashQr.required = needsQrImage;
  manualGcashReference.required = needsQrImage;
  if (!needsQrImage) {
    manualGcashQr.value = "";
    manualGcashReference.value = "";
  }
}

async function loadDashboardData() {
  availableSlotsStatus.hidden = false;
  availableSlotsStatus.textContent = "Loading available court times...";

  try {
    const response = await fetch("../includes/admin-php/dashboard_reservations.php");
    const result = await response.json();
    if (!response.ok || !result.success) throw new Error(result.message || "Unable to load reservations.");
    availableSlots = result.slots;
    todayBookings = result.today_bookings;
    document.getElementById("availableCourtCount").textContent = result.available_court_count;
    renderTodayBookings();
    renderAvailableSlots();
  } catch (error) {
    console.error(error);
    availableSlotsStatus.hidden = false;
    availableSlotsStatus.textContent = "Unable to load available court times.";
    availableSlotCount.textContent = "Unavailable";
    window.showDialog({
      type: "error",
      title: "Unable to load reservations",
      message: error.message || "Unable to load reservations.",
    });
  }
}

function setModalOpen(modal, open) {
  modal.classList.toggle("show", open);
  modal.setAttribute("aria-hidden", String(!open));
}

function openBookingForSlot(scheduleId) {
  const slot = availableSlots.find((item) => String(item.schedule_id) === scheduleId);
  if (!slot) return;
  manualBookingForm.reset();
  updateManualPaymentFields();
  document.getElementById("manualScheduleId").value = slot.schedule_id;
  document.getElementById("manualBookingSlot").textContent =
    `Court ${slot.court_no} · ${formatSlotDate(slot.court_date)} at ${formatSlotTime(slot.court_time)} · ₱${Number(slot.court_rate).toFixed(2)}`;
  setModalOpen(manualBookingModal, true);
  document.getElementById("manualCustomerName").focus();
}

function closeAttendanceModal() {
  setModalOpen(arrivedConfirmationModal, false);
  pendingAttendanceBookingId = null;
}

function closeCancelModal() {
  setModalOpen(cancelBookingModal, false);
  pendingCancelBookingId = null;
}

dashboardCards.forEach((card) => {
  card.addEventListener("click", () => setActivePanel(card.dataset.dashboardPanel));
});

todayBookingsList.addEventListener("click", (event) => {
  const target = event.target;
  if (!(target instanceof Element)) return;
  const arrivedButton = target.closest(".dashboard-arrived-button");
  if (arrivedButton) {
    pendingAttendanceBookingId = arrivedButton.dataset.bookingId;
    setModalOpen(arrivedConfirmationModal, true);
    return;
  }
  const cancelButton = target.closest(".dashboard-cancel-button");
  if (cancelButton) {
    pendingCancelBookingId = cancelButton.dataset.bookingId;
    setModalOpen(cancelBookingModal, true);
  }
});

availableSlotsList.addEventListener("click", (event) => {
  const target = event.target;
  if (!(target instanceof Element)) return;
  const button = target.closest("[data-schedule-id]");
  if (button) openBookingForSlot(button.dataset.scheduleId);
});

async function postBookingAction(endpoint, data) {
  const response = await fetch(endpoint, {
    method: "POST",
    headers: { "Content-Type": "application/x-www-form-urlencoded" },
    body: new URLSearchParams(data),
  });
  const result = await response.json();
  if (!response.ok || !result.success) {
    const error = new Error(result.message || "Unable to update this booking.");
    error.code = result.code;
    error.details = result;
    throw error;
  }
  return result;
}

async function handleAttendanceSubmission(attendance) {
  const bookingId = pendingAttendanceBookingId;
  if (!bookingId) return;

  const confirmButton = attendance === "arrived"
    ? document.getElementById("confirmArrivedConfirmation")
    : document.getElementById("markNoShowConfirmation");
  const cancelButton = document.getElementById("cancelArrivedConfirmation");

  if (confirmButton) confirmButton.disabled = true;
  if (cancelButton) cancelButton.disabled = true;

  try {
    const result = await postBookingAction("../includes/admin-php/mark_booking_arrived.php", {
      booking_id: bookingId,
      attendance,
    });

    closeAttendanceModal();
    await window.showDialog({
      type: "success",
      title: "Booking updated",
      message: result.message,
    });
    await loadDashboardData();
  } catch (error) {
    closeAttendanceModal();

    if (error.code === "already_recorded") {
      updateBookingAttendanceState(bookingId, error.details?.attendance_state || attendance);
      await window.showDialog({
        type: "success",
        title: "Attendance recorded",
        message: error.message || "Attendance was already recorded for this booking.",
      });
      return;
    }

    await window.showDialog({
      type: "error",
      title: "Unable to update booking",
      message: error.message || "Unable to update this booking.",
    });
  } finally {
    if (confirmButton) confirmButton.disabled = false;
    if (cancelButton) cancelButton.disabled = false;
    pendingAttendanceBookingId = null;
  }
}

document.getElementById("confirmArrivedConfirmation").addEventListener("click", async () => {
  await handleAttendanceSubmission("arrived");
});

document.getElementById("cancelArrivedConfirmation").addEventListener("click", closeAttendanceModal);
arrivedConfirmationModal.addEventListener("click", (event) => {
  if (event.target === arrivedConfirmationModal) closeAttendanceModal();
});
document.getElementById("markNoShowConfirmation").addEventListener("click", async () => {
  await handleAttendanceSubmission("no_show");
});

document.getElementById("dismissCancelBooking").addEventListener("click", closeCancelModal);
cancelBookingModal.addEventListener("click", (event) => {
  if (event.target === cancelBookingModal) closeCancelModal();
});
document.getElementById("confirmCancelBooking").addEventListener("click", async () => {
  const bookingId = pendingCancelBookingId;
  if (!bookingId) return;
  const button = document.getElementById("confirmCancelBooking");
  button.disabled = true;
  try {
    const result = await postBookingAction("../includes/admin-php/cancel_dashboard_booking.php", {
      booking_id: bookingId,
    });
    closeCancelModal();
    await window.showDialog({ type: "success", title: "Booking cancelled", message: result.message || "Reservation cancelled." });
    await loadDashboardData();
  } catch (error) {
    await window.showDialog({ type: "error", title: "Unable to cancel booking", message: error.message || "Unable to cancel this booking." });
  } finally {
    button.disabled = false;
  }
});

manualBookingForm.addEventListener("submit", async (event) => {
  event.preventDefault();
  if (!manualBookingForm.reportValidity()) return;
  const submitButton = document.getElementById("submitManualBooking");
  const customerName = document.getElementById("manualCustomerName").value.trim();
  const slot = availableSlots.find((item) => String(item.schedule_id) === document.getElementById("manualScheduleId").value);
  submitButton.disabled = true;

  try {
    const response = await fetch("../includes/admin-php/dashboard_reservations.php", {
      method: "POST",
      body: new FormData(manualBookingForm),
    });
    const result = await response.json();
    if (!response.ok || !result.success) throw new Error(result.message || "Unable to create this booking.");
    setModalOpen(manualBookingModal, false);
    const bookedTime = result.court_time || (slot && slot.court_time);
    await window.showDialog({ type: "success", title: "Booking confirmed", message: `${customerName} is booked for ${formatSlotTime(bookedTime)}.` });
    await loadDashboardData();
  } catch (error) {
    await window.showDialog({ type: "error", title: "Unable to create booking", message: error.message || "Unable to create this booking." });
    await loadDashboardData();
  } finally {
    submitButton.disabled = false;
  }
});

document.getElementById("cancelManualBooking").addEventListener("click", () => setModalOpen(manualBookingModal, false));
manualPaymentMethod.addEventListener("change", updateManualPaymentFields);
manualBookingModal.addEventListener("click", (event) => {
  if (event.target === manualBookingModal) setModalOpen(manualBookingModal, false);
});
document.addEventListener("keydown", (event) => {
  if (event.key !== "Escape") return;
  if (manualBookingModal.classList.contains("show")) setModalOpen(manualBookingModal, false);
  if (arrivedConfirmationModal.classList.contains("show")) closeAttendanceModal();
  if (cancelBookingModal.classList.contains("show")) closeCancelModal();
});

document.getElementById("refreshBookings").addEventListener("click", loadDashboardData);

renderTodayBookings();
setActivePanel("todayBookingsPanel");
loadDashboardData();

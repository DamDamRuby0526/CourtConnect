const ALLOW_MULTIPLE_SLOTS = false;

function formatSlotTime(value) {
  const [hoursValue, minutes] = value.split(":").map(Number);
  const suffix = hoursValue >= 12 ? "PM" : "AM";
  const hours = hoursValue % 12 || 12;
  return `${hours}:${String(minutes).padStart(2, "0")} ${suffix}`;
}

function formatDate(value) {
  return new Date(`${value}T12:00:00`).toLocaleDateString(undefined, {
    weekday: "short",
    month: "short",
    day: "numeric",
    year: "numeric",
  });
}

function showCourtToast(title, message, type = "success") {
  if (typeof window.showToast === "function") window.showToast(title, message, type);
}

async function loadCourtSlots(card, date) {
  const courtId = card.querySelector(".court-book-button").dataset.courtId;
  const slotList = card.querySelector(".court-slot-list");
  const emptyMessage = card.querySelector(".court-slot-empty");
  const nextDateButton = card.querySelector(".court-next-date");
  const loadingMessage = card.querySelector(".court-slot-loading");
  const continueButton = card.querySelector(".court-continue-button");
  const summary = card.querySelector(".court-booking-summary");
  slotList.replaceChildren();
  emptyMessage.hidden = true;
  nextDateButton.hidden = true;
  loadingMessage.hidden = false;
  continueButton.disabled = true;
  card.selectedSchedule = null;
  summary.querySelector(".summary-date").textContent = "Choose a date";
  summary.querySelector(".summary-time").textContent = "Choose a time";
  summary.querySelector(".summary-total").textContent = "P0";

  try {
    const response = await fetch(`includes/backend-api/load_schedules.php?court_id=${encodeURIComponent(courtId)}&date=${encodeURIComponent(date)}`);
    const result = await response.json();
    if (!response.ok || !result.success) throw new Error(result.message || "Unable to load times for this date.");

    for (const schedule of result.schedules) {
      const chip = document.createElement("button");
      chip.type = "button";
      chip.className = `court-slot-chip ${schedule.schedule_status === "Available" ? "is-available" : "is-unavailable"}`;
      chip.textContent = formatSlotTime(schedule.court_time);
      chip.disabled = schedule.schedule_status !== "Available";
      chip.setAttribute("aria-label", `${formatSlotTime(schedule.court_time)}${chip.disabled ? ", unavailable" : ", available"}`);

      if (!chip.disabled) {
        chip.addEventListener("click", () => {
          if (!ALLOW_MULTIPLE_SLOTS) {
            slotList.querySelectorAll(".court-slot-chip.is-selected").forEach((selectedChip) => {
              selectedChip.classList.remove("is-selected");
            });
            card.selectedSchedule = schedule;
            chip.classList.add("is-selected");
          }
          const rate = Number(continueButton.dataset.courtRate);
          const duration = Number(continueButton.dataset.slotDuration);
          summary.querySelector(".summary-date").textContent = formatDate(schedule.court_date);
          summary.querySelector(".summary-time").textContent = formatSlotTime(schedule.court_time);
          summary.querySelector(".summary-duration").textContent = `${duration} min`;
          summary.querySelector(".summary-total").textContent = `P${rate.toFixed(2)}`;
          continueButton.disabled = false;
        });
      }
      slotList.append(chip);
    }
    const hasAvailableSlot = result.schedules.some((schedule) => schedule.schedule_status === "Available");
    emptyMessage.hidden = hasAvailableSlot;
    if (!hasAvailableSlot && result.next_available_date) {
      nextDateButton.dataset.date = result.next_available_date;
      nextDateButton.textContent = `See next available: ${formatDate(result.next_available_date)}`;
      nextDateButton.hidden = false;
    }
  } catch (error) {
    showCourtToast("Unable to load times", error.message || "Please try again.", "error");
    emptyMessage.hidden = false;
    emptyMessage.querySelector("span").textContent = "Unable to load times for this date.";
    nextDateButton.hidden = true;
  } finally {
    loadingMessage.hidden = true;
  }
}

document.addEventListener("DOMContentLoaded", () => {
  document.querySelectorAll(".court-card-image").forEach((image) => {
    const fallback = image.parentElement.querySelector(".court-image-fallback");
    const showFallback = () => {
      image.hidden = true;
    };
    image.addEventListener("load", () => {
      image.classList.remove("is-loading");
      if (fallback) fallback.hidden = true;
    }, { once: true });
    image.addEventListener("error", showFallback, { once: true });
    if (image.complete) {
      if (image.naturalWidth === 0) showFallback();
      else {
        image.classList.remove("is-loading");
        if (fallback) fallback.hidden = true;
      }
    }
  });

  document.querySelectorAll(".court-card").forEach((card) => {
    const openButton = card.querySelector(".court-book-button");
    if (!openButton) return;

    const panel = card.querySelector(".court-booking-panel");
    const dateInput = card.querySelector(".court-date-input");
    const continueButton = card.querySelector(".court-continue-button");
    const nextDateButton = card.querySelector(".court-next-date");
    openButton.addEventListener("click", async () => {
      panel.hidden = !panel.hidden;
      const isOpen = !panel.hidden;
      openButton.textContent = isOpen ? "Close times" : "Book now";
      openButton.classList.toggle("player-primary-button", !isOpen);
      openButton.classList.toggle("player-secondary-link", isOpen);
      if (!panel.hidden) await loadCourtSlots(card, dateInput.value);
    });

    dateInput.addEventListener("change", () => loadCourtSlots(card, dateInput.value));
    nextDateButton.addEventListener("click", () => {
      dateInput.value = nextDateButton.dataset.date;
      loadCourtSlots(card, dateInput.value);
    });
    continueButton.addEventListener("click", async () => {
      if (!card.selectedSchedule) return;
      if (document.querySelector(".courts-page").dataset.loggedIn !== "true") {
        const returnTo = `${window.location.pathname}${window.location.search}#court-${openButton.dataset.courtId}`;
        window.location.href = `signin.php?return_to=${encodeURIComponent(returnTo)}`;
        return;
      }

      continueButton.disabled = true;
      try {
        const response = await fetch("includes/backend-api/make_booking.php", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ schedule_id: card.selectedSchedule.schedule_id }),
        });
        const result = await response.json();
        if (!response.ok || !result.success) throw new Error(result.message || "That slot was just taken.");
        window.location.href = `checkout.php?booking_id=${encodeURIComponent(result.booking_id)}`;
      } catch (error) {
        showCourtToast("Booking unavailable", error.message || "That slot was just taken.", "error");
        await loadCourtSlots(card, dateInput.value);
      }
    });
  });
});
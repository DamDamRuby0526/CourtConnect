function courtFormatTime(value) {
  const [hoursValue, minutes] = value.split(":").map(Number);
  const suffix = hoursValue >= 12 ? "PM" : "AM";
  const hours = hoursValue % 12 || 12;
  return `${hours}:${String(minutes).padStart(2, "0")} ${suffix}`;
}

function courtFormatDate(value, options = { weekday: "short", month: "short", day: "numeric", year: "numeric" }) {
  return new Date(`${value}T12:00:00`).toLocaleDateString(undefined, options);
}

function courtCurrency(value) {
  return new Intl.NumberFormat("en-PH", {
    style: "currency",
    currency: "PHP",
    maximumFractionDigits: 0,
  }).format(value);
}

function courtTimeStamp(value) {
  const [hours, minutes, seconds = "00"] = value.split(":");
  return Number(hours) * 3600 + Number(minutes) * 60 + Number(seconds);
}

function courtFormatHours(minutes) {
  const hours = minutes / 60;
  return `${Number.isInteger(hours) ? hours : Number(hours.toFixed(2))} ${hours === 1 ? "hour" : "hours"}`;
}

document.addEventListener("DOMContentLoaded", () => {
  const page = document.querySelector(".courts-page");
  const cards = Array.from(document.querySelectorAll(".court-modern-card"));
  const dateInput = document.getElementById("courtDateInput");
  const datePills = Array.from(document.querySelectorAll(".court-date-pill"));
  const priceFilter = document.getElementById("courtPriceFilter");
  const availabilityFilter = document.getElementById("courtAvailabilityFilter");
  const scheduleGrid = document.querySelector(".court-schedule-grid");
  const scheduleScroll = document.querySelector(".court-schedule-scroll");
  const scheduleLoading = document.querySelector(".court-schedule-loading");
  const scheduleEmpty = document.querySelector(".court-schedule-empty");
  const scheduleDateLabel = document.querySelector(".court-schedule-date");
  const checkoutBar = document.getElementById("courtCheckoutBar");
  const checkoutTitle = document.getElementById("courtCheckoutTitle");
  const checkoutDetails = document.getElementById("courtCheckoutDetails");
  const checkoutBreakdown = document.getElementById("courtCheckoutBreakdown");
  const checkoutTotal = document.getElementById("courtCheckoutTotal");
  const checkoutButton = document.getElementById("courtProceedButton");
  const imageLightbox = document.getElementById("courtImageLightbox");
  const imageLightboxImage = document.getElementById("courtImageLightboxImage");
  const imageLightboxClose = document.getElementById("courtImageLightboxClose");
  const scheduleCache = new Map();
  let selection = null;
  let loadToken = 0;

  if (!page || !dateInput || !cards.length) return;

  const scheduleForDate = async (court, date) => {
    const cacheKey = `${court.dataset.courtId}:${date}`;
    if (scheduleCache.has(cacheKey)) return scheduleCache.get(cacheKey);
    const request = fetch(`includes/backend-api/load_schedules.php?court_id=${encodeURIComponent(court.dataset.courtId)}&date=${encodeURIComponent(date)}`)
      .then(async (response) => {
        const result = await response.json();
        if (!response.ok || !result.success) throw new Error(result.message || "Unable to load availability.");
        return result.schedules || [];
      })
      .catch((error) => {
        scheduleCache.delete(cacheKey);
        throw error;
      });
    scheduleCache.set(cacheKey, request);
    return request;
  };

  const clearSelection = () => {
    selection = null;
    page.classList.remove("has-selection");
    document.querySelectorAll(".court-time-slot.is-selected").forEach((slot) => {
      slot.classList.remove("is-selected");
      slot.classList.remove("is-range-start", "is-range-middle", "is-range-end");
      slot.setAttribute("aria-pressed", "false");
    });
    hideCheckoutBar();
    checkoutButton.disabled = true;
  };

  const updateSummary = () => {
    if (!selection) {
      page.classList.remove("has-selection");
      hideCheckoutBar();
      checkoutButton.disabled = true;
      return;
    }
    page.classList.add("has-selection");
    const firstSlot = selection.schedules[0];
    const lastSlot = selection.schedules[selection.schedules.length - 1];
    const slotDuration = Number(selection.court.dataset.slotDuration);
    const lastEnd = courtFormatTime(new Date(Date.UTC(
      2000, 0, 1,
      Number(lastSlot.court_time.slice(0, 2)),
      Number(lastSlot.court_time.slice(3, 5)) + slotDuration,
    )).toISOString().slice(11, 16) + ":00");
    const durationMinutes = slotDuration * selection.schedules.length;
    const amount = Number(selection.court.dataset.courtRate) * selection.schedules.length;
    checkoutTitle.textContent = `Court ${selection.court.dataset.courtNo} · ${courtFormatHours(durationMinutes)}`;
    const now = new Date();
    const localToday = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, "0")}-${String(now.getDate()).padStart(2, "0")}`;
    const isToday = firstSlot.court_date === localToday;
    const selectedDate = courtFormatDate(firstSlot.court_date, { month: "short", day: "numeric" });
    checkoutDetails.textContent = `${isToday ? "Today, " : ""}${selectedDate} · ${courtFormatTime(firstSlot.court_time)}–${lastEnd}`;
    checkoutBreakdown.textContent = `${selection.schedules.length} ${selection.schedules.length === 1 ? "hour" : "hours"} × ${courtCurrency(Number(selection.court.dataset.courtRate))}`;
    checkoutTotal.textContent = courtCurrency(amount);
    showCheckoutBar();
    checkoutButton.disabled = false;
  };

  let checkoutRevealFrame = 0;
  let checkoutHideTimer = 0;
  const showCheckoutBar = () => {
    window.clearTimeout(checkoutHideTimer);
    window.cancelAnimationFrame(checkoutRevealFrame);
    checkoutBar.hidden = false;
    checkoutBar.setAttribute("aria-hidden", "false");
    checkoutRevealFrame = window.requestAnimationFrame(() => checkoutBar.classList.add("is-visible"));
  };

  const hideCheckoutBar = () => {
    window.clearTimeout(checkoutHideTimer);
    window.cancelAnimationFrame(checkoutRevealFrame);
    checkoutBar.classList.remove("is-visible");
    checkoutBar.setAttribute("aria-hidden", "true");
    checkoutHideTimer = window.setTimeout(() => {
      if (!selection) checkoutBar.hidden = true;
    }, 220);
  };

  const getConsecutiveSlots = (court, schedules, startIndex) => {
    const slotMinutes = Number(court.dataset.slotDuration);
    const chosen = schedules.slice(startIndex, startIndex + 1);
    return chosen.length === 1 && chosen[0].schedule_status === "Available" && slotMinutes > 0
      ? chosen
      : null;
  };

  const paintSelection = () => {
    document.querySelectorAll(".court-time-slot.is-selected").forEach((slot) => {
      slot.classList.remove("is-selected");
      slot.classList.remove("is-range-start", "is-range-middle", "is-range-end");
      slot.setAttribute("aria-pressed", "false");
    });
    selection.schedules.forEach((schedule, index) => {
      const selectedButton = document.querySelector(`[data-schedule-id="${CSS.escape(String(schedule.schedule_id))}"]`);
      if (selectedButton) {
        selectedButton.classList.add("is-selected");
        if (selection.schedules.length === 1) {
          selectedButton.classList.add("is-range-start", "is-range-end");
        } else if (index === 0) {
          selectedButton.classList.add("is-range-start");
        } else if (index === selection.schedules.length - 1) {
          selectedButton.classList.add("is-range-end");
        } else {
          selectedButton.classList.add("is-range-middle");
        }
        selectedButton.setAttribute("aria-pressed", "true");
        selectedButton.setAttribute("aria-label", `Court ${selection.court.dataset.courtNo}, ${courtFormatTime(schedule.court_time)}, selected`);
      }
    });
    updateSummary();
  };

  const chooseStartTime = (court, schedules, startIndex) => {
    if (court.dataset.courtStatus !== "Available") return;
    if (selection && selection.court === court) {
      const firstSelectedId = Number(selection.schedules[0].schedule_id);
      const lastSelectedId = Number(selection.schedules[selection.schedules.length - 1].schedule_id);
      const firstIndex = schedules.findIndex((slot) => Number(slot.schedule_id) === firstSelectedId);
      const lastIndex = schedules.findIndex((slot) => Number(slot.schedule_id) === lastSelectedId);
      if (startIndex >= firstIndex && startIndex <= lastIndex) {
        if (firstIndex === lastIndex) {
          clearSelection();
        } else if (startIndex === firstIndex) {
          selection = { court, schedules: schedules.slice(firstIndex + 1, lastIndex + 1) };
          paintSelection();
        } else if (startIndex === lastIndex) {
          selection = { court, schedules: schedules.slice(firstIndex, lastIndex) };
          paintSelection();
        } else {
          if (typeof window.showToast === "function") {
            window.showToast("Keep the reservation continuous", "Remove slots from either end so the selected hours stay together.", "info");
          }
        }
        return;
      }
      if (startIndex === firstIndex - 1 || startIndex === lastIndex + 1) {
        const rangeStart = Math.min(firstIndex, startIndex);
        const rangeEnd = Math.max(lastIndex, startIndex);
        const extendedSlots = schedules.slice(rangeStart, rangeEnd + 1);
        const continuous = extendedSlots.every((slot) => slot.schedule_status === "Available")
          && extendedSlots.every((slot, index) => index === 0
            || courtTimeStamp(slot.court_time) === courtTimeStamp(extendedSlots[index - 1].court_time)
              + Number(court.dataset.slotDuration) * 60);
        if (continuous && extendedSlots.length > 8) {
          if (typeof window.showToast === "function") {
            window.showToast(
              "Maximum booking duration reached",
              "You can book up to 8 consecutive hourly slots per reservation.",
              "info",
              { position: "booking", dedupeKey: "maximum-booking-duration" },
            );
          }
          return;
        }
        if (continuous) {
          selection = { court, schedules: extendedSlots };
          paintSelection();
          return;
        }
      }
    }
    const chosenSlots = getConsecutiveSlots(court, schedules, startIndex);
    if (!chosenSlots) {
      if (typeof window.showToast === "function") {
        window.showToast("Duration unavailable", "There are not enough consecutive available slots from this time.", "error");
      }
      return;
    }
    clearSelection();
    selection = { court, schedules: chosenSlots };
    paintSelection();
  };

  const openImageLightbox = (src, alt) => {
    if (!src) return;
    imageLightboxImage.src = src;
    imageLightboxImage.alt = alt;
    imageLightbox.hidden = false;
    imageLightbox.setAttribute("aria-hidden", "false");
    imageLightboxClose.focus();
  };

  const closeImageLightbox = () => {
    imageLightbox.hidden = true;
    imageLightbox.setAttribute("aria-hidden", "true");
    imageLightboxImage.src = "";
  };

  const buildScheduleGrid = (date) => {
    const visibleCourts = cards.filter((court) => !court.hidden);
    scheduleGrid.replaceChildren();
    if (!visibleCourts.length || !visibleCourts.some((court) => (court.schedules || []).length)) {
      scheduleScroll.hidden = true;
      scheduleEmpty.hidden = false;
      scheduleEmpty.textContent = "No schedules are listed for this date. Try another day.";
      return;
    }

    scheduleEmpty.hidden = true;
    scheduleScroll.hidden = false;

    visibleCourts.forEach((court) => {
      const schedules = (court.schedules || []).slice().sort((left, right) => left.court_time.localeCompare(right.court_time));
      const lane = document.createElement("section");
      lane.className = "court-lane";
      lane.setAttribute("aria-label", `Court ${court.dataset.courtNo} availability`);

      const rowHeader = document.createElement("div");
      rowHeader.className = "court-grid-row-header";
      const details = document.createElement("span");
      details.className = "court-grid-row-details";
      const name = document.createElement("strong");
      name.textContent = `Court ${court.dataset.courtNo}`;
      const rate = document.createElement("small");
      rate.className = "court-grid-rate";
      rate.textContent = `${courtCurrency(Number(court.dataset.courtRate))} / hour`;
      const status = document.createElement("small");
      status.className = court.dataset.courtStatus === "Maintenance" ? "is-maintenance" : "";
      status.textContent = court.dataset.courtStatus === "Maintenance"
        ? "Maintenance"
        : `${courtFormatHours(Number(court.dataset.slotDuration))} slots`;
      const description = document.createElement("small");
      description.className = "court-grid-surface";
      description.textContent = court.dataset.courtDescription || "Court details unavailable";
      details.append(name, description, rate, status);
      const image = court.querySelector(".court-card-image");
      const thumbnail = document.createElement("button");
      thumbnail.type = "button";
      thumbnail.className = "court-grid-thumbnail";
      thumbnail.setAttribute("aria-label", `View Court ${court.dataset.courtNo} photo`);
      const thumbnailFallback = document.createElement("span");
      thumbnailFallback.className = "court-grid-thumbnail-fallback";
      thumbnailFallback.textContent = "Court";
      if (image) {
        const preview = document.createElement("img");
        preview.src = image.src;
        preview.alt = "";
        preview.addEventListener("error", () => {
          preview.hidden = true;
          thumbnail.classList.add("has-image-error");
        }, { once: true });
        preview.addEventListener("load", () => {
          thumbnail.classList.remove("has-image-error");
        }, { once: true });
        thumbnail.append(preview);
        thumbnail.addEventListener("click", () => openImageLightbox(image.src, image.alt));
      } else {
        thumbnail.disabled = true;
      }
      thumbnail.append(thumbnailFallback);
      rowHeader.append(thumbnail);
      rowHeader.append(details);
      lane.append(rowHeader);

      const timeline = document.createElement("div");
      timeline.className = "court-lane-timeline";
      timeline.setAttribute("role", "group");
      timeline.setAttribute("aria-label", `Time slots for Court ${court.dataset.courtNo}`);
      schedules.forEach((schedule, index) => {
        const slotDateTime = new Date(`${schedule.court_date}T${schedule.court_time}`);
        const isPast = slotDateTime && slotDateTime.getTime() <= Date.now();
        const isBooked = schedule.schedule_status === "Booked";
        const isAvailable = court.dataset.courtStatus === "Available"
          && schedule.schedule_status === "Available"
          && !isPast;
        const slot = document.createElement("button");
        slot.type = "button";
        slot.className = `court-time-slot${isAvailable ? " is-available" : " is-unavailable"}${isBooked ? " is-booked" : ""}${isPast ? " is-past" : ""}`;
        slot.disabled = !isAvailable;
        slot.setAttribute("aria-pressed", "false");
        const statusText = isAvailable ? "available" : isBooked ? "booked" : isPast ? "past" : "unavailable";
        slot.setAttribute("aria-label", `Court ${court.dataset.courtNo}, ${courtFormatTime(schedule.court_time)}, ${statusText}`);
        slot.dataset.scheduleId = String(schedule.schedule_id);
        slot.dataset.time = schedule.court_time;
        slot.textContent = isAvailable ? courtFormatTime(schedule.court_time) : isBooked ? "Booked" : "Unavailable";
        if (isAvailable) {
          slot.addEventListener("click", () => chooseStartTime(court, schedules, index));
        }
        timeline.append(slot);
      });
      lane.append(timeline);
      scheduleGrid.append(lane);
    });

    scheduleDateLabel.textContent = courtFormatDate(date, { weekday: "short", month: "short", day: "numeric" });
  };

  const applyFilters = () => {
    const maximumRate = Number(priceFilter.value) || Infinity;
    cards.forEach((court) => {
      const belowRate = Number(court.dataset.courtRate) <= maximumRate;
      const hasAvailableTimes = (court.schedules || []).some((slot) => slot.schedule_status === "Available");
      const available = court.dataset.courtStatus === "Available" && hasAvailableTimes;
      const statusMatches = availabilityFilter.value === "all" || available;
      court.hidden = !belowRate || !statusMatches;
      if (selection && selection.court === court && court.hidden) clearSelection();
    });
    buildScheduleGrid(dateInput.value);
  };

  const loadAvailability = async (date) => {
    const requestToken = ++loadToken;
    scheduleLoading.hidden = false;
    scheduleScroll.hidden = true;
    scheduleEmpty.hidden = true;
    clearSelection();
    cards.forEach((court) => {
      court.schedules = [];
    });
    try {
      const results = await Promise.all(cards.map((court) => scheduleForDate(court, date)));
      if (requestToken !== loadToken) return;
      cards.forEach((court, index) => {
        court.schedules = results[index];
      });
      applyFilters();
    } catch (error) {
      if (requestToken !== loadToken) return;
      scheduleEmpty.hidden = false;
      scheduleEmpty.textContent = error.message || "Unable to load court availability. Please try again.";
      if (typeof window.showToast === "function") window.showToast("Availability unavailable", scheduleEmpty.textContent, "error");
    } finally {
      if (requestToken === loadToken) scheduleLoading.hidden = true;
    }
  };

  dateInput.addEventListener("change", () => {
    datePills.forEach((pill) => {
      const active = pill.dataset.date === dateInput.value;
      pill.classList.toggle("is-active", active);
      pill.setAttribute("aria-pressed", String(active));
    });
    loadAvailability(dateInput.value);
  });
  datePills.forEach((pill) => {
    pill.addEventListener("click", () => {
      dateInput.value = pill.dataset.date;
      dateInput.dispatchEvent(new Event("change"));
    });
  });
  priceFilter.addEventListener("change", applyFilters);
  availabilityFilter.addEventListener("change", applyFilters);

  checkoutButton.addEventListener("click", async () => {
    if (!selection) return;
    checkoutButton.disabled = true;
    checkoutButton.textContent = "Checking availability…";
    try {
      const response = await fetch("includes/backend-api/make_booking.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ schedule_ids: selection.schedules.map((schedule) => schedule.schedule_id) }),
      });
      const result = await response.json();
      if (result.requires_auth) {
        window.location.href = "reservation_resume.php";
        return;
      }
      if (result.slot_unavailable || response.status === 409) {
        scheduleCache.clear();
        clearSelection();
        await loadAvailability(dateInput.value);
        if (typeof window.showToast === "function") window.showToast("Time just booked", result.message || "Select another available time.", "error");
        return;
      }
      if (!response.ok || !result.success) throw new Error(result.message || "Unable to reserve this time.");
      if (result.booking_group_id) {
        window.location.href = `checkout.php?booking_group_id=${encodeURIComponent(result.booking_group_id)}`;
      } else {
        window.location.href = `checkout.php?booking_id=${encodeURIComponent(result.booking_id)}`;
      }
    } catch (error) {
      checkoutButton.disabled = false;
      checkoutButton.textContent = "Book Now";
      if (typeof window.showToast === "function") window.showToast("Reservation unavailable", error.message || "Please try again.", "error");
    }
  });

  document.querySelectorAll(".court-card-image").forEach((image) => {
    const fallback = image.parentElement.querySelector(".court-image-fallback");
    const showFallback = () => {
      image.hidden = true;
      const fallback = image.parentElement.querySelector(".court-image-fallback");
      if (fallback) fallback.hidden = false;
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

  document.querySelectorAll(".courts-facility-logo .facility-detail-logo").forEach((image) => {
    const fallback = image.parentElement.querySelector(".courts-facility-logo-fallback");
    const showFallback = () => {
      image.hidden = true;
      fallback.hidden = false;
    };
    image.addEventListener("error", showFallback, { once: true });
    image.addEventListener("load", () => {
      image.hidden = false;
      fallback.hidden = true;
    }, { once: true });
    if (image.complete) {
      if (image.naturalWidth === 0) showFallback();
      else fallback.hidden = true;
    }
  });

  imageLightboxClose.addEventListener("click", closeImageLightbox);
  imageLightbox.addEventListener("click", (event) => {
    if (event.target === imageLightbox) closeImageLightbox();
  });
  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape" && !imageLightbox.hidden) closeImageLightbox();
  });

  loadAvailability(dateInput.value);
});

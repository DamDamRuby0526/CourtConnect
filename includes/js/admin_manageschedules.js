document.addEventListener("DOMContentLoaded", loadSchedules);

const grid = document.getElementById("grid-container");
const modal = document.getElementById("modal_container");
const form = document.getElementById("scheduleForm");
const removeSlotButton = document.getElementById("remove-slot-btn");
const removeSlotHelp = document.getElementById("remove-slot-help");
const generateModal = document.getElementById("generate_modal");
const generateForm = document.getElementById("generateForm");
const generateStartDate = document.getElementById("generate_start_date");
const generateEndDate = document.getElementById("generate_end_date");
const generateSubmitButton = document.getElementById("generate-submit-btn");
const generatePreview = document.getElementById("generate-preview");
const removeScheduleModal = document.getElementById("remove-schedule-modal");
const removeScheduleForm = document.getElementById("remove-schedule-form");
const removeStartDate = document.getElementById("remove_start_date");
const removeEndDate = document.getElementById("remove_end_date");
const removeRangeSummary = document.getElementById("remove-range-summary");
const removeRangeButton = document.getElementById("remove-range-submit-btn");
const generatePanel = generateModal.querySelector(".modal--compact");
const previewCourts = JSON.parse(generatePanel.dataset.previewCourts || "[]");
const openingTime = generatePanel.dataset.openingTime;
const closingTime = generatePanel.dataset.closingTime;
const params = new URLSearchParams(window.location.search);
const courtId = params.get("court_id");
const schedulesById = new Map();
let removePreviewRequest = 0;

function setModalOpen(container, open, focusTarget) {
  container.classList.toggle("show", open);
  container.setAttribute("aria-hidden", String(!open));
  if (open && focusTarget) {
    window.requestAnimationFrame(() => focusTarget.focus());
  }
}

function formatScheduleDate(dateValue) {
  return new Date(`${dateValue}T00:00:00`).toLocaleDateString(undefined, {
    weekday: "short",
    month: "short",
    day: "numeric",
  });
}

function formatScheduleTime(timeValue) {
  return new Date(`1970-01-01T${timeValue}`).toLocaleTimeString([], {
    hour: "numeric",
    minute: "2-digit",
  });
}

function showError(title, message) {
  return window.showDialog({ type: "error", title, message });
}

async function loadSchedules() {
  if (!courtId) {
    await showError("Unable to load schedules", "Court information is missing.");
    return;
  }

  try {
    const response = await fetch(
      `../includes/admin-php/get_schedules.php?court_id=${encodeURIComponent(courtId)}`,
    );
    const data = await response.json();
    if (!response.ok || !data.success) {
      await showError("Unable to load schedules", data.message || "Unable to load schedules.");
      return;
    }

    schedulesById.clear();
    for (const schedule of data.schedules) {
      schedulesById.set(String(schedule.schedule_id), schedule);
    }
    grid.replaceChildren();
    if (data.schedules.length === 0) {
      const empty = document.createElement("p");
      empty.className = "owner-empty-state";
      empty.textContent = "No reservation times yet. Add a time slot to make this court available.";
      grid.append(empty);
      return;
    }

    const schedulesByDate = new Map();
    for (const schedule of data.schedules) {
      if (!schedulesByDate.has(schedule.court_date)) {
        schedulesByDate.set(schedule.court_date, []);
      }
      schedulesByDate.get(schedule.court_date).push(schedule);
    }

    for (const [courtDate, schedules] of schedulesByDate) {
      const group = document.createElement("section");
      group.className = "schedule-date-group";
      const heading = document.createElement("div");
      heading.className = "schedule-date-heading";

      const dateLabel = document.createElement("h2");
      dateLabel.textContent = formatScheduleDate(courtDate);
      const count = document.createElement("span");
      count.className = "schedule-date-count";
      count.textContent = `${schedules.length} ${schedules.length === 1 ? "slot" : "slots"}`;
      const removeDay = document.createElement("button");
      removeDay.type = "button";
      removeDay.className = "schedule-day-remove";
      removeDay.dataset.removeScheduleDay = courtDate;
      removeDay.textContent = "Remove day";
      heading.append(dateLabel, count, removeDay);

      const row = document.createElement("div");
      row.className = "schedule-slot-row";
      for (const schedule of schedules) {
        const chip = document.createElement("button");
        chip.type = "button";
        const isHeld = Number(schedule.has_live_hold) === 1;
        const slotStatus = schedule.schedule_status;
        const statusClass = isHeld ? "is-held" : slotStatus === "Available" ? "is-available" : "is-booked";
        chip.className = `schedule-slot-chip ${statusClass}`;
        chip.textContent = formatScheduleTime(schedule.court_time);
        chip.dataset.id = schedule.schedule_id;
        chip.setAttribute("aria-label", `${chip.textContent}, ${isHeld ? "Held" : slotStatus}`);
        row.append(chip);
      }
      group.append(heading, row);
      grid.append(group);
    }
  } catch (error) {
    console.error(error);
    await showError("Unable to load schedules", "Unable to load schedules. Please try again.");
  }
}

function openAddSchedule() {
  form.reset();
  form.dataset.scheduleId = "";
  document.getElementById("scheduleDialogTitle").textContent = "Add reservation time";
  removeSlotButton.hidden = true;
  removeSlotHelp.hidden = true;
  setModalOpen(modal, true, document.getElementById("court_date"));
}

function openEditSchedule(schedule) {
  document.getElementById("court_date").value = schedule.court_date;
  document.getElementById("court_time").value = schedule.court_time;
  document.getElementById("schedule_status").value = schedule.schedule_status;
  form.dataset.scheduleId = schedule.schedule_id;
  document.getElementById("scheduleDialogTitle").textContent = "Edit reservation time";
  removeSlotButton.hidden = false;
  removeSlotButton.disabled = Number(schedule.can_remove) !== 1;
  removeSlotHelp.hidden = !removeSlotButton.disabled;
  if (removeSlotButton.disabled) {
    if (schedule.schedule_status === "Booked") {
      removeSlotHelp.textContent = "This slot has a booking. Cancel the booking first.";
    } else if (Number(schedule.has_live_hold) === 1) {
      removeSlotHelp.textContent = "A player is checking out for this slot.";
    } else {
      removeSlotHelp.textContent = "This slot has booking history and cannot be removed.";
    }
  } else {
    removeSlotHelp.textContent = "";
  }
  setModalOpen(modal, true, document.getElementById("court_date"));
}

function formatLocalDate(date) {
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, "0");
  const day = String(date.getDate()).padStart(2, "0");
  return `${year}-${month}-${day}`;
}

function addDays(dateValue, days) {
  const date = new Date(`${dateValue}T00:00:00`);
  date.setDate(date.getDate() + days);
  return formatLocalDate(date);
}

function constrainDateRange(startInput, endInput, clampEnd) {
  endInput.min = startInput.value;
  if (!startInput.value) {
    endInput.removeAttribute("max");
    return false;
  }
  endInput.max = addDays(startInput.value, 89);
  if (clampEnd && endInput.value && endInput.value > endInput.max) {
    endInput.value = endInput.max;
  }
  return !endInput.value || (endInput.value >= startInput.value && endInput.value <= endInput.max);
}

function selectedScope(formElement, fieldName) {
  return formElement.querySelector(`input[name="${fieldName}"]:checked`)?.value || "court";
}

function daysInRange(startValue, endValue) {
  if (!startValue || !endValue || endValue < startValue) return 0;
  const start = new Date(`${startValue}T00:00:00`);
  const end = new Date(`${endValue}T00:00:00`);
  return Math.floor((end - start) / 86400000) + 1;
}

function slotCountForDuration(duration) {
  if (!openingTime || !closingTime) return null;
  const [openHour, openMinute] = openingTime.split(":").map(Number);
  const [closeHour, closeMinute] = closingTime.split(":").map(Number);
  if (![openHour, openMinute, closeHour, closeMinute].every(Number.isFinite)) return null;
  const availableMinutes = closeHour * 60 + closeMinute - (openHour * 60 + openMinute);
  return availableMinutes < 0 ? 0 : Math.floor(availableMinutes / duration) + 1;
}

function updateGeneratePreview() {
  const validRange = constrainDateRange(generateStartDate, generateEndDate, false);
  const days = daysInRange(generateStartDate.value, generateEndDate.value);
  if (!validRange || !days || days > 90) {
    generatePreview.textContent = "Choose a valid date range of up to 90 days.";
    return;
  }
  const scope = selectedScope(generateForm, "scope");
  const courts = scope === "court"
    ? previewCourts.filter((court) => Number(court.court_id) === Number(courtId))
    : previewCourts;
  const perDay = courts.reduce((total, court) => {
    const duration = Math.max(1, Number(court.slot_duration) || 60);
    const count = slotCountForDuration(duration);
    return count === null ? null : total + count;
  }, 0);
  if (perDay === null) {
    generatePreview.textContent = "Set facility opening and closing times before generating schedules.";
    return;
  }
  const total = perDay * days;
  generatePreview.textContent = `Creates up to ${total} slots (${perDay} per day, ${days} ${days === 1 ? "day" : "days"}). Existing slots are skipped.`;
}

function openGenerateModal() {
  generateForm.reset();
  const today = document.getElementById("generate-btn").dataset.today;
  generateStartDate.min = today;
  generateStartDate.value = today;
  generateEndDate.value = addDays(today, 6);
  generateForm.querySelector('input[name="scope"][value="court"]').checked = true;
  constrainDateRange(generateStartDate, generateEndDate, true);
  updateGeneratePreview();
  setModalOpen(generateModal, true, generateStartDate);
}

function updateRemoveDateLimits(clampEnd = false) {
  return constrainDateRange(removeStartDate, removeEndDate, clampEnd);
}

function removalRequest(payload) {
  return fetch("../includes/admin-php/remove_schedule.php", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(payload),
  }).then(async (response) => {
    const result = await response.json();
    if (!response.ok || !result.success) {
      throw new Error(result.message || "Unable to remove schedule slots.");
    }
    return result;
  });
}

function updateRemoveRangePreview() {
  const requestId = ++removePreviewRequest;
  const validRange = updateRemoveDateLimits(false);
  const days = daysInRange(removeStartDate.value, removeEndDate.value);
  removeRangeButton.disabled = true;
  if (!validRange || !days || days > 90) {
    removeRangeSummary.textContent = "Choose a valid date range of up to 90 days.";
    return;
  }
  removeRangeSummary.textContent = "Checking removable slots...";
  removalRequest({
    action: "range",
    dry_run: true,
    court_id: courtId,
    start_date: removeStartDate.value,
    end_date: removeEndDate.value,
    scope: selectedScope(removeScheduleForm, "remove_scope"),
  }).then((result) => {
    if (requestId !== removePreviewRequest) return;
    removeRangeSummary.textContent = `${result.removed} open ${result.removed === 1 ? "slot" : "slots"} will be removed. ${result.kept_booked} booked ${result.kept_booked === 1 ? "slot" : "slots"} and ${result.kept_held} held ${result.kept_held === 1 ? "slot" : "slots"} will be kept.`;
    removeRangeButton.disabled = result.removed === 0;
  }).catch(async (error) => {
    if (requestId !== removePreviewRequest) return;
    removeRangeSummary.textContent = "Unable to preview this date range.";
    await showError("Unable to preview removal", error.message);
  });
}

function openRemoveScheduleModal() {
  removeScheduleForm.reset();
  const today = document.getElementById("generate-btn").dataset.today;
  removeStartDate.value = today;
  removeEndDate.value = addDays(today, 6);
  removeScheduleForm.querySelector('input[name="remove_scope"][value="court"]').checked = true;
  updateRemoveDateLimits(true);
  setModalOpen(removeScheduleModal, true, removeStartDate);
  updateRemoveRangePreview();
}

async function removeSchedule(payload) {
  try {
    const result = await removalRequest(payload);
    setModalOpen(modal, false);
    setModalOpen(removeScheduleModal, false);
    const kept = result.kept_booked + result.kept_held + result.kept_other;
    const message = `Removed ${result.removed} ${result.removed === 1 ? "slot" : "slots"}. ${result.kept_booked} booked ${result.kept_booked === 1 ? "slot was" : "slots were"} kept.${result.kept_held ? ` ${result.kept_held} held ${result.kept_held === 1 ? "slot was" : "slots were"} kept.` : ""}${result.kept_other ? ` ${result.kept_other} other ${result.kept_other === 1 ? "slot was" : "slots were"} kept.` : ""}`;
    await window.showDialog({ type: "success", title: "Schedule removed", message });
    await loadSchedules();
    return result;
  } catch (error) {
    await showError("Unable to remove schedule", error.message || "Please try again.");
    return null;
  }
}

function openDayRemoval(day) {
  return removalRequest({
    action: "day",
    dry_run: true,
    court_id: courtId,
    court_date: day,
  }).then(async (preview) => {
    const dayLabel = formatScheduleDate(day);
    if (preview.removed === 0) {
      await showError("No open slots to remove", `${dayLabel} has no removable slots.`);
      return;
    }
    const heldNote = preview.kept_held ? ` ${preview.kept_held} held ${preview.kept_held === 1 ? "slot will" : "slots will"} be kept.` : "";
    const confirmed = await window.showDialog({
      type: "confirm",
      title: "Remove this day's open slots?",
      message: `Remove ${preview.removed} open ${preview.removed === 1 ? "slot" : "slots"} on ${dayLabel}? ${preview.kept_booked} booked ${preview.kept_booked === 1 ? "slot will" : "slots will"} be kept.${heldNote}`,
      confirmText: "Remove day",
      cancelText: "Keep slots",
    });
    if (!confirmed) return;
    await removeSchedule({
      action: "day",
      court_id: courtId,
      court_date: day,
    });
  }).catch((error) => showError("Unable to remove day", error.message || "Please try again."));
}

document.getElementById("add-btn").addEventListener("click", openAddSchedule);
document.getElementById("generate-btn").addEventListener("click", openGenerateModal);
document.getElementById("remove-schedule-btn").addEventListener("click", openRemoveScheduleModal);

generateStartDate.addEventListener("change", () => {
  constrainDateRange(generateStartDate, generateEndDate, true);
  updateGeneratePreview();
});
generateEndDate.addEventListener("change", updateGeneratePreview);
generateForm.querySelectorAll('input[name="scope"]').forEach((input) => {
  input.addEventListener("change", updateGeneratePreview);
});
generateForm.querySelectorAll("[data-generation-days]").forEach((button) => {
  button.addEventListener("click", () => {
    const today = document.getElementById("generate-btn").dataset.today;
    const days = Number(button.dataset.generationDays);
    generateStartDate.value = today;
    generateEndDate.value = addDays(today, days - 1);
    constrainDateRange(generateStartDate, generateEndDate, true);
    updateGeneratePreview();
  });
});

generateForm.addEventListener("submit", async (event) => {
  event.preventDefault();
  constrainDateRange(generateStartDate, generateEndDate, false);
  if (!generateForm.reportValidity() || daysInRange(generateStartDate.value, generateEndDate.value) > 90) return;
  generateSubmitButton.disabled = true;
  generateSubmitButton.textContent = "Generating...";
  try {
    const response = await fetch("../includes/admin-php/generate_schedules.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        court_id: courtId,
        start_date: generateStartDate.value,
        end_date: generateEndDate.value,
        scope: selectedScope(generateForm, "scope"),
      }),
    });
    const result = await response.json();
    if (!response.ok || !result.success) {
      await showError("Unable to generate schedules", result.message || "Unable to generate schedules.");
      return;
    }
    setModalOpen(generateModal, false);
    await window.showDialog({
      type: "success",
      title: "Schedule generated",
      message: `${result.created_slots} new slots added, ${result.skipped_slots} skipped.`,
    });
    await loadSchedules();
  } catch (error) {
    console.error(error);
    await showError("Unable to generate schedules", "Unable to generate schedules. Please try again.");
  } finally {
    generateSubmitButton.disabled = false;
    generateSubmitButton.textContent = "Generate";
  }
});

removeStartDate.addEventListener("change", () => {
  updateRemoveDateLimits(true);
  updateRemoveRangePreview();
});
removeEndDate.addEventListener("change", updateRemoveRangePreview);
removeScheduleForm.querySelectorAll('input[name="remove_scope"]').forEach((input) => {
  input.addEventListener("change", updateRemoveRangePreview);
});
removeScheduleForm.addEventListener("submit", async (event) => {
  event.preventDefault();
  if (removeRangeButton.disabled || !removeScheduleForm.reportValidity()) return;
  removeRangeButton.disabled = true;
  removeRangeButton.textContent = "Removing...";
  await removeSchedule({
    action: "range",
    court_id: courtId,
    start_date: removeStartDate.value,
    end_date: removeEndDate.value,
    scope: selectedScope(removeScheduleForm, "remove_scope"),
  });
  removeRangeButton.textContent = "Remove";
  updateRemoveRangePreview();
});

form.addEventListener("submit", async (event) => {
  event.preventDefault();
  if (!form.reportValidity()) return;
  const saveButton = form.querySelector(".save-btn");
  saveButton.disabled = true;
  saveButton.textContent = "Saving...";
  const scheduleId = form.dataset.scheduleId;
  const endpoint = scheduleId
    ? "../includes/admin-php/update_schedule.php"
    : "../includes/admin-php/add_schedule.php";
  try {
    const response = await fetch(endpoint, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        schedule_id: scheduleId,
        court_id: courtId,
        court_date: document.getElementById("court_date").value,
        court_time: document.getElementById("court_time").value,
        schedule_status: document.getElementById("schedule_status").value,
      }),
    });
    const result = await response.json();
    if (!response.ok || !result.success) {
      await showError("Unable to save schedule", result.message || "Unable to save this schedule.");
      return;
    }
    setModalOpen(modal, false);
    await window.showDialog({
      type: "success",
      title: "Schedule saved",
      message: result.message || "Reservation time saved.",
    });
    await loadSchedules();
  } catch (error) {
    console.error(error);
    await showError("Unable to save schedule", "Unable to save this schedule. Please try again.");
  } finally {
    saveButton.disabled = false;
    saveButton.textContent = "Save slot";
  }
});

removeSlotButton.addEventListener("click", async () => {
  const scheduleId = form.dataset.scheduleId;
  const schedule = schedulesById.get(String(scheduleId));
  if (!schedule || Number(schedule.can_remove) !== 1) return;
  const confirmed = await window.showDialog({
    type: "confirm",
    title: "Remove this time slot?",
    message: "Players won't be able to book it.",
    confirmText: "Remove slot",
    cancelText: "Keep slot",
  });
  if (!confirmed) return;
  removeSlotButton.disabled = true;
  await removeSchedule({ action: "slot", court_id: courtId, schedule_id: scheduleId });
});

document.addEventListener("click", (event) => {
  const target = event.target;
  if (!(target instanceof Element)) return;
  const chip = target.closest(".schedule-slot-chip");
  if (chip) {
    const schedule = schedulesById.get(chip.dataset.id);
    if (schedule) openEditSchedule(schedule);
    return;
  }
  const removeDay = target.closest("[data-remove-schedule-day]");
  if (removeDay) {
    openDayRemoval(removeDay.dataset.removeScheduleDay);
    return;
  }
  if (target.closest(".generate-cancel-btn") || target === generateModal) {
    setModalOpen(generateModal, false);
    return;
  }
  if (target.closest(".remove-schedule-cancel-btn") || target === removeScheduleModal) {
    setModalOpen(removeScheduleModal, false);
    return;
  }
  if (target.closest(".cancel-btn") || target === modal) {
    setModalOpen(modal, false);
  }
});

document.addEventListener("keydown", (event) => {
  if (event.key !== "Escape") return;
  if (document.querySelector(".admin-dialog-overlay")) return;
  if (generateModal.classList.contains("show")) setModalOpen(generateModal, false);
  else if (removeScheduleModal.classList.contains("show")) setModalOpen(removeScheduleModal, false);
  else if (modal.classList.contains("show")) setModalOpen(modal, false);
});

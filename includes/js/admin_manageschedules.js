document.addEventListener("DOMContentLoaded", loadSchedules);

const grid = document.getElementById("grid-container");
const modal = document.getElementById("modal_container");
const form = document.getElementById("scheduleForm");
const generateModal = document.getElementById("generate_modal");
const generateForm = document.getElementById("generateForm");
const generateStartDate = document.getElementById("generate_start_date");
const generateEndDate = document.getElementById("generate_end_date");
const generateSubmitButton = document.getElementById("generate-submit-btn");
const generateSubmitLabel = generateSubmitButton.textContent;

const params = new URLSearchParams(window.location.search);
const courtId = params.get("court_id");
const schedulesById = new Map();

async function loadSchedules() {
  if (!courtId) {
    window.showDialog({ type: "error", title: "Unable to load schedules", message: "Court information is missing." });
    return;
  }

  try {
    const response = await fetch(
      `../includes/admin-php/get_schedules.php?court_id=${encodeURIComponent(courtId)}`,
    );
    const data = await response.json();

    if (!response.ok || !data.success) {
      window.showDialog({ type: "error", title: "Unable to load schedules", message: data.message || "Unable to load schedules." });
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
      dateLabel.textContent = new Date(`${courtDate}T00:00:00`).toLocaleDateString(undefined, {
        weekday: "short",
        month: "short",
        day: "numeric",
        year: "numeric",
      });

      const count = document.createElement("span");
      count.className = "schedule-date-count";
      count.textContent = `${schedules.length} ${schedules.length === 1 ? "slot" : "slots"}`;
      heading.append(dateLabel, count);

      const row = document.createElement("div");
      row.className = "schedule-slot-row";

      for (const schedule of schedules) {
        const chip = document.createElement("button");
        chip.type = "button";
        chip.className = `schedule-slot-chip ${schedule.schedule_status === "Available" ? "is-available" : "is-booked"}`;
        chip.textContent = new Date(`1970-01-01T${schedule.court_time}`).toLocaleTimeString([], {
          hour: "numeric",
          minute: "2-digit",
        });
        chip.dataset.id = schedule.schedule_id;
        chip.setAttribute("aria-label", `${chip.textContent}, ${schedule.schedule_status}`);
        row.append(chip);
      }

      group.append(heading, row);
      grid.append(group);
    }
  } catch (error) {
    console.error(error);
    window.showDialog({ type: "error", title: "Unable to load schedules", message: "Unable to load schedules. Please try again." });
  }
}

document.getElementById("add-btn").addEventListener("click", () => {
  form.reset();
  form.dataset.scheduleId = "";
  document.getElementById("scheduleDialogTitle").textContent = "Add reservation time";
  modal.classList.add("show");
  modal.setAttribute("aria-hidden", "false");
});

function openEditSchedule(schedule) {
  document.getElementById("court_date").value = schedule.court_date;
  document.getElementById("court_time").value = schedule.court_time;
  document.getElementById("schedule_status").value = schedule.schedule_status;
  form.dataset.scheduleId = schedule.schedule_id;
  document.getElementById("scheduleDialogTitle").textContent = "Edit reservation time";
  modal.classList.add("show");
  modal.setAttribute("aria-hidden", "false");
}

function formatLocalDate(date) {
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, "0");
  const day = String(date.getDate()).padStart(2, "0");
  return `${year}-${month}-${day}`;
}

function updateGenerationDateLimits() {
  const start = new Date(`${generateStartDate.value}T00:00:00`);
  generateEndDate.min = generateStartDate.value;

  if (!Number.isNaN(start.getTime())) {
    const lastAllowedDate = new Date(start);
    lastAllowedDate.setDate(lastAllowedDate.getDate() + 89);
    generateEndDate.max = formatLocalDate(lastAllowedDate);
  }
}

function closeGenerateModal() {
  generateModal.classList.remove("show");
  generateModal.setAttribute("aria-hidden", "true");
}

document.getElementById("generate-btn").addEventListener("click", () => {
  generateForm.reset();
  const today = document.getElementById("generate-btn").dataset.today;
  generateStartDate.min = today;
  generateStartDate.value = today;
  generateEndDate.value = today;
  document.getElementById("generate_scope").value = "court";
  updateGenerationDateLimits();
  generateModal.classList.add("show");
  generateModal.setAttribute("aria-hidden", "false");
});

generateStartDate.addEventListener("change", updateGenerationDateLimits);

generateForm.addEventListener("submit", async (event) => {
  event.preventDefault();
  updateGenerationDateLimits();
  if (!generateForm.reportValidity()) return;

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
        scope: document.getElementById("generate_scope").value,
      }),
    });
    const result = await response.json();

    if (!response.ok || !result.success) {
      window.showDialog({ type: "error", title: "Unable to generate schedules", message: result.message || "Unable to generate schedules." });
      return;
    }

    window.showDialog({
      type: "success",
      title: "Schedule generated",
      message: `${result.created_slots} new slots added, ${result.skipped_slots} skipped`,
    });
    closeGenerateModal();
    await loadSchedules();
  } catch (error) {
    console.error(error);
    window.showDialog({ type: "error", title: "Unable to generate schedules", message: "Unable to generate schedules. Please try again." });
  } finally {
    generateSubmitButton.disabled = false;
    generateSubmitButton.textContent = generateSubmitLabel;
  }
});

document.addEventListener("click", async (event) => {
  const target = event.target;
  if (!(target instanceof Element)) return;

  const slotChip = target.closest(".schedule-slot-chip");
  if (slotChip) {
    const schedule = schedulesById.get(slotChip.dataset.id);
    if (schedule) openEditSchedule(schedule);
    return;
  }

  if (target.closest(".generate-cancel-btn") || target === generateModal) {
    closeGenerateModal();
    return;
  }

  if (target.closest(".cancel-btn") || target === modal) {
    modal.classList.remove("show");
    modal.setAttribute("aria-hidden", "true");
    return;
  }

  if (!target.closest("#scheduleForm .save-btn")) return;

  if (!form.reportValidity()) return;
  const scheduleId = form.dataset.scheduleId;
  const endpoint = scheduleId
    ? "../includes/admin-php/update_schedule.php"
    : "../includes/admin-php/add_schedule.php";
  const payload = {
    schedule_id: scheduleId,
    court_id: courtId,
    court_date: document.getElementById("court_date").value,
    court_time: document.getElementById("court_time").value,
    schedule_status: document.getElementById("schedule_status").value,
  };

  try {
    const response = await fetch(endpoint, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload),
    });
    const result = await response.json();

    if (!response.ok || !result.success) {
      window.showDialog({ type: "error", title: "Unable to save schedule", message: result.message || "Unable to save this schedule." });
      return;
    }

    modal.classList.remove("show");
    modal.setAttribute("aria-hidden", "true");
    window.showDialog({ type: "success", title: "Schedule saved", message: result.message || "Reservation time saved." });
    await loadSchedules();
  } catch (error) {
    console.error(error);
    window.showDialog({ type: "error", title: "Unable to save schedule", message: "Unable to save this schedule. Please try again." });
  }
});

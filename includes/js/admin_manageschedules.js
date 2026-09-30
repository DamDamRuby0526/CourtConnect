document.addEventListener("DOMContentLoaded", loadSchedules);

const grid = document.getElementById("grid-container");
const modal = document.getElementById("modal_container");
const form = document.getElementById("scheduleForm");

// url search to get the court id
const params = new URLSearchParams(window.location.search);
const courtId = params.get("court_id");
// change court id 
document.getElementById("court-id").textContent = "Court id:" + courtId ?? N/A;

async function loadSchedules() {
  if (!courtId) return;

  try {
    const res = await fetch(
      `../includes/admin-php/get_schedules.php?court_id=${courtId}`,
    );

    const data = await res.json();

    if (!data.success) {
      alert(data.message);
      return;
    }

    grid.replaceChildren();

    if (data.schedules.length === 0) {
      const empty = document.createElement("p");
      empty.className = "owner-empty-state";
      empty.textContent = "No reservation times yet. Add a time slot to make this court available.";
      grid.append(empty);
      return;
    }

    for (const sched of data.schedules) {
      const item = document.createElement("div");
      item.className = "owner-schedule-row";

      const date = document.createElement("h3");
      date.textContent = new Date(`${sched.court_date}T00:00:00`).toLocaleDateString(undefined, {
        weekday: "short",
        month: "short",
        day: "numeric",
        year: "numeric",
      });

      const time = document.createElement("p");
      time.className = "owner-schedule-time";
      time.textContent = new Date(`1970-01-01T${sched.court_time}`).toLocaleTimeString([], {
        hour: "numeric",
        minute: "2-digit",
      });

      const status = document.createElement("p");
      status.className = `owner-court-status ${sched.schedule_status === "Available" ? "is-available" : "is-booked"}`;
      status.textContent = sched.schedule_status;

      const edit = document.createElement("button");
      edit.type = "button";
      edit.textContent = "Edit time";
      edit.className = "edit-btn owner-secondary-button";
      edit.dataset.id = sched.schedule_id;

      item.append(date, time, status, edit);
      grid.append(item);
    }
  } catch (err) {
    console.error(err);
  }
}

// OPEN ADD MODAL
document.getElementById("add-btn").addEventListener("click", () => {
  form.reset();
  form.dataset.scheduleId = "";
  document.getElementById("scheduleDialogTitle").textContent = "Add reservation time";
  modal.classList.add("show");
  modal.setAttribute("aria-hidden", "false");
});

// EDIT
document.addEventListener("click", async (e) => {
  const editButton = e.target.closest(".edit-btn");
  if (!editButton) return;

  const id = editButton.dataset.id;

  const res = await fetch(
    `../includes/admin-php/get_schedules.php?court_id=${courtId}`,
  );

  const data = await res.json();

  const sched = data.schedules.find((s) => s.schedule_id == id);

  if (!sched) return;

  document.getElementById("court_date").value = sched.court_date;
  document.getElementById("court_time").value = sched.court_time;
  document.getElementById("schedule_status").value = sched.schedule_status;

  form.dataset.scheduleId = id;

  document.getElementById("scheduleDialogTitle").textContent = "Edit reservation time";
  modal.classList.add("show");
  modal.setAttribute("aria-hidden", "false");
});

// save (add or edit)
document.addEventListener("click", async (e) => {
  if (e.target.closest("#scheduleForm .save-btn")) {
    const form = document.getElementById("scheduleForm");
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

    const res = await fetch(endpoint, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify(payload),
    });

    const result = await res.json();

    if (!res.ok || !result.success) {
      alert(result.message);
      return;
    }

    modal.classList.remove("show");
    modal.setAttribute("aria-hidden", "true");
    loadSchedules();
  }
});

// cancel
document.addEventListener("click", (e) => {
  if (e.target.closest(".cancel-btn")) {
    modal.classList.remove("show");
    modal.setAttribute("aria-hidden", "true");
  } else if (e.target === modal) {
    modal.classList.remove("show");
    modal.setAttribute("aria-hidden", "true");
  }
});

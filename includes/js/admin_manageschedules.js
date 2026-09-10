document.addEventListener("DOMContentLoaded", loadSchedules);

const grid = document.getElementById("grid-container");
const modal = document.getElementById("modal_container");
const form = document.getElementById("scheduleForm");

// url search to get the court id
const params = new URLSearchParams(window.location.search);
const courtId = params.get("court_id");

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

    for (const sched of data.schedules) {
      const item = document.createElement("div");
      item.className = "item";

      const date = document.createElement("h3");
      date.textContent = sched.court_date;

      const time = document.createElement("p");
      time.textContent = sched.court_time;

      const status = document.createElement("p");
      status.textContent = sched.schedule_status;

      const edit = document.createElement("button");
      edit.textContent = "Edit";
      edit.className = "edit-btn";
      edit.dataset.id = sched.schedule_id;

      item.append(data, time, status, edit);
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
  modal.classList.add("show");
});

// EDIT
document.addEventListener("click", async (e) => {
  if (!e.target.classList.contains("edit-btn")) return;

  const id = e.target.dataset.id;

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

  modal.classList.add("show");
});

// save (add or edit)
document.addEventListener("click", async (e) => {
  if (e.target.classList.contains("save-btn")) {
    const form = document.getElementById("scheduleForm");
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

    if (!result.success) {
      alert(result.message);
      return;
    }

    modal.classList.remove("show");
    loadSchedules();
  }
});

// cancel
document.addEventListener("click", (e) => {
  if (e.target.classList.contains("cancel-btn")) {
    modal_container.classList.remove("show");
  }
});

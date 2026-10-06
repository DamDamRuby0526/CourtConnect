function getFacilityId() {
  return document.getElementById("ownerFacility")?.dataset.facilityId;
}

async function loadCourts() {
  const grid = document.getElementById("grid-container");

  try {
    const response = await fetch("../includes/admin-php/get_court.php");
    const data = await response.json();

    if (!data.success) {
      grid.textContent = data.message || "Failed to load courts.";
      return;
    }

    grid.replaceChildren();
    document.getElementById("courtCount").textContent =
      `${data.courts.length} ${data.courts.length === 1 ? "court" : "courts"}`;

    if (data.courts.length === 0) {
      const empty = document.createElement("p");
      empty.className = "owner-empty-state";
      empty.textContent =
        "No courts yet. Add your first court to start accepting reservations.";
      grid.append(empty);
      return;
    }

    for (const court of data.courts) {
      const article = document.createElement("article");
      article.className = "owner-court-row";

      const image = document.createElement("img");
      image.className = "owner-court-image";
      image.src = court.court_img
        ? `../uploads/court/${encodeURIComponent(court.court_img)}`
        : "../uploads/badminton-pickleball-court.svg";
      image.alt = court.court_img
        ? `Court ${court.court_no}`
        : "CourtConnect court placeholder";

      const details = document.createElement("div");
      details.className = "owner-court-details";
      const name = document.createElement("h3");
      name.textContent = `Court ${court.court_no}`;
      const description = document.createElement("p");
      description.textContent = court.description;
      const meta = document.createElement("div");
      meta.className = "owner-court-meta";
      meta.textContent = `₱${court.court_rate} / hour  ·  ${court.slot_duration} min slots`;
      details.append(name, description, meta);

      const status = document.createElement("span");
      status.className = `owner-court-status ${court.court_status === "Available" ? "is-available" : "is-maintenance"}`;
      status.textContent = court.court_status;

      const actions = document.createElement("div");
      actions.className = "owner-court-actions";
      const editButton = document.createElement("button");
      editButton.type = "button";
      editButton.className = "edit-btn owner-secondary-button";
      editButton.textContent = "Edit details";
      editButton.dataset.courtId = court.court_id;
      const scheduleButton = document.createElement("a");
      scheduleButton.className = "owner-schedule-link";
      scheduleButton.href = `manageschedules.php?court_id=${encodeURIComponent(court.court_id)}`;
      scheduleButton.textContent = "Court schedule";
      actions.append(editButton, scheduleButton);

      article.append(image, details, status, actions);
      grid.append(article);
    }
  } catch (error) {
    console.error("Error loading courts:", error);
  }
}
const courtForm = document.getElementById("courtForm");
const modalContainer = document.getElementById("modal_container");
const courtImagePreview = document.getElementById("court_img_preview");

function closeCourtModal() {
  modalContainer.classList.remove("show");
  modalContainer.setAttribute("aria-hidden", "true");
  courtForm.reset();
  courtImagePreview.hidden = true;
  courtImagePreview.removeAttribute("src");
  document.getElementById("courtFormStatus").textContent = "";
  document.getElementById("courtFormStatus").classList.remove("is-error");
}

function openCourtModal(court = null) {
  courtForm.reset();
  courtForm.dataset.courtId = court?.court_id || "";
  document.getElementById("courtDialogTitle").textContent = court
    ? `Edit Court ${court.court_no}`
    : "Add a court";
  document.getElementById("court_no").value = court?.court_no || "";
  document.getElementById("court_rate").value = court?.court_rate || "";
  document.getElementById("description").value = court?.description || "";
  document.getElementById("slot_duration").value = court?.slot_duration || "60";
  document.getElementById("court_status").value =
    court?.court_status || "Available";
  courtImagePreview.hidden = !court?.court_img;
  if (court?.court_img)
    courtImagePreview.src = `../uploads/court/${encodeURIComponent(court.court_img)}`;
  document.getElementById("courtFormStatus").textContent = "";
  document.getElementById("courtFormStatus").classList.remove("is-error");
  modalContainer.classList.add("show");
  modalContainer.setAttribute("aria-hidden", "false");
}

document.addEventListener("DOMContentLoaded", () => {
  loadCourts();
  document
    .getElementById("add-btn")
    .addEventListener("click", () => openCourtModal());

  document
    .getElementById("facilityHoursForm")
    .addEventListener("submit", async (event) => {
      event.preventDefault();
      const status = document.getElementById("hoursStatus");
      const response = await fetch(
        "../includes/admin-php/update_facility_hours.php",
        {
          method: "POST",
          body: new FormData(event.currentTarget),
        },
      );
      const result = await response.json();
      status.textContent = result.message;
      status.classList.toggle("is-error", !result.success);
    });

  document.getElementById("court_img").addEventListener("change", (event) => {
    const file = event.currentTarget.files[0];
    if (!file) return;
    courtImagePreview.src = URL.createObjectURL(file);
    courtImagePreview.hidden = false;
  });

  document.addEventListener("click", async (event) => {
    const editButton = event.target.closest(".edit-btn");
    if (editButton) {
      const response = await fetch("../includes/admin-php/get_court.php");
      const result = await response.json();
      const court = result.courts?.find(
        (item) => String(item.court_id) === editButton.dataset.courtId,
      );
      if (!result.success || !court) {
        window.alert(result.message || "Could not load this court.");
        return;
      }
      openCourtModal(court);
      return;
    }

    if (event.target.closest(".cancel-btn")) closeCourtModal();
    if (event.target === modalContainer) closeCourtModal();

    const saveButton = event.target.closest(".save-btn");
    if (!saveButton) return;
    if (!courtForm.reportValidity()) return;

    saveButton.disabled = true;
    const status = document.getElementById("courtFormStatus");
    status.textContent = "Saving court details...";
    const formData = new FormData(courtForm);
    const courtId = courtForm.dataset.courtId;
    if (courtId) formData.append("court_id", courtId);
    const endpoint = courtId ? "update_court.php" : "add_courts.php";

    try {
      const response = await fetch(`../includes/admin-php/${endpoint}`, {
        method: "POST",
        body: formData,
      });
      const result = await response.json();
      if (!response.ok || !result.success)
        throw new Error(result.message || "Could not save court details.");
      closeCourtModal();
      await loadCourts();
    } catch (error) {
      status.textContent = error.message;
      status.classList.add("is-error");
    } finally {
      saveButton.disabled = false;
    }
  });
});

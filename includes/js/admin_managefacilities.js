document.addEventListener("DOMContentLoaded", loadFacilities);

async function loadFacilities() {
  const grid = document.getElementById("grid-container");

  try {
    const res = await fetch("../includes/admin-php/get_facility.php");
    const data = await res.json();

    if (!res.ok || !data.success) {
      window.showDialog({ type: "error", title: "Unable to load facilities", message: data.message || "Failed to load facilities." });
      return;       
    }

    grid.replaceChildren();

    for (const facility of data.facilities) {
      const item = document.createElement("article");
      item.className = "superadmin-facility-card";

      const img = document.createElement("img");
      img.src = facility.facility_img
        ? "../uploads/facility/" + facility.facility_img
        : "../uploads/facility/facility.png";
      img.alt = `${facility.facility_name} facility`;

      const name = document.createElement("p");
      name.className = "superadmin-facility-name";
      name.textContent = facility.facility_name;

      const id = document.createElement("p");
      id.className = "superadmin-facility-id";
      id.textContent = "Facility ID: " + facility.facility_id;

      const editBtn = document.createElement("button");
      editBtn.type = "button";
      editBtn.className = "edit-btn";
      editBtn.textContent = "Manage Facility";
      editBtn.dataset.facilityId = facility.facility_id;

      item.append(img, name, id, editBtn);
      if (document.body.dataset.roleId !== "5") {
        const openBtn = document.createElement("button");
        openBtn.type = "button";
        openBtn.className = "open-btn";
        openBtn.textContent = "Court Details";
        openBtn.dataset.facilityId = facility.facility_id;
        item.append(openBtn);
      }
      grid.append(item);
    }
  } catch (error) {
    window.showDialog({
      type: "error",
      title: "Unable to load facilities",
      message: error.message || "Could not load facility records. Please try again.",
    });
  }
}

// COURT DETAILS PAGE
document.addEventListener("click", (e) => {
  if (!e.target.classList.contains("open-btn")) return;

  const facilityId = e.target.dataset.facilityId;

  window.location.href = `managecourts.php?facility_id=${facilityId}`;
});


function resetImagePreviews() {
  for (const key of ["facility_img", "qr_img"]) {
    document.getElementById(`${key}_preview`).style.display = "none";
    document.getElementById(`${key}_preview`).src = "";
    document.getElementById(`${key}_hint`).textContent = "";
  }
}

// open modal for ADD
document.getElementById("add-btn").addEventListener("click", () => {
  document.getElementById("facilityForm").reset();
  document.getElementById("facilityForm").dataset.facilityId = "";
  resetImagePreviews();
  modal_container.classList.add("show");
  modal_container.setAttribute("aria-hidden", "false");
  document.getElementById("facility_name").focus();
});

// open modal for EDIT (Manage Facility)
document.addEventListener("click", async (e) => {
  if (e.target.classList.contains("edit-btn")) {
    const facilityId = e.target.dataset.facilityId;

    const res = await fetch(
      `../includes/admin-php/get_facility.php?facility_id=${facilityId}`,
    );
    const result = await res.json();


  if (!result.success) {
    window.showDialog({ type: "error", title: "Unable to load facility", message: result.message || "Failed to load this facility." });
    return;
  }

  const f = result.facilities[0];



    document.getElementById("facility_name").value = f.facility_name;
    document.getElementById("address").value = f.address;
    document.getElementById("municipality").value = f.municipality;
    document.getElementById("sport_type").value = f.sport_type;
    document.getElementById("opening_time").value = f.opening_time;
    document.getElementById("closing_time").value = f.closing_time;

    resetImagePreviews();

    if (f.facility_img) {
      const preview = document.getElementById("facility_img_preview");
      preview.src = "../uploads/facility/" + f.facility_img;
      preview.style.display = "block";
      document.getElementById("facility_img_hint").textContent =
        "Current image shown above. Choose a file only to replace it.";
    }

    if (f.qr_img) {
      const preview = document.getElementById("qr_img_preview");
      preview.src = "../uploads/qr/" + f.qr_img;
      preview.style.display = "block";
      document.getElementById("qr_img_hint").textContent =
        "Current QR shown above. Choose a file only to replace it.";
    }

    document.getElementById("facilityForm").dataset.facilityId = f.facility_id;

    modal_container.classList.add("show");
    modal_container.setAttribute("aria-hidden", "false");
    document.getElementById("facility_name").focus();
  }
});

// save (add or edit) — FormData because of file uploads
document.addEventListener("click", async (e) => {
  if (e.target.classList.contains("save-btn")) {
    const form = document.getElementById("facilityForm");
    const facilityId = form.dataset.facilityId;

    const formData = new FormData(form);
    if (facilityId) {
      formData.append("facility_id", facilityId);
    }

    const endpoint = facilityId
      ? "../includes/admin-php/update_facility.php"
      : "../includes/admin-php/add_facility.php";

    const res = await fetch(endpoint, {
      method: "POST",
      body: formData,
    });

    const result = await res.json();

    if (!result.success) {
      window.showDialog({ type: "error", title: "Unable to save facility", message: result.message || "Unable to save this facility." });
      return;
    }

    modal_container.classList.remove("show");
    modal_container.setAttribute("aria-hidden", "true");
    window.showDialog({ type: "success", title: "Facility saved", message: result.message || "Facility saved successfully." });
    loadFacilities();
  }
});

// cancel
document.addEventListener("click", (e) => {
  if (e.target.classList.contains("cancel-btn")) {
    modal_container.classList.remove("show");
    modal_container.setAttribute("aria-hidden", "true");
  }
});

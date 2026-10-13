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
    document.getElementById("courtCount").textContent = `${data.courts.length} ${data.courts.length === 1 ? "court" : "courts"}`;

    if (data.courts.length === 0) {
      const empty = document.createElement("p");
      empty.className = "owner-empty-state";
      empty.textContent = "No courts yet. Add your first court to start accepting reservations.";
      grid.append(empty);
      return;
    }

    for (const court of data.courts) {
      const article = document.createElement("article");
      article.className = "owner-court-row";

      const image = document.createElement("img");
      image.className = "owner-court-image";
      image.src = court.court_img ? `../uploads/court/${encodeURIComponent(court.court_img)}` : "../uploads/badminton-pickleball-court.svg";
      image.alt = court.court_img ? `Court ${court.court_no}` : "CourtConnect court placeholder";

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
    document.getElementById("courtDialogTitle").textContent = court ? `Edit Court ${court.court_no}` : "Add a court";
    document.getElementById("court_no").value = court?.court_no || "";
    document.getElementById("court_rate").value = court?.court_rate || "";
    document.getElementById("description").value = court?.description || "";
    document.getElementById("slot_duration").value = court?.slot_duration || "60";
    document.getElementById("court_status").value = court?.court_status || "Available";
    courtImagePreview.hidden = !court?.court_img;
    if (court?.court_img) courtImagePreview.src = `../uploads/court/${encodeURIComponent(court.court_img)}`;
    document.getElementById("courtFormStatus").textContent = "";
    document.getElementById("courtFormStatus").classList.remove("is-error");
    modalContainer.classList.add("show");
    modalContainer.setAttribute("aria-hidden", "false");
  }

  document.addEventListener("DOMContentLoaded", () => {
    loadCourts();
    document.getElementById("add-btn").addEventListener("click", () => openCourtModal());

    const brandingForm = document.getElementById("facilityBrandingForm");
    const logoInput = document.getElementById("facilityLogoInput");
    const logoPreview = document.getElementById("facilityLogoPreview");
    const qrInput = document.getElementById("gcashQrInput");
    const qrPreview = document.getElementById("gcashQrPreview");
    const qrPlaceholder = document.getElementById("gcashQrPlaceholder");
    const removeLogoInput = document.getElementById("removeFacilityLogo");
    const uploadLogoButton = document.getElementById("uploadFacilityLogo");
    const uploadQrButton = document.getElementById("uploadGcashQr");
    const removeLogoButton = document.getElementById("clearFacilityLogo");
    const facilityNameInput = document.getElementById("facilityNameInput");
    const brandingStatus = document.getElementById("facilityBrandingStatus");
    const saveBrandingButton = document.getElementById("saveFacilityBranding");
    let initialFacilityName = facilityNameInput.value;
    const placeholderLogo = "../uploads/badminton-pickleball-court.svg";
    let previewObjectUrl = null;
    let qrPreviewObjectUrl = null;

    function updateBrandingSaveState() {
      const changed = facilityNameInput.value.trim() !== initialFacilityName
        || logoInput.files.length > 0
        || qrInput.files.length > 0
        || removeLogoInput.value === "1";
      saveBrandingButton.disabled = !changed;
    }

    function setLogoPreview(source, hasLogo) {
      if (previewObjectUrl) {
        URL.revokeObjectURL(previewObjectUrl);
        previewObjectUrl = null;
      }
      logoPreview.src = source || placeholderLogo;
      logoPreview.dataset.currentLogo = hasLogo ? "1" : "0";
      removeLogoButton.disabled = !hasLogo;
    }

    function setQrPreview(source) {
      if (qrPreviewObjectUrl && qrPreviewObjectUrl !== source) {
        URL.revokeObjectURL(qrPreviewObjectUrl);
      }
      qrPreviewObjectUrl = source && source.startsWith("blob:") ? source : null;
      qrPreview.hidden = !source;
      qrPlaceholder.hidden = !!source;
      if (source) qrPreview.src = source;
      else qrPreview.removeAttribute("src");
    }

    function validateImageFile(file, label) {
      if (!["image/jpeg", "image/png", "image/webp"].includes(file.type)) {
        window.showDialog({ type: "error", title: `Unsupported ${label} format`, message: "Choose a JPG, PNG, or WebP image." });
        return false;
      }
      if (file.size > 2 * 1024 * 1024) {
        window.showDialog({ type: "error", title: `${label} is too large`, message: `${label} images must be 2 MB or smaller.` });
        return false;
      }
      return true;
    }

    uploadLogoButton.addEventListener("click", () => logoInput.click());
    uploadQrButton.addEventListener("click", () => qrInput.click());
    logoInput.addEventListener("change", () => {
      const file = logoInput.files[0];
      if (!file) return;

      if (!validateImageFile(file, "Logo")) {
        logoInput.value = "";
        return;
      }

      removeLogoInput.value = "0";
      previewObjectUrl = URL.createObjectURL(file);
      logoPreview.src = previewObjectUrl;
      logoPreview.dataset.currentLogo = "1";
      removeLogoButton.disabled = false;
      brandingStatus.textContent = "Previewing selected logo. Save changes to publish it.";
      brandingStatus.classList.remove("is-error");
      updateBrandingSaveState();
    });

    qrInput.addEventListener("change", () => {
      const file = qrInput.files[0];
      if (!file) return;
      if (!validateImageFile(file, "GCash QR")) {
        qrInput.value = "";
        return;
      }

      setQrPreview(URL.createObjectURL(file));
      brandingStatus.textContent = "Previewing selected GCash QR. Save changes to publish it.";
      brandingStatus.classList.remove("is-error");
      updateBrandingSaveState();
    });

    removeLogoButton.addEventListener("click", () => {
      logoInput.value = "";
      removeLogoInput.value = "1";
      setLogoPreview(placeholderLogo, false);
      brandingStatus.textContent = "Logo removal is pending. Save changes to publish it.";
      brandingStatus.classList.remove("is-error");
      updateBrandingSaveState();
    });

    facilityNameInput.addEventListener("input", updateBrandingSaveState);

    brandingForm.addEventListener("submit", async (event) => {
      event.preventDefault();
      if (!brandingForm.reportValidity()) return;

      saveBrandingButton.disabled = true;
      saveBrandingButton.textContent = "Saving...";
      brandingStatus.textContent = "Saving facility branding...";
      brandingStatus.classList.remove("is-error");

      try {
        const response = await fetch("../includes/admin-php/update_facility_branding.php", {
          method: "POST",
          body: new FormData(brandingForm),
        });
        const result = await response.json();
        if (!response.ok || !result.success) {
          throw new Error(result.message || "Unable to save facility branding.");
        }

        facilityNameInput.value = result.facility_name;
        initialFacilityName = result.facility_name;
        document.querySelectorAll(".owner-brand small, .admin-brand-copy small").forEach((label) => {
          label.textContent = result.facility_name;
        });
        if (result.facility_img) {
          setLogoPreview(`../uploads/facility/${encodeURIComponent(result.facility_img)}`, true);
        } else {
          setLogoPreview(placeholderLogo, false);
        }
        if (result.qr_img) {
          setQrPreview(`../uploads/qr/${encodeURIComponent(result.qr_img)}`);
        } else {
          setQrPreview("");
        }
        logoInput.value = "";
        qrInput.value = "";
        removeLogoInput.value = "0";
        brandingStatus.textContent = result.message;
        updateBrandingSaveState();
        window.showDialog({ type: "success", title: "Facility settings saved", message: result.message });
      } catch (error) {
        brandingStatus.textContent = error.message || "Unable to save facility branding.";
        brandingStatus.classList.add("is-error");
        window.showDialog({ type: "error", title: "Unable to save facility settings", message: brandingStatus.textContent });
        updateBrandingSaveState();
      } finally {
        saveBrandingButton.textContent = "Save Changes";
      }
    });

    document.getElementById("facilityHoursForm").addEventListener("submit", async (event) => {
      event.preventDefault();
      const status = document.getElementById("hoursStatus");
      const response = await fetch("../includes/admin-php/update_facility_hours.php", {
        method: "POST",
        body: new FormData(event.currentTarget),
      });
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
        const court = result.courts?.find((item) => String(item.court_id) === editButton.dataset.courtId);
        if (!result.success || !court) {
          window.showDialog({ type: "error", title: "Unable to load court", message: result.message || "Could not load this court." });
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
        const response = await fetch(`../includes/admin-php/${endpoint}`, { method: "POST", body: formData });
        const result = await response.json();
        if (!response.ok || !result.success) throw new Error(result.message || "Could not save court details.");
        closeCourtModal();
        window.showDialog({ type: "success", title: "Court saved", message: result.message || "Court details saved." });
        await loadCourts();
      } catch (error) {
        status.textContent = error.message;
        status.classList.add("is-error");
        window.showDialog({ type: "error", title: "Unable to save court", message: error.message || "Could not save court details." });
      } finally {
        saveButton.disabled = false;
      }
    });
  });

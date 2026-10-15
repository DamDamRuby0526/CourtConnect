document.addEventListener("DOMContentLoaded", () => {
  const form = document.getElementById("resubmit-form");
  const submitButton = document.getElementById("resubmit-submit");
  const submitLabel = document.getElementById("resubmit-submit-label");
  const submitSpinner = form.querySelector(".resubmit-submit-spinner");
  const requiredCount = document.getElementById("required-document-count");
  const cancelButton = document.getElementById("resubmit-cancel");
  const viewerOverlay = document.getElementById("resubmit-viewer");
  const viewerDialog = viewerOverlay.querySelector('[role="dialog"]');
  const viewerContent = document.getElementById("resubmit-viewer-content");
  const viewerTitle = document.getElementById("resubmit-viewer-title");
  const viewerControls = document.getElementById("resubmit-viewer-controls");
  const fileInputs = [...form.querySelectorAll(".resubmit-file-input")];
  const objectUrls = new Map();
  let viewerImage = null;
  let viewerScale = 1;
  let viewerRotation = 0;
  let viewerTrigger = null;
  let isSubmitting = false;

  function updateSubmitState() {
    const requiredDocuments = [...form.querySelectorAll('.owner-resubmit-document[data-required="true"]')];
    const selectedCount = requiredDocuments.filter((card) => {
      const input = card.querySelector(".resubmit-file-input");
      return input.files.length > 0 && !card.querySelector(".resubmit-file-error:not([hidden])");
    }).length;
    requiredCount.textContent = `${selectedCount} of ${requiredDocuments.length} required ${requiredDocuments.length === 1 ? "document" : "documents"} selected`;
    submitButton.disabled = isSubmitting || selectedCount !== requiredDocuments.length;
  }

  function setFileError(card, message) {
    const error = card.querySelector(".resubmit-file-error");
    error.textContent = message;
    error.hidden = !message;
    card.classList.toggle("has-file-error", Boolean(message));
  }

  function fileSizeLabel(bytes) {
    return bytes < 1024 * 1024
      ? `${Math.max(1, Math.round(bytes / 1024))} KB`
      : `${(bytes / (1024 * 1024)).toFixed(2)} MB`;
  }

  function removePreviewUrl(input) {
    const url = objectUrls.get(input);
    if (url) URL.revokeObjectURL(url);
    objectUrls.delete(input);
  }

  function clearSelectedFile(input, shouldFocus = false) {
    const card = input.closest(".owner-resubmit-document");
    removePreviewUrl(input);
    window.setUploadInputFiles(input, []);
    if (shouldFocus) card.querySelector(".resubmit-drop-area").focus();
  }

  function showFilePreview(input, file) {
    const card = input.closest(".owner-resubmit-document");
    const preview = card.querySelector(".resubmit-file-preview");
    preview.replaceChildren();
    preview.hidden = false;

    const extension = file.name.split(".").pop()?.toLocaleLowerCase() || "";
    const isImage = file.type.startsWith("image/") || ["jpg", "jpeg", "png", "webp"].includes(extension);
    const iconOrImage = document.createElement(isImage ? "img" : "span");
    iconOrImage.className = isImage ? "resubmit-preview-image" : "resubmit-preview-pdf";
    if (isImage) {
      const url = URL.createObjectURL(file);
      objectUrls.set(input, url);
      iconOrImage.src = url;
      iconOrImage.alt = "";
    } else {
      iconOrImage.textContent = "PDF";
      iconOrImage.setAttribute("aria-hidden", "true");
    }

    const metadata = document.createElement("span");
    metadata.className = "resubmit-preview-metadata";
    const name = document.createElement("strong");
    name.textContent = file.name;
    const size = document.createElement("small");
    size.textContent = fileSizeLabel(file.size);
    metadata.append(name, size);

    const removeButton = document.createElement("button");
    removeButton.type = "button";
    removeButton.className = "resubmit-text-action resubmit-remove-file";
    removeButton.textContent = "Remove";
    removeButton.addEventListener("click", () => clearSelectedFile(input, true));
    preview.append(iconOrImage, metadata, removeButton);
  }

  function renderUploadState(input, files, message) {
    const card = input.closest(".owner-resubmit-document");
    removePreviewUrl(input);
    setFileError(card, message);
    const preview = card.querySelector(".resubmit-file-preview");
    if (message || !files.length) {
      preview.replaceChildren();
      preview.hidden = true;
    } else {
      showFilePreview(input, files[0]);
    }
    updateSubmitState();
  }

  fileInputs.forEach((input) => {
    const card = input.closest(".owner-resubmit-document");
    const zone = card.querySelector(".resubmit-drop-area");
    window.initializeFileUploadZone({
      input,
      zone,
      error: card.querySelector(".resubmit-file-error"),
      maxFileSize: 5 * 1024 * 1024,
      allowedTypes: window.facilityUploadTypes.documents,
      invalidTypeMessage: "Invalid file format. Please upload a PDF.",
      onFilesChanged: (files, message) => {
        renderUploadState(input, files, message);
      },
    });
  });

  document.querySelectorAll(".replace-document-button").forEach((button) => {
    button.addEventListener("click", () => {
      const card = button.closest(".owner-resubmit-document");
      const zone = card.querySelector(".resubmit-file-zone");
      const isExpanded = !zone.hidden;
      zone.hidden = isExpanded;
      button.setAttribute("aria-expanded", String(!isExpanded));
      if (!isExpanded) card.querySelector(".resubmit-drop-area").focus();
    });
  });

  function closeViewer() {
    viewerOverlay.classList.remove("is-open");
    viewerOverlay.setAttribute("aria-hidden", "true");
    viewerContent.replaceChildren();
    viewerControls.hidden = true;
    viewerImage = null;
    if (viewerTrigger?.isConnected) viewerTrigger.focus();
  }

  function openViewer(button) {
    viewerTrigger = button;
    viewerTitle.textContent = button.dataset.documentTitle || "Current document";
    viewerContent.replaceChildren();
    viewerControls.hidden = true;
    const url = button.dataset.viewerUrl;
    if (!url) return;
    if (button.dataset.documentMime === "application/pdf") {
        const object = document.createElement("object");
        object.type = "application/pdf";
        object.data = url;
        object.setAttribute("aria-label", `${viewerTitle.textContent} PDF`);
        object.append("Unable to display this PDF. Use your browser's PDF support.");
        viewerContent.append(object);
    } else if (["image/jpeg", "image/png", "image/webp"].includes(button.dataset.documentMime)) {
        const image = document.createElement("img");
        image.alt = viewerTitle.textContent;
        image.src = url;
        image.addEventListener("load", () => { viewerImage = image; viewerControls.hidden = false; }, { once: true });
        image.addEventListener("error", () => {
          if (viewerContent.contains(image)) {
            const error = document.createElement("p");
            error.textContent = "Unable to display this document.";
            viewerContent.replaceChildren(error);
          }
        }, { once: true });
        viewerContent.append(image);
    } else {
      const error = document.createElement("p");
      error.textContent = "The current document type cannot be displayed.";
      viewerContent.append(error);
    }
    viewerOverlay.classList.add("is-open");
    viewerOverlay.setAttribute("aria-hidden", "false");
    viewerDialog.focus();
  }

  document.querySelectorAll(".view-current-button").forEach((button) => {
    button.addEventListener("click", () => openViewer(button));
  });
  viewerOverlay.querySelector(".resubmit-viewer-close").addEventListener("click", closeViewer);
  viewerOverlay.addEventListener("click", (event) => {
    if (event.target === viewerOverlay) closeViewer();
  });
  viewerOverlay.querySelectorAll("[data-viewer-action]").forEach((button) => {
    button.addEventListener("click", () => {
      if (!viewerImage) return;
      const action = button.dataset.viewerAction;
      if (action === "zoom-in") viewerScale = Math.min(3, viewerScale + 0.2);
      if (action === "zoom-out") viewerScale = Math.max(0.6, viewerScale - 0.2);
      if (action === "rotate") viewerRotation = (viewerRotation + 90) % 360;
      if (action === "reset") {
        viewerScale = 1;
        viewerRotation = 0;
      }
      viewerImage.style.transform = `scale(${viewerScale}) rotate(${viewerRotation}deg)`;
    });
  });
  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape" && viewerOverlay.classList.contains("is-open")) closeViewer();
  });

  cancelButton.addEventListener("click", async () => {
    const hasChosenFiles = fileInputs.some((input) => input.files.length > 0);
    if (hasChosenFiles) {
      const confirmed = await window.showDialog({
        type: "confirm",
        title: "Discard selected files?",
        message: "Your selected documents will be removed and the application will remain rejected.",
        confirmText: "Discard files",
        cancelText: "Keep editing",
      });
      if (!confirmed) return;
    }
    window.location.assign("owner_application.php");
  });

  form.addEventListener("submit", async (event) => {
    event.preventDefault();
    if (isSubmitting) return;
    if (!form.reportValidity()) return;

    isSubmitting = true;
    submitButton.disabled = true;
    submitSpinner.hidden = false;
    submitLabel.textContent = "Submitting...";
    try {
      const response = await fetch("includes/backend-api/resubmit_application.php", {
        method: "POST",
        body: new FormData(form),
      });
      const result = await response.json();
      if (!response.ok || !result.success) throw new Error(result.message || "Unable to resubmit documents.");
      await window.showDialog({
        type: "success",
        title: "Documents submitted",
        message: result.message,
      });
      window.location.assign("owner_application.php");
    } catch (error) {
      await window.showDialog({
        type: "error",
        title: "Unable to resubmit documents",
        message: error.message || "Please try again.",
      });
    } finally {
      isSubmitting = false;
      submitSpinner.hidden = true;
      submitLabel.textContent = "Submit for review";
      updateSubmitState();
    }
  });

  updateSubmitState();
  window.addEventListener("beforeunload", () => {
    objectUrls.forEach((url) => URL.revokeObjectURL(url));
  });
});

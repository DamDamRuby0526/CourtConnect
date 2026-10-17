document.addEventListener("DOMContentLoaded", () => {
  const form = document.getElementById("registerForm");
  const accountStep = document.getElementById("accountStep");
  const verificationStep = document.getElementById("verificationStep");
  const continueButton = document.getElementById("continueToVerification");
  const backButton = document.getElementById("backToAccount");
  const progressSteps = [
    document.getElementById("progressStep1"),
    document.getElementById("progressStep2"),
    document.getElementById("progressStep3"),
  ];
  const progressLines = [
    document.getElementById("progressLine"),
    document.getElementById("progressLine2"),
  ];
  const registrationTitle = document.getElementById("registrationTitle");
  const registrationSubtitle = document.getElementById("registrationSubtitle");
  const submitButton = document.getElementById("registrationSubmit");
  const submitLabel = document.getElementById("registrationSubmitLabel");
  const submitSpinner = document.getElementById("registrationSpinner");
  const submitHelp = document.getElementById("registrationSubmitHelp");
  const requiredUploadCount = document.getElementById("requiredUploadCount");
  const photoInput = document.getElementById("facility_photos");
  const photoPreview = document.getElementById("facilityPhotosPreview");
  const photoCount = document.getElementById("facilityPhotosCount");
  const photoUrls = new Map();
  let isSubmitting = false;

  function setProgress(step) {
    progressSteps.forEach((element, index) => {
      const completed = index + 1 < step;
      const current = index + 1 === step;
      element.classList.toggle("completed", completed);
      element.classList.toggle("active", current);
      element.toggleAttribute("aria-current", current);
      element.querySelector(".progress-circle").textContent = completed ? "✓" : String(index + 1);
    });
    progressLines.forEach((line, index) => line.classList.toggle("active", index + 1 < step));
  }

  function showAccountStep() {
    accountStep.hidden = false;
    verificationStep.hidden = true;
    registrationTitle.textContent = "Create your facility account";
    registrationSubtitle.textContent = "Start with your account details. You'll add your facility and documents next.";
    setProgress(1);
    document.getElementById("first_name").focus();
  }

  function showFacilityStep() {
    accountStep.hidden = true;
    verificationStep.hidden = false;
    registrationTitle.textContent = "Tell us about your facility";
    registrationSubtitle.textContent = "Add your facility details and upload your documents. Our team will review them before your facility goes live.";
    setProgress(2);
    document.getElementById("facility_name").focus();
    updateSubmissionState();
  }

  function setFieldError(input, message) {
    const error = document.getElementById(`${input.id}_error`);
    if (!error) return;
    error.textContent = message;
    error.hidden = !message;
    input.setAttribute("aria-invalid", String(Boolean(message)));
  }

  function validateField(input) {
    let message = "";
    if (input.id === "email" && input.value && input.validity.typeMismatch) {
      message = "Enter a valid email address.";
    } else if (input.id === "confirm_email" && input.value !== document.getElementById("email").value) {
      message = "Email addresses do not match.";
    } else if (input.id === "phone_number" && input.value && !/^09\d{9}$/.test(input.value)) {
      message = "Enter an 11-digit number starting with 09.";
    } else if (input.id === "password" && input.value.length < 8) {
      message = "Use at least 8 characters.";
    } else if (input.id === "confirm_password" && input.value && input.value !== document.getElementById("password").value) {
      message = "Passwords do not match.";
    } else if (input.required && !input.value.trim()) {
      message = "This field is required.";
    }
    setFieldError(input, message);
    return !message;
  }

  const accountInputs = [...accountStep.querySelectorAll("input[required]")];
  accountInputs.forEach((input) => {
    input.addEventListener("blur", () => validateField(input));
    input.addEventListener("input", () => {
      if (!document.getElementById(`${input.id}_error`).hidden) validateField(input);
      if (input.id === "confirm_password" && input.value) validateField(input);
      if (input.id === "password") {
        const confirmPassword = document.getElementById("confirm_password");
        if (confirmPassword.value) validateField(confirmPassword);
      }
    });
  });

  document.querySelectorAll(".owner-password-toggle").forEach((button) => {
    button.addEventListener("click", () => {
      const input = document.getElementById(button.dataset.passwordTarget);
      const show = input.type === "password";
      input.type = show ? "text" : "password";
      button.setAttribute("aria-label", show ? "Hide password" : "Show password");
      button.classList.toggle("is-visible", show);
    });
  });

  const password = document.getElementById("password");
  password.addEventListener("input", () => {
    const strength = Math.min(100, Math.round((password.value.length / 12) * 100));
    document.getElementById("passwordStrengthBar").style.width = `${strength}%`;
  });

  continueButton.addEventListener("click", () => {
    const valid = accountInputs.map(validateField).every(Boolean);
    if (!valid) {
      accountInputs.find((input) => input.getAttribute("aria-invalid") === "true")?.focus();
      return;
    }
    showFacilityStep();
  });

  backButton.addEventListener("click", showAccountStep);
  const documentInputs = [...form.querySelectorAll(".owner-upload-input:not(#facility_photos)")];
  const uploadZones = new Map();
  function fileSizeLabel(bytes) {
    return bytes < 1024 * 1024
      ? `${Math.max(1, Math.round(bytes / 1024))} KB`
      : `${(bytes / (1024 * 1024)).toFixed(2)} MB`;
  }

  function revokePhotoUrls() {
    photoUrls.forEach((url) => URL.revokeObjectURL(url));
    photoUrls.clear();
  }

  function updatePhotoGrid(files) {
    revokePhotoUrls();
    photoPreview.replaceChildren();
    files.forEach((file, index) => {
      const url = URL.createObjectURL(file);
      photoUrls.set(file, url);
      const tile = document.createElement("figure");
      tile.className = "owner-photo-tile";
      const image = document.createElement("img");
      image.src = url;
      image.alt = `Selected facility photo ${index + 1}: ${file.name}`;
      const remove = document.createElement("button");
      remove.type = "button";
      remove.className = "owner-photo-remove";
      remove.setAttribute("aria-label", `Remove ${file.name}`);
      remove.textContent = "×";
      remove.addEventListener("click", () => {
        const remaining = [...photoInput.files].filter((selected) => selected !== file);
        window.setUploadInputFiles(photoInput, remaining);
      });
      tile.append(image, remove);
      photoPreview.append(tile);
    });
    photoCount.textContent = `${files.length} ${files.length === 1 ? "photo" : "photos"} added`;
  }

  function updateSubmissionState() {
    const docsAdded = documentInputs.filter((input) => {
      const card = input.closest(".owner-document-upload-card");
      return input.files.length === 1 && !card.querySelector(".resubmit-file-error:not([hidden])");
    }).length;
    requiredUploadCount.textContent = `${docsAdded} of 4 required documents added`;
    const missing = [];
    [
      ["facility_name", "facility name"],
      ["facility_address", "facility address"],
      ["municipality", "municipality"],
      ["facility_type", "facility type"],
    ].forEach(([id, label]) => {
      if (!document.getElementById(id).value.trim()) missing.push(`enter your ${label}`);
    });
    documentInputs.forEach((input) => {
      if (!input.files.length) {
        const title = input.closest(".owner-document-upload-card").querySelector(".owner-upload-heading strong").textContent;
        missing.push(`add your ${title}`);
      }
    });
    if (!photoInput.files.length) missing.push("add at least one facility photo");
    submitButton.disabled = isSubmitting || missing.length > 0;
    submitHelp.textContent = missing.length
      ? `${missing[0][0].toUpperCase()}${missing[0].slice(1)} to continue.`
      : "Everything is ready to submit.";
  }

  documentInputs.forEach((input) => {
    const card = input.closest(".owner-document-upload-card");
    const zone = card.querySelector(".resubmit-drop-area");
    const preview = card.querySelector(".owner-upload-preview");
    const fileError = card.querySelector(".resubmit-file-error");
    const upload = window.initializeFileUploadZone({
      input,
      zone,
      error: fileError,
      allowedTypes: window.facilityUploadTypes.pdfDocuments,
      invalidTypeMessage: "Invalid file format. Please upload a PDF.",
      maxFileSize: 5 * 1024 * 1024,
      onFilesChanged: (files, message) => {
        if (upload.previewUrl) {
          URL.revokeObjectURL(upload.previewUrl);
          upload.previewUrl = null;
        }
        preview.replaceChildren();
        if (!files.length || message) {
          preview.hidden = true;
          zone.hidden = false;
          updateSubmissionState();
          return;
        }

        const file = files[0];
        const extension = file.name.split(".").pop()?.toLowerCase() || "";
        let fileVisual;
        if (file.type.startsWith("image/") || ["jpg", "jpeg", "png", "webp"].includes(extension)) {
          fileVisual = document.createElement("img");
          fileVisual.className = "resubmit-preview-image";
          fileVisual.alt = `${card.querySelector(".owner-upload-heading strong").textContent} preview`;
          const url = URL.createObjectURL(file);
          fileVisual.src = url;
          upload.previewUrl = url;
        } else {
          fileVisual = document.createElement("span");
          fileVisual.className = "resubmit-preview-pdf";
          fileVisual.textContent = extension.toUpperCase();
          fileVisual.setAttribute("aria-hidden", "true");
        }
        const metadata = document.createElement("span");
        metadata.className = "resubmit-preview-metadata";
        const name = document.createElement("strong");
        name.textContent = file.name;
        const size = document.createElement("small");
        size.textContent = fileSizeLabel(file.size);
        metadata.append(name, size);

        const statusTag = document.createElement("span");
        statusTag.className = "owner-document-tag is-accepted";
        statusTag.textContent = "✓ Added";
        const previewButton = document.createElement("button");
        previewButton.type = "button";
        previewButton.className = "resubmit-text-action";
        previewButton.textContent = "Preview";
        previewButton.addEventListener("click", () => openLocalPreview(file, card.querySelector(".owner-upload-heading strong").textContent));
        const replaceButton = document.createElement("button");
        replaceButton.type = "button";
        replaceButton.className = "resubmit-text-action";
        replaceButton.textContent = "Replace";
        replaceButton.addEventListener("click", () => {
          zone.hidden = false;
          zone.querySelector("label").focus();
        });
        const removeButton = document.createElement("button");
        removeButton.type = "button";
        removeButton.className = "resubmit-text-action";
        removeButton.textContent = "Remove";
        removeButton.addEventListener("click", () => upload.clear());
        preview.append(fileVisual, metadata, statusTag, previewButton, replaceButton, removeButton);
        preview.hidden = false;
        zone.hidden = true;
        updateSubmissionState();
      },
    });
    uploadZones.set(input, upload);
  });

  const photoZone = photoInput.closest(".resubmit-file-zone").querySelector(".resubmit-drop-area");
  window.initializeFileUploadZone({
    input: photoInput,
    zone: photoZone,
    error: document.getElementById("facility_photos_error"),
    multiple: true,
    maxFiles: 5,
    maxFileSize: 10 * 1024 * 1024,
    allowedTypes: window.facilityUploadTypes.images,
    onFilesChanged: (files) => {
      updatePhotoGrid(files);
      updateSubmissionState();
    },
  });

  const viewer = document.getElementById("ownerUploadViewer");
  const viewerDialog = viewer.querySelector('[role="dialog"]');
  const viewerContent = document.getElementById("ownerUploadViewerContent");
  const viewerTitle = document.getElementById("ownerUploadViewerTitle");
  const viewerUrls = new Set();
  function openLocalPreview(file, title) {
    viewerTitle.textContent = title;
    viewerContent.replaceChildren();
    const url = URL.createObjectURL(file);
    viewerUrls.add(url);
    if (file.type === "application/pdf" || file.name.toLowerCase().endsWith(".pdf")) {
      const object = document.createElement("object");
      object.data = url;
      object.type = "application/pdf";
      object.setAttribute("aria-label", `${title} PDF preview`);
      object.append("This browser cannot display this PDF.");
      viewerContent.append(object);
    } else {
      const image = document.createElement("img");
      image.src = url;
      image.alt = `${title} preview`;
      viewerContent.append(image);
    }
    viewer.classList.add("is-open");
    viewer.setAttribute("aria-hidden", "false");
    viewerDialog.focus();
  }
  function closeLocalPreview() {
    viewer.classList.remove("is-open");
    viewer.setAttribute("aria-hidden", "true");
    viewerContent.replaceChildren();
    viewerUrls.forEach((url) => URL.revokeObjectURL(url));
    viewerUrls.clear();
  }
  document.getElementById("ownerUploadViewerClose").addEventListener("click", closeLocalPreview);
  viewer.addEventListener("click", (event) => {
    if (event.target === viewer) closeLocalPreview();
  });
  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape" && viewer.classList.contains("is-open")) closeLocalPreview();
  });

  [
    "facility_name",
    "facility_address",
    "municipality",
    "facility_type",
  ].forEach((id) => {
    document.getElementById(id).addEventListener("input", updateSubmissionState);
    document.getElementById(id).addEventListener("change", updateSubmissionState);
  });

  form.addEventListener("submit", async (event) => {
    event.preventDefault();
    if (isSubmitting) return;
    const facilityFields = ["facility_name", "facility_address", "municipality", "facility_type"].map((id) => document.getElementById(id));
    const invalidField = facilityFields.find((input) => !input.value.trim());
    if (invalidField) {
      invalidField.focus();
      await window.showDialog({ type: "error", title: "Complete facility details", message: "Fill in all facility information before submitting." });
      return;
    }
    if (submitButton.disabled || !form.reportValidity()) return;

    isSubmitting = true;
    updateSubmissionState();
    backButton.disabled = true;
    submitSpinner.hidden = false;
    submitLabel.textContent = "Submitting...";
    try {
      const response = await fetch("includes/backend-api/adminregister.php", {
        method: "POST",
        body: new FormData(form),
      });
      const result = await response.json();
      if (!response.ok || !result.success) throw new Error(result.message || "Unable to submit your application.");
      window.location.assign("owner_application.php");
    } catch (error) {
      await window.showDialog({
        type: "error",
        title: "Unable to submit application",
        message: error.message || "Please try again.",
      });
    } finally {
      isSubmitting = false;
      backButton.disabled = false;
      submitSpinner.hidden = true;
      submitLabel.textContent = "Submit for review";
      updateSubmissionState();
    }
  });

  const query = new URLSearchParams(window.location.search);
  const resumeVerification = query.get("resumeVerification") === "1";
  if (resumeVerification) {
    const userId = query.get("user_id");
    fetch("includes/backend-api/registration_status.php")
      .then((response) => response.json())
      .then(async (result) => {
        if (!result.success) return;
        const activeUserId = userId || result.user_id;
        document.getElementById("otpForm").dataset.userId = activeUserId;
        if (result.status !== "Active") {
          window.location.assign("owner_application.php");
          return;
        }
        const response = await fetch("includes/backend-api/sendOTP.php", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ user_id: activeUserId }),
        });
        let sent;
        try {
          sent = await response.json();
        } catch {
          throw new Error("The email service did not return a valid response. Please contact support.");
        }
        if (!response.ok || !sent.success) {
          await window.showDialog({ type: "error", title: "Unable to send verification code", message: sent.message || "Please try again." });
          return;
        }
        const modal = document.getElementById("modal_container");
        modal.classList.add("show");
        modal.setAttribute("aria-hidden", "false");
        otpInputs[0].focus();
        startResendCountdown(60);
      })
      .catch(async (error) => {
        await window.showDialog({
          type: "error",
          title: "Unable to send verification code",
          message: error.message || "Please check the email service configuration and try again.",
        });
      });
  }

  const otpInputs = [...document.querySelectorAll('#inputs input[name="otp"]')];
  const otpError = document.getElementById("ownerOtpError");
  const otpForm = document.getElementById("otpForm");
  const resendLink = document.getElementById("resendOtpLink");
  const resendCountdown = document.getElementById("resendOtpCountdown");
  let resendTimer = null;
  function startResendCountdown(seconds) {
    window.clearInterval(resendTimer);
    let remaining = seconds;
    resendLink.setAttribute("aria-disabled", "true");
    const updateCountdown = () => {
      const minutes = Math.floor(remaining / 60).toString().padStart(2, "0");
      const secondsRemaining = (remaining % 60).toString().padStart(2, "0");
      resendCountdown.textContent = `(${minutes}:${secondsRemaining})`;
    };
    updateCountdown();
    resendTimer = window.setInterval(() => {
      remaining -= 1;
      if (remaining <= 0) {
        window.clearInterval(resendTimer);
        resendLink.removeAttribute("aria-disabled");
        resendCountdown.textContent = "";
        return;
      }
      updateCountdown();
    }, 1000);
  }
  document.getElementById("inputs").addEventListener("input", (event) => {
    const input = event.target;
    if (!otpInputs.includes(input)) return;
    const digits = input.value.replace(/\D/g, "");
    if (digits.length > 1) {
      const start = otpInputs.indexOf(input);
      [...digits].slice(0, 6 - start).forEach((digit, offset) => { otpInputs[start + offset].value = digit; });
      otpInputs[Math.min(start + digits.length, 5)].focus();
    } else {
      input.value = digits;
      if (digits && input.nextElementSibling) input.nextElementSibling.focus();
    }
    otpError.textContent = "";
  });
  document.getElementById("inputs").addEventListener("keydown", (event) => {
    if (event.key === "Backspace" && !event.target.value && event.target.previousElementSibling) {
      event.target.previousElementSibling.focus();
    }
  });
  document.getElementById("inputs").addEventListener("paste", (event) => {
    const pasted = event.clipboardData.getData("text").replace(/\D/g, "").slice(0, 6);
    if (!pasted) return;
    event.preventDefault();
    pasted.split("").forEach((digit, index) => { otpInputs[index].value = digit; });
    otpInputs[Math.min(pasted.length, 5)].focus();
  });
  otpForm.addEventListener("submit", async (event) => {
    event.preventDefault();
    const otp = otpInputs.map((input) => input.value).join("");
    if (otp.length !== 6) {
      otpError.textContent = "Enter all six digits of the code.";
      return;
    }
    const button = document.getElementById("submitOtpButton");
    button.disabled = true;
    button.textContent = "Verifying...";
    try {
      const response = await fetch("includes/backend-api/verifyOTP.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ user_id: otpForm.dataset.userId, otp }),
      });
      let result;
      try {
        result = await response.json();
      } catch {
        throw new Error("The email service did not return a valid response. Please contact support.");
      }
      if (!response.ok || !result.success) throw new Error(result.message || "Unable to verify this code.");
      await window.showDialog({ type: "success", title: "Email verified", message: result.message || "Account verified successfully." });
      window.location.assign("signin.php");
    } catch (error) {
      otpError.textContent = error.message || "Unable to verify this code.";
    } finally {
      button.disabled = false;
      button.textContent = "Submit Code";
    }
  });
  resendLink.addEventListener("click", async (event) => {
    event.preventDefault();
    if (resendLink.getAttribute("aria-disabled") === "true") return;
    try {
      const response = await fetch("includes/backend-api/sendOTP.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ user_id: otpForm.dataset.userId }),
      });
      const result = await response.json();
      if (!response.ok || !result.success) throw new Error(result.message || "Unable to resend the code.");
      otpError.textContent = "";
      startResendCountdown(60);
    } catch (error) {
      otpError.textContent = error.message || "Unable to resend the code.";
    }
  });

  window.addEventListener("beforeunload", () => {
    photoUrls.forEach((url) => URL.revokeObjectURL(url));
    viewerUrls.forEach((url) => URL.revokeObjectURL(url));
    uploadZones.forEach((upload) => { if (upload.previewUrl) URL.revokeObjectURL(upload.previewUrl); });
  });
  updateSubmissionState();
});

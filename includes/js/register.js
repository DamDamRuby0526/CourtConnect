const resumeVerification = new URLSearchParams(window.location.search).get("resumeVerification") === "1";
const resumeUserId = new URLSearchParams(window.location.search).get("user_id");
const resendOtpLink = document.getElementById("resendOtpLink");
const resendOtpCountdown = document.getElementById("resendOtpCountdown");
const registerForm = document.getElementById("registerForm");
const registerSubmit = document.getElementById("registerSubmit");
const registrationSubmitHint = document.getElementById("registrationSubmitHint");
const consentModal = document.getElementById("consentModal");
const consentDialog = consentModal.querySelector('[role="dialog"]');
const consentNoticeContent = document.getElementById("consentNoticeContent");
const consentCheckbox = document.getElementById("consentAgreement");
const acceptConsentButton = document.getElementById("acceptConsent");
const registrationConsent = document.getElementById("registrationConsent");
const registrationConsentIndicator = document.getElementById("registrationConsentIndicator");
const consentScrollHint = document.getElementById("consentScrollHint");
const consentSectionEnds = {
  privacy: document.getElementById("privacyNoticeEnd"),
  terms: document.getElementById("termsEnd"),
};
const registrationFields = Array.from(registerForm.querySelectorAll(".account-auth-field input"));
const reviewedDocuments = { privacy: false, terms: false };
let resendOtpTimer = null;
let consentAccepted = false;
let consentTrigger = null;
let submittingRegistration = false;
let previousBodyOverflow = "";
let consentUserScrollTimer = null;
let consentUserInitiatedScroll = false;
let registrationValidationAttempted = false;
let emailConflict = null;

registrationConsentIndicator.checked = false;
registrationConsent.value = "";

registrationFields.forEach((field) => {
  const error = document.createElement("div");
  error.id = `${field.id}Error`;
  error.className = "account-auth-field-error";
  error.hidden = true;
  error.setAttribute("aria-live", "polite");
  if (field.id === "email") {
    const title = document.createElement("span");
    title.className = "account-auth-field-error-title";
    const icon = document.createElementNS("http://www.w3.org/2000/svg", "svg");
    icon.setAttribute("viewBox", "0 0 16 16");
    icon.setAttribute("aria-hidden", "true");
    icon.innerHTML = '<path d="M8 1.75 14.25 13H1.75L8 1.75Z"></path><path d="M8 5.25v3.5M8 11.25v.1"></path>';
    const heading = document.createElement("strong");
    heading.textContent = "Email already registered";
    title.append(icon, heading);
    const description = document.createElement("span");
    description.className = "account-auth-field-error-description";
    description.textContent = "This email address is associated with an existing account.";
    const validationMessage = document.createElement("span");
    validationMessage.className = "account-auth-field-error-validation";
    validationMessage.hidden = true;
    error.append(title, description, validationMessage);
  }
  field.insertAdjacentElement("afterend", error);
  field.setAttribute("aria-describedby", error.id);
  field.setAttribute("aria-invalid", "false");
});

function noteConsentUserScroll() {
  consentUserInitiatedScroll = true;
  window.clearTimeout(consentUserScrollTimer);
  consentUserScrollTimer = window.setTimeout(() => {
    consentUserInitiatedScroll = false;
  }, 200);
}

function updateConsentScrollState() {
  if (!consentUserInitiatedScroll) return;
  const contentTop = consentNoticeContent.getBoundingClientRect().top;
  const reviewBoundary = contentTop + consentNoticeContent.clientHeight - 8;
  for (const [documentName, endMarker] of Object.entries(consentSectionEnds)) {
    if (endMarker.getBoundingClientRect().top <= reviewBoundary) {
      reviewedDocuments[documentName] = true;
    }
  }

  const bothReviewed = reviewedDocuments.privacy && reviewedDocuments.terms;
  consentCheckbox.disabled = !bothReviewed;
  acceptConsentButton.disabled = !bothReviewed || !consentCheckbox.checked;
  if (bothReviewed) {
    consentScrollHint.textContent = "Both sections reviewed. You may now confirm your agreement.";
  } else {
    consentScrollHint.textContent = "Scroll through both sections to continue.";
  }
  updateRegistrationState();
}

function getRegistrationFieldErrors() {
  const fields = {
    email: document.getElementById("email"),
    confirmEmail: document.getElementById("confirm_email"),
    password: document.getElementById("password"),
    confirmPassword: document.getElementById("confirm_password"),
  };
  fields.confirmEmail.setCustomValidity(
    fields.confirmEmail.value && fields.confirmEmail.value !== fields.email.value
      ? "Email addresses do not match."
      : ""
  );
  fields.confirmPassword.setCustomValidity(
    fields.confirmPassword.value && fields.confirmPassword.value !== fields.password.value
      ? "Passwords do not match."
      : ""
  );
  return registrationFields
    .filter((field) => !field.validity.valid)
    .map((field) => {
      const label = registerForm.querySelector(`label[for="${field.id}"]`).textContent.trim();
      let message = "This field is required.";
      if (field.id === "confirm_email" && fields.email.value && field.value
        && field.value !== fields.email.value) {
        message = "Email addresses do not match.";
      } else if (field.id === "confirm_password" && fields.password.value && field.value
        && field.value !== fields.password.value) {
        message = "Passwords do not match.";
      } else if (field.validity.valueMissing) {
        message = "This field is required.";
      } else if (field.id === "phone_number" && field.validity.patternMismatch) {
        message = "Enter an 11-digit mobile number starting with 09.";
      } else if (field.type === "email" && field.validity.typeMismatch) {
        message = "Enter a valid email address.";
      } else if (field.type === "password" && field.validity.tooShort) {
        message = `Use at least ${field.minLength} characters.`;
      }
      return { field, label, message };
    });
}

function updateRegistrationState() {
  const fieldErrors = getRegistrationFieldErrors();
  registrationFields.forEach((field) => {
    const error = document.getElementById(`${field.id}Error`);
    const fieldError = fieldErrors.find(({ field: invalidField }) => invalidField === field);
    const showEmailConflict = field.id === "email"
      && emailConflict !== null
      && field.value.trim() === emailConflict;
    const showError = showEmailConflict
      || ((registrationValidationAttempted || field.dataset.touched === "true") && Boolean(fieldError));
    if (showEmailConflict) {
      error.classList.add("is-email-conflict");
      error.querySelector(".account-auth-field-error-title").hidden = false;
      error.querySelector(".account-auth-field-error-description").hidden = false;
      error.querySelector(".account-auth-field-error-validation").hidden = true;
    } else {
      error.classList.remove("is-email-conflict");
      if (field.id === "email") {
        error.querySelector(".account-auth-field-error-title").hidden = true;
        error.querySelector(".account-auth-field-error-description").hidden = true;
        const validationMessage = error.querySelector(".account-auth-field-error-validation");
        validationMessage.textContent = showError ? fieldError.message : "";
        validationMessage.hidden = !showError;
      } else {
        error.textContent = showError ? fieldError.message : "";
      }
    }
    error.hidden = !showError;
    field.setAttribute("aria-invalid", String(showError));
  });
  const consentReady = consentAccepted
    && registrationConsent.value === "accepted"
    && reviewedDocuments.privacy
    && reviewedDocuments.terms;
  registerSubmit.disabled = submittingRegistration || !consentReady;
  if (submittingRegistration) {
    registrationSubmitHint.textContent = "Submitting your registration...";
    return;
  }

  const nextRequirements = [];
  if (fieldErrors.length) nextRequirements.push("Complete the required fields before continuing.");
  if (!reviewedDocuments.privacy || !reviewedDocuments.terms) {
    nextRequirements.push("Review both documents.");
  } else if (!consentReady) {
    nextRequirements.push("Check the agreement box in the privacy dialog to enable Next.");
  }
  registrationSubmitHint.textContent = nextRequirements.join(" ");
}

function clearEmailConflict() {
  if (emailConflict === null) return;
  emailConflict = null;
  updateRegistrationState();
}

function closeConsentNotice() {
  consentModal.classList.remove("show");
  consentModal.setAttribute("aria-hidden", "true");
  document.body.classList.remove("consent-modal-open");
  document.body.style.overflow = previousBodyOverflow;
  window.clearTimeout(consentUserScrollTimer);
  consentUserInitiatedScroll = false;
  consentCheckbox.checked = false;
  consentCheckbox.disabled = true;
  acceptConsentButton.disabled = true;
  if (consentTrigger) consentTrigger.focus();
}

function openConsentNotice(trigger) {
  window.clearTimeout(consentUserScrollTimer);
  consentUserInitiatedScroll = false;
  consentAccepted = false;
  registrationConsent.value = "";
  registrationConsentIndicator.checked = false;
  updateRegistrationState();
  consentTrigger = trigger;
  consentCheckbox.checked = false;
  previousBodyOverflow = document.body.style.overflow;
  document.body.style.overflow = "hidden";
  document.body.classList.add("consent-modal-open");
  consentModal.classList.add("show");
  consentModal.setAttribute("aria-hidden", "false");
  consentNoticeContent.scrollTop = 0;
  consentCheckbox.disabled = !(reviewedDocuments.privacy && reviewedDocuments.terms);
  acceptConsentButton.disabled = true;
  consentNoticeContent.focus();
}

registrationConsentIndicator.addEventListener("change", () => {
  registrationConsentIndicator.checked = false;
  openConsentNotice(registrationConsentIndicator);
});
document.getElementById("closeConsentNotice").addEventListener("click", closeConsentNotice);
document.getElementById("declineConsent").addEventListener("click", () => {
  closeConsentNotice();
});
consentNoticeContent.addEventListener("wheel", noteConsentUserScroll, { passive: true });
consentNoticeContent.addEventListener("touchmove", noteConsentUserScroll, { passive: true });
consentNoticeContent.addEventListener("pointerdown", (event) => {
  const contentRect = consentNoticeContent.getBoundingClientRect();
  const scrollbarWidth = consentNoticeContent.offsetWidth - consentNoticeContent.clientWidth;
  if (scrollbarWidth > 0 && event.clientX >= contentRect.right - scrollbarWidth) {
    noteConsentUserScroll();
  }
});
consentNoticeContent.addEventListener("keydown", (event) => {
  if (["ArrowDown", "ArrowUp", "PageDown", "PageUp", "Home", "End", " "].includes(event.key)) {
    noteConsentUserScroll();
  }
});
consentNoticeContent.addEventListener("scroll", updateConsentScrollState);
consentCheckbox.addEventListener("change", () => {
  acceptConsentButton.disabled = !(reviewedDocuments.privacy && reviewedDocuments.terms && consentCheckbox.checked);
});
acceptConsentButton.addEventListener("click", () => {
  if (!reviewedDocuments.privacy || !reviewedDocuments.terms || !consentCheckbox.checked) return;
  consentAccepted = true;
  registrationConsent.value = "accepted";
  registrationConsentIndicator.checked = true;
  updateRegistrationState();
  closeConsentNotice();
});
registerForm.querySelectorAll(".account-auth-field input").forEach((input) => {
  input.addEventListener("focus", () => {
    if (input.id === "email") clearEmailConflict();
  });
  input.addEventListener("blur", () => {
    input.dataset.touched = "true";
    updateRegistrationState();
  });
  input.addEventListener("input", () => {
    if (input.id === "email") clearEmailConflict();
    updateRegistrationState();
  });
  input.addEventListener("change", () => {
    if (input.id === "email") clearEmailConflict();
    updateRegistrationState();
  });
});
registerForm.addEventListener("submit", () => {
  registrationValidationAttempted = true;
  updateRegistrationState();
});
updateRegistrationState();
consentModal.addEventListener("click", (event) => {
  if (event.target === consentModal) closeConsentNotice();
});
document.addEventListener("keydown", (event) => {
  if (!consentModal.classList.contains("show")) return;
  if (event.key === "Escape") {
    closeConsentNotice();
    return;
  }
  if (event.key !== "Tab") return;

  const focusableElements = Array.from(
    consentDialog.querySelectorAll('button:not([disabled]), input:not([disabled]), a[href], [tabindex="0"]')
  ).filter((element) => element.offsetParent !== null);
  const firstElement = focusableElements[0];
  const lastElement = focusableElements[focusableElements.length - 1];
  if (event.shiftKey && document.activeElement === firstElement) {
    event.preventDefault();
    lastElement.focus();
  } else if (!event.shiftKey && document.activeElement === lastElement) {
    event.preventDefault();
    firstElement.focus();
  }
});

function startOtpResendCountdown(seconds) {
  window.clearInterval(resendOtpTimer);
  let remaining = seconds;
  resendOtpLink.setAttribute("aria-disabled", "true");
  const updateCountdown = () => {
    const minutes = Math.floor(remaining / 60).toString().padStart(2, "0");
    const secondsRemaining = (remaining % 60).toString().padStart(2, "0");
    resendOtpCountdown.textContent = `(${minutes}:${secondsRemaining})`;
  };
  updateCountdown();
  resendOtpTimer = window.setInterval(() => {
    remaining -= 1;
    if (remaining <= 0) {
      window.clearInterval(resendOtpTimer);
      resendOtpLink.removeAttribute("aria-disabled");
      resendOtpCountdown.textContent = "";
      return;
    }
    updateCountdown();
  }, 1000);
}

async function resumeOtpVerification(userId) {
  const otpForm = document.getElementById("otpForm");
  otpForm.dataset.userId = userId;
  document.getElementById("modal_container").classList.add("show");

  const sendResponse = await fetch("includes/backend-api/sendOTP.php", {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
    },
    body: JSON.stringify({ user_id: userId }),
  });

  const sendResult = await sendResponse.json();
  if (!sendResult.success) {
    resendOtpLink.removeAttribute("aria-disabled");
    resendOtpCountdown.textContent = "";
    alert(sendResult.message);
    return;
  }

  if (sendResult.otp_code) {
    alert(`Development OTP: ${sendResult.otp_code}`);
  }
  startOtpResendCountdown(60);
}

document.addEventListener("DOMContentLoaded", async () => {
  if (resumeVerification && resumeUserId) {
    const registerForm = document.getElementById("registerForm");
    if (registerForm) {
      registerForm.style.display = "none";
    }
    await resumeOtpVerification(resumeUserId);
  }
});

document
  .getElementById("registerForm")
  .addEventListener("submit", async (e) => {
    e.preventDefault();
    const fieldErrors = getRegistrationFieldErrors();
    registrationValidationAttempted = true;
    updateRegistrationState();
    if (fieldErrors.length) {
      registrationSubmitHint.textContent = `Please correct the following: ${fieldErrors.map(({ label, message }) => `${label}: ${message}`).join(" ")}`;
      fieldErrors[0].field.focus();
      return;
    }

    if (!consentAccepted || registrationConsent.value !== "accepted"
      || !reviewedDocuments.privacy || !reviewedDocuments.terms) {
      registrationSubmitHint.textContent = "Review both documents and confirm your agreement before continuing.";
      return;
    }

    const form = new FormData(e.target);

    //get data from form
    const data = {
      first_name: form.get("first_name"),
      last_name: form.get("last_name"),
      email: form.get("email"),
      phone_number: form.get("phone_number"),
      password: form.get("password"),
      consent: registrationConsent.value,
      privacy_notice_version: document.getElementById("privacyNoticeVersion").value,
      terms_version: document.getElementById("termsVersion").value,
    };

    emailConflict = null;
    submittingRegistration = true;
    updateRegistrationState();
    let response;
    let result;
    let requestError = "";
    try {
      response = await fetch("includes/backend-api/register.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
      });
      result = await response.json();
      if (!result || typeof result !== "object") {
        throw new Error("The registration service returned an invalid response. Please try again.");
      }
    } catch (error) {
      requestError = error instanceof Error
        ? error.name === "TypeError"
          ? "Unable to reach the registration service. Please try again."
          : error.message
        : "Unable to complete registration. Please try again.";
    } finally {
      submittingRegistration = false;
      updateRegistrationState();
    }

    if (requestError) {
      registrationSubmitHint.textContent = requestError;
      return;
    }

    if (response.status === 409 && result.code === "email_exists" && result.field === "email") {
      document.getElementById("email").focus();
      emailConflict = String(data.email).trim();
      updateRegistrationState();
      return;
    }

    if (!response.ok || !result.success) {
      registrationSubmitHint.textContent = result.message || "Unable to complete registration. Please try again.";
      return;
    }

    // open OTP modal
    const otpForm = document.getElementById("otpForm");
    otpForm.dataset.userId = result.user_id;

    document.getElementById("modal_container").classList.add("show");

    // Automatically send the OTP as soon as the modal opens
    try {
      const sendResponse = await fetch("includes/backend-api/sendOTP.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({ user_id: result.user_id }),
      });
      let sendResult;
      try {
        sendResult = await sendResponse.json();
      } catch {
        throw new Error("The email service did not return a valid response. Please contact support.");
      }
      if (!sendResponse.ok || !sendResult.success) {
        throw new Error(sendResult.message || "Unable to send the verification code.");
      }
      startOtpResendCountdown(60);
    } catch (error) {
      resendOtpLink.removeAttribute("aria-disabled");
      resendOtpCountdown.textContent = "";
      alert(error.message || "Unable to send the verification code.");
    }
  });

resendOtpLink.addEventListener("click", async (event) => {
  event.preventDefault();
  if (resendOtpLink.getAttribute("aria-disabled") === "true") return;
  try {
    const response = await fetch("includes/backend-api/sendOTP.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ user_id: document.getElementById("otpForm").dataset.userId }),
    });
    let result;
    try {
      result = await response.json();
    } catch {
      throw new Error("The email service did not return a valid response. Please contact support.");
    }
    if (!response.ok || !result.success) throw new Error(result.message || "Unable to resend the code.");
    startOtpResendCountdown(60);
  } catch (error) {
    alert(error.message || "Unable to resend the code.");
  }
});

// Move through the six OTP fields as the user types.

const inputs = document.getElementById("inputs");
const otpInputs = Array.from(inputs.querySelectorAll('input[name="otp"]'));

inputs.addEventListener("input", function (e) {
  const target = e.target;
  const val = target.value.replace(/\D/g, "").slice(-1);

  target.value = val;

  if (val != "") {
    const next = target.nextElementSibling;
    if (next) {
      next.focus();
    }
  }
});

inputs.addEventListener("keyup", function (e) {
  const target = e.target;
  const key = e.key.toLowerCase();

  if (key == "backspace" || key == "delete") {
    target.value = "";
    const prev = target.previousElementSibling;
    if (prev) {
      prev.focus();
    }
    return;
  }
});

// form actions

document.getElementById("otpForm").addEventListener("submit", async (e) => {
  e.preventDefault();

  const otpForm = e.target;
  const userId = otpForm.dataset.userId;
  const otp = otpInputs.map((input) => input.value).join("");
  const btn = document.getElementById("submitOtpButton");

  if (otp.length !== 6) {
    alert("Please enter all 6 digits of the code sent to your email.");
    return;
  }

  // disable button after submitting once
  btn.disabled = true;
  btn.innerText = "Submitting...";

  try {
    const response = await fetch("includes/backend-api/verifyOTP.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify({
        user_id: userId,
        otp: otp,
      }),
    });

    let result;
    try {
      result = await response.json();
    } catch {
      throw new Error("The email service did not return a valid response. Please contact support.");
    }
    if (result.success) {
      document.getElementById("modal_container").classList.remove("show");
      const notification = document.createElement("div");
      const loginRedirectDelay = 2000;
      const hasPendingReservation = document.body.dataset.pendingReservation === "true";
      notification.className = "login-success-overlay";
      notification.style.setProperty("--login-redirect-delay", `${loginRedirectDelay}ms`);
      notification.setAttribute("role", "status");
      const card = document.createElement("div");
      card.className = "login-success-card";
      const icon = document.createElement("span");
      icon.className = "login-success-icon";
      icon.setAttribute("aria-hidden", "true");
      icon.textContent = "\u2713";
      const welcome = document.createElement("strong");
      welcome.textContent = `Welcome, ${result.user.first_name}!`;
      const destinationMessage = document.createElement("small");
      destinationMessage.textContent = `Taking you to ${hasPendingReservation ? "your selected reservation" : "your home page"}.`;
      const loader = document.createElement("span");
      loader.className = "login-success-loader";
      loader.setAttribute("aria-hidden", "true");
      card.append(icon, welcome, destinationMessage, loader);
      notification.appendChild(card);
      document.body.appendChild(notification);

      window.setTimeout(() => {
        window.location.href = hasPendingReservation ? "reservation_resume.php" : "index.php";
      }, loginRedirectDelay);
    } else {
      alert(result.message || "Unable to verify this code.");
    }
  } catch (error) {
    alert("Network error. Please try again.");
    btn.disabled = false;
    btn.innerText = "Submit Code";
  }
});

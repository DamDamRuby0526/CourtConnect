function checkoutDialog(title, message, type = "success") {
  if (typeof window.showDialog !== "function") return Promise.resolve(false);
  return window.showDialog({ type, title, message });
}

function renderCheckoutConfirmation(bookingId) {
  const layout = document.querySelector(".checkout-layout");
  if (!layout) return;
  const confirmation = document.createElement("section");
  confirmation.className = "checkout-state checkout-confirmation";
  confirmation.setAttribute("aria-live", "polite");
  const eyebrow = document.createElement("p");
  eyebrow.className = "player-eyebrow";
  eyebrow.textContent = "PAYMENT SUBMITTED";
  const heading = document.createElement("h2");
  heading.textContent = `Booking reference #${bookingId}`;
  const nextStep = document.createElement("p");
  nextStep.className = "checkout-next-step";
  const nextTitle = document.createElement("strong");
  nextTitle.textContent = "What happens next";
  const nextText = document.createElement("span");
  nextText.textContent = "The facility will confirm your payment.";
  nextStep.append(nextTitle, nextText);
  const links = document.createElement("div");
  links.className = "checkout-confirmation-links";
  const bookingsLink = document.createElement("a");
  bookingsLink.className = "player-primary-link";
  bookingsLink.href = "mybookings.php";
  bookingsLink.textContent = "My bookings";
  const facilitiesLink = document.createElement("a");
  facilitiesLink.className = "player-secondary-link";
  facilitiesLink.href = "facility.php";
  facilitiesLink.textContent = "Find a court";
  links.append(bookingsLink, facilitiesLink);
  confirmation.append(eyebrow, heading, nextStep, links);
  layout.replaceWith(confirmation);
}

function renderExpiredCheckout(message) {
  const layout = document.querySelector(".checkout-layout");
  if (!layout) return;
  const expired = document.createElement("section");
  expired.className = "checkout-state checkout-expired";
  const heading = document.createElement("h2");
  heading.textContent = "Your booking expired and the slot was released";
  const description = document.createElement("p");
  description.textContent = message || "Your booking expired and the slot was released.";
  const pickAnother = document.createElement("a");
  pickAnother.className = "player-primary-link";
  pickAnother.href = "facility.php";
  pickAnother.textContent = "Pick another time";
  expired.append(heading, description, pickAnother);
  layout.replaceWith(expired);
  checkoutDialog("Booking expired", description.textContent, "error");
}

async function checkCheckoutExpiry(timer) {
  try {
    const response = await fetch("includes/backend-api/expire_booking.php", {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: new URLSearchParams({
        booking_id: document.querySelector(".checkout-page").dataset.bookingId,
      }),
    });
    const result = await response.json();
    if (!response.ok || !result.success) throw new Error(result.message || "Unable to check the booking hold.");
    if (result.expired) {
      renderExpiredCheckout(result.message);
      return;
    }
    if (Number(result.hold_expires_epoch) > Number(result.server_now_epoch)) {
      timer.dataset.expires = result.hold_expires_epoch;
      timer.dataset.serverNow = result.server_now_epoch;
      timer.classList.remove("is-expired");
      startHoldCountdown();
      return;
    }
    renderExpiredCheckout(result.message);
  } catch (error) {
    checkoutDialog("Unable to check booking", error.message || "Please retry in a moment.", "error");
    window.setTimeout(() => checkCheckoutExpiry(timer), 1500);
  }
}

function startHoldCountdown() {
  const timer = document.getElementById("holdTimer");
  if (!timer) return;
  const expiresAt = Number(timer.dataset.expires) * 1000;
  const serverNow = Number(timer.dataset.serverNow) * 1000;
  const deadline = performance.now() + Math.max(0, expiresAt - serverNow);
  let interval;
  const update = () => {
    if (!timer.isConnected) {
      window.clearInterval(interval);
      return;
    }
    const seconds = Math.max(0, Math.ceil((deadline - performance.now()) / 1000));
    const minutesText = String(Math.floor(seconds / 60)).padStart(2, "0");
    const secondsText = String(seconds % 60).padStart(2, "0");
    timer.textContent = seconds > 0 ? `Slot held for ${minutesText}:${secondsText}` : "This slot hold has expired.";
    if (seconds === 0) {
      timer.classList.add("is-expired");
      window.clearInterval(interval);
      checkCheckoutExpiry(timer);
    }
  };
  update();
  interval = window.setInterval(update, 1000);
}

document.addEventListener("DOMContentLoaded", () => {
  startHoldCountdown();

  const qrOpenButton = document.getElementById("checkoutQrOpen");
  const qrLightbox = document.getElementById("checkoutQrLightbox");
  const qrCloseButton = document.getElementById("checkoutQrClose");
  const closeQrLightbox = () => {
    if (!qrLightbox) return;
    qrLightbox.hidden = true;
    qrLightbox.setAttribute("aria-hidden", "true");
    qrOpenButton?.focus();
  };

  qrOpenButton?.addEventListener("click", () => {
    qrLightbox.hidden = false;
    qrLightbox.setAttribute("aria-hidden", "false");
    qrCloseButton.focus();
  });
  qrCloseButton?.addEventListener("click", closeQrLightbox);
  qrLightbox?.addEventListener("click", (event) => {
    if (event.target === qrLightbox) closeQrLightbox();
  });
  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape" && qrLightbox && !qrLightbox.hidden) closeQrLightbox();
  });

  const cancelButton = document.getElementById("cancelCheckoutBooking");
  cancelButton?.addEventListener("click", async () => {
    const checkoutPage = document.querySelector(".checkout-page");
    const isGroupedReservation = Number(checkoutPage.dataset.bookingGroupId) > 0;
    const confirmed = await window.showDialog({
      type: "confirm",
      title: isGroupedReservation ? "Cancel this reservation?" : "Cancel this booking?",
      message: isGroupedReservation
        ? "All selected time slots will be released and this reservation will be removed."
        : "The time slot will be released and this booking will be removed.",
      confirmText: "Yes, cancel",
      cancelText: "Keep booking",
    });
    if (!confirmed) return;

    const submitButton = document.querySelector("#checkoutPaymentForm button[type='submit']");
    cancelButton.disabled = true;
    cancelButton.textContent = "Cancelling...";
    if (submitButton) submitButton.disabled = true;
    try {
      const response = await fetch("includes/backend-api/cancel_checkout_booking.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: new URLSearchParams(isGroupedReservation
          ? { booking_group_id: checkoutPage.dataset.bookingGroupId }
          : { booking_id: checkoutPage.dataset.bookingId }),
      });
      const result = await response.json();
      if (!response.ok || !result.success) {
        const error = new Error(result.message || "Unable to cancel this booking.");
        error.code = result.code;
        throw error;
      }
      await window.showDialog({
        type: "success",
        title: "Booking cancelled",
        message: result.message,
      });
      const facilityId = Number(result.facility_id);
      window.location.href = facilityId > 0
        ? `courts.php?facility_id=${encodeURIComponent(facilityId)}`
        : "mybookings.php";
    } catch (error) {
      await window.showDialog({
        type: "error",
        title: error.code === "too_late" ? "Cancellation window passed" : "Unable to cancel booking",
        message: error.message || "Please try again.",
      });
      cancelButton.disabled = false;
      cancelButton.textContent = "Cancel booking";
      if (submitButton) submitButton.disabled = false;
    }
  });

  const form = document.getElementById("checkoutPaymentForm");
  form?.addEventListener("submit", async (event) => {
    event.preventDefault();
    const receipt = form.elements.receipt.files[0];
    if (!receipt) {
      form.elements.receipt.reportValidity();
      return;
    }
    if (receipt.size > 5 * 1024 * 1024) {
      checkoutDialog("Receipt too large", "Receipt images must be 5 MB or smaller.", "error");
      return;
    }

    const submitButton = form.querySelector("button[type='submit']");
    const formData = new FormData(form);
    const checkoutPage = document.querySelector(".checkout-page");
    if (Number(checkoutPage.dataset.bookingGroupId) > 0) {
      formData.append("booking_group_id", checkoutPage.dataset.bookingGroupId);
    } else {
      formData.append("booking_id", checkoutPage.dataset.bookingId);
    }
    submitButton.disabled = true;
    submitButton.textContent = "Submitting...";
    try {
      const response = await fetch("includes/backend-api/submit_checkout_payment.php", {
        method: "POST",
        body: formData,
      });
      const result = await response.json();
      if (!response.ok || !result.success) throw new Error(result.message || "Unable to submit payment.");
      await checkoutDialog("Payment submitted", result.message);
      renderCheckoutConfirmation(document.querySelector(".checkout-page").dataset.bookingId);
    } catch (error) {
      await checkoutDialog("Payment not submitted", error.message || "Please try again.", "error");
      submitButton.disabled = false;
      submitButton.textContent = "Submit payment";
    }
  });

});
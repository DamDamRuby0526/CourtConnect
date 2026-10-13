const bookingList = document.getElementById("playerBookingList");
let allBookings = [];
const bookingExpiryTimers = new Set();
let activeRejectedBooking = null;
const rejectionReasonModal = document.getElementById("rejectionReasonModal");
const paymentResubmissionModal = document.getElementById("paymentResubmissionModal");
const paymentResubmissionForm = document.getElementById("paymentResubmissionForm");

function toastBooking(title, message, type = "success") {
  if (typeof window.showToast === "function") window.showToast(title, message, type);
}

function bookingDateTime(booking) {
  const [hours, minutes] = booking.court_time.split(":").map(Number);
  const time = new Date(2000, 0, 1, hours, minutes).toLocaleTimeString(undefined, {
    hour: "numeric",
    minute: "2-digit",
  });
  const date = new Date(`${booking.court_date}T12:00:00`).toLocaleDateString(undefined, {
    weekday: "short",
    month: "short",
    day: "numeric",
    year: "numeric",
  });
  return { date, time };
}

function makeStatusBadge(label, className) {
  const badge = document.createElement("span");
  badge.className = `player-status-badge ${className}`;
  badge.textContent = label;
  return badge;
}

function makeBookingCard(booking) {
  const card = document.createElement("article");
  card.className = "player-booking-card";
  const heading = document.createElement("div");
  heading.className = "player-booking-heading";
  const title = document.createElement("div");
  const facility = document.createElement("h2");
  facility.textContent = booking.facility_name;
  const court = document.createElement("p");
  court.textContent = `Court ${booking.court_no} · ${booking.municipality || "Bataan"}`;
  title.append(facility, court);
  const badges = document.createElement("div");
  badges.className = "player-booking-badges";
  const bookingLabels = {
    pending_payment: ["Payment required", "status-needs-payment"],
    pending: ["Awaiting confirmation", "status-awaiting"],
    confirmed: ["Confirmed", "status-confirmed"],
    cancelled: ["Cancelled", "status-neutral"],
    completed: ["Completed", "status-neutral"],
  };
  const [bookingLabel, bookingClass] = bookingLabels[booking.booking_status] || [booking.booking_status, "status-neutral"];
  const paymentLabel = booking.payment_status === "Rejected" ? "Payment Rejected" : booking.payment_status;
  badges.append(makeStatusBadge(paymentLabel, `payment-${booking.payment_status.toLowerCase()}`));
  badges.append(makeStatusBadge(bookingLabel, bookingClass));
  if (booking.is_past && booking.attendance_status) {
    const attendanceClasses = {
      Arrived: "attendance-arrived",
      "Did not arrive": "attendance-no-show",
      "Awaiting attendance": "attendance-awaiting",
    };
    badges.append(makeStatusBadge(booking.attendance_status, attendanceClasses[booking.attendance_status] || "status-neutral"));
  }
  heading.append(title, badges);

  const details = document.createElement("dl");
  details.className = "player-booking-details";
  const dateTime = bookingDateTime(booking);
  const detailItems = [
    ["Date", dateTime.date],
    ["Time", dateTime.time],
    ["Duration", `${booking.slot_duration} min`],
    ["Amount", `P${Number(booking.total_amount).toFixed(2)}`],
  ];
  detailItems.forEach(([label, value]) => {
    const item = document.createElement("div");
    const term = document.createElement("dt");
    const description = document.createElement("dd");
    term.textContent = label;
    description.textContent = value;
    item.append(term, description);
    details.append(item);
  });

  const actions = document.createElement("div");
  actions.className = "player-booking-actions";
  if (booking.can_checkout) {
    const checkout = document.createElement("a");
    checkout.className = "player-primary-link";
    checkout.href = `checkout.php?booking_id=${encodeURIComponent(booking.booking_id)}`;
    checkout.textContent = "Complete payment";
    actions.append(checkout);
    const expiry = document.createElement("span");
    expiry.className = "player-hold-expiry";
    expiry.dataset.bookingId = booking.booking_id;
    expiry.dataset.expires = booking.hold_expires_epoch;
    expiry.dataset.serverNow = booking.server_now_epoch;
    expiry.textContent = "Expires in --:--";
    actions.append(expiry);
  }
  if (booking.can_cancel) {
    const cancel = document.createElement("button");
    cancel.type = "button";
    cancel.className = "player-secondary-link";
    cancel.dataset.cancelBookingId = booking.booking_id;
    cancel.textContent = "Cancel booking";
    actions.append(cancel);
  }
  if (booking.payment_status === "Rejected" && booking.payment_id) {
    const rejectionButton = document.createElement("button");
    rejectionButton.type = "button";
    rejectionButton.className = "player-secondary-link";
    rejectionButton.dataset.rejectedPaymentBookingId = booking.booking_id;
    rejectionButton.textContent = "View Rejection Reason";
    actions.append(rejectionButton);
  }
  if (booking.has_receipt && booking.payment_id) {
    const receipt = document.createElement("a");
    receipt.className = "player-secondary-link";
    receipt.href = `includes/admin-php/view_payment_receipt.php?payment_id=${encodeURIComponent(booking.payment_id)}`;
    receipt.target = "_blank";
    receipt.rel = "noopener noreferrer";
    receipt.textContent = "View receipt";
    actions.append(receipt);
  }
  card.append(heading, details);
  if (actions.childElementCount) card.append(actions);
  return card;
}

function renderBookingTab(tab) {
  bookingExpiryTimers.forEach((timer) => window.clearInterval(timer));
  bookingExpiryTimers.clear();
  bookingList.dataset.activeTab = tab;
  bookingList.replaceChildren();
  const matchingBookings = allBookings.filter((booking) => tab === "past" ? booking.is_past : !booking.is_past);
  if (!allBookings.length) {
    const empty = document.createElement("div");
    empty.className = "player-empty-state";
    const heading = document.createElement("h2");
    heading.textContent = "No bookings yet";
    const copy = document.createElement("p");
    copy.textContent = "Choose a court and reserve your first time.";
    const link = document.createElement("a");
    link.className = "player-primary-link";
    link.href = "facility.php";
    link.textContent = "Find a court";
    empty.append(heading, copy, link);
    bookingList.append(empty);
    return;
  }
  if (!matchingBookings.length) {
    const empty = document.createElement("p");
    empty.className = "player-list-empty";
    empty.textContent = tab === "past" ? "No past bookings." : "No upcoming bookings.";
    bookingList.append(empty);
    return;
  }
  matchingBookings.forEach((booking) => bookingList.append(makeBookingCard(booking)));
  startBookingExpiryTimers();
}

function startBookingExpiryTimers() {
  bookingList.querySelectorAll(".player-hold-expiry").forEach((label) => {
    startBookingExpiryTimer(label);
  });
}

function startBookingExpiryTimer(label) {
  const deadline = performance.now()
    + Math.max(0, Number(label.dataset.expires) * 1000 - Number(label.dataset.serverNow) * 1000);
  let timer;
  const update = async () => {
    const remaining = Math.max(0, Math.ceil((deadline - performance.now()) / 1000));
    const minutes = String(Math.floor(remaining / 60)).padStart(2, "0");
    const seconds = String(remaining % 60).padStart(2, "0");
    label.textContent = `Expires in ${minutes}:${seconds}`;
    if (remaining === 0) {
      window.clearInterval(timer);
      bookingExpiryTimers.delete(timer);
      await expirePlayerBooking(label);
    }
  };
  timer = window.setInterval(update, 1000);
  bookingExpiryTimers.add(timer);
  update();
}

async function expirePlayerBooking(label) {
  try {
    const response = await fetch("includes/backend-api/expire_booking.php", {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: new URLSearchParams({ booking_id: label.dataset.bookingId }),
    });
    const result = await response.json();
    if (!response.ok || !result.success) throw new Error(result.message || "Unable to refresh this expired booking.");
    if (result.expired) {
      toastBooking("Booking expired", result.message, "error");
      allBookings = allBookings.filter((booking) => String(booking.booking_id) !== label.dataset.bookingId);
      renderBookingTab(bookingList.dataset.activeTab || "upcoming");
    } else {
      label.dataset.expires = result.hold_expires_epoch;
      label.dataset.serverNow = result.server_now_epoch;
      startBookingExpiryTimer(label);
    }
  } catch (error) {
    toastBooking("Booking status unavailable", error.message || "Reload My bookings to refresh.", "error");
    window.setTimeout(() => {
      if (label.isConnected) startBookingExpiryTimer(label);
    }, 1500);
  }
}

async function loadMyBookings() {
  try {
    const response = await fetch("includes/backend-api/view_booking.php");
    const result = await response.json();
    if (!response.ok || !result.success) throw new Error(result.message || "Unable to load your bookings.");
    allBookings = result.bookings;
    renderBookingTab(bookingList.dataset.activeTab || "upcoming");
  } catch (error) {
    bookingList.replaceChildren();
    toastBooking("Unable to load bookings", error.message || "Please try again.", "error");
  }
}

async function cancelPlayerBooking(button) {
  const bookingId = button.dataset.cancelBookingId;
  const booking = allBookings.find((item) => String(item.booking_id) === String(bookingId));
  if (!booking) return;

  const confirmationMessage = booking.can_delete_on_cancel
    ? "The time slot will be released and this booking will be removed."
    : "The time slot will be released. Your payment record will be kept; contact the facility about your payment.";
  const confirmed = await window.showDialog({
    type: "confirm",
    title: "Cancel this booking?",
    message: confirmationMessage,
    confirmText: "Yes, cancel",
    cancelText: "Keep booking",
  });
  if (!confirmed) return;

  button.disabled = true;
  try {
    const response = await fetch("includes/backend-api/cancel_checkout_booking.php", {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: new URLSearchParams({ booking_id: bookingId }),
    });
    const result = await response.json();
    if (!response.ok || !result.success) {
      const error = new Error(result.message || "Unable to cancel this booking.");
      error.code = result.code;
      throw error;
    }

    if (result.code === "deleted" || result.code === "already_gone") {
      allBookings = allBookings.filter((item) => String(item.booking_id) !== String(bookingId));
    } else if (result.code === "cancelled") {
      booking.booking_status = "cancelled";
      booking.can_cancel = false;
      booking.can_checkout = false;
      booking.attendance_status = null;
    }

    renderBookingTab(bookingList.dataset.activeTab || "upcoming");
    await window.showDialog({
      type: "success",
      title: "Booking cancelled",
      message: result.message,
    });
  } catch (error) {
    await window.showDialog({
      type: "error",
      title: error.code === "too_late" ? "Cancellation window passed" : "Unable to cancel booking",
      message: error.message || "Unable to cancel this booking. Please try again.",
    });
    if (button.isConnected) button.disabled = false;
  }
}

function setPlayerModalOpen(modal, open) {
  modal.classList.toggle("show", open);
  modal.setAttribute("aria-hidden", String(!open));
}

function openRejectionReason(booking) {
  activeRejectedBooking = booking;
  document.getElementById("rejectionReasonText").textContent =
    booking.rejection_reason?.trim() || "No rejection reason was provided. Please contact the facility.";
  const resubmissionButton = document.getElementById("openPaymentResubmission");
  const resubmissionMessage = document.getElementById("rejectionResubmissionMessage");
  resubmissionButton.hidden = !booking.can_resubmit_payment;
  resubmissionMessage.hidden = booking.can_resubmit_payment;
  resubmissionMessage.textContent = booking.resubmission_message || "";
  setPlayerModalOpen(rejectionReasonModal, true);
  document.getElementById("closeRejectionReason").focus();
}

function openPaymentResubmission() {
  if (!activeRejectedBooking?.can_resubmit_payment) return;
  const booking = activeRejectedBooking;
  const dateTime = bookingDateTime(booking);
  document.getElementById("resubmissionBookingId").value = booking.booking_id;
  document.getElementById("resubmissionBookingDetails").textContent =
    `${booking.facility_name} · Court ${booking.court_no} · ${dateTime.date} at ${dateTime.time} · P${Number(booking.total_amount).toFixed(2)} due`;
  document.getElementById("resubmissionReference").value = "";
  document.getElementById("resubmissionReceipt").value = "";
  const feedback = document.getElementById("paymentResubmissionFeedback");
  feedback.textContent = "";
  feedback.classList.remove("is-error");
  setPlayerModalOpen(rejectionReasonModal, false);
  setPlayerModalOpen(paymentResubmissionModal, true);
  document.getElementById("resubmissionPaymentMethod").focus();
}

bookingList.addEventListener("click", (event) => {
  const target = event.target;
  if (!(target instanceof Element)) return;
  const cancelButton = target.closest("[data-cancel-booking-id]");
  if (cancelButton) cancelPlayerBooking(cancelButton);
  const rejectionButton = target.closest("[data-rejected-payment-booking-id]");
  if (rejectionButton) {
    const booking = allBookings.find((item) => String(item.booking_id) === rejectionButton.dataset.rejectedPaymentBookingId);
    if (booking) openRejectionReason(booking);
  }
});

document.getElementById("closeRejectionReason").addEventListener("click", () => {
  setPlayerModalOpen(rejectionReasonModal, false);
});
document.getElementById("openPaymentResubmission").addEventListener("click", openPaymentResubmission);
document.getElementById("cancelPaymentResubmission").addEventListener("click", () => {
  setPlayerModalOpen(paymentResubmissionModal, false);
});
for (const modal of [rejectionReasonModal, paymentResubmissionModal]) {
  modal.addEventListener("click", (event) => {
    if (event.target === modal) setPlayerModalOpen(modal, false);
  });
}
document.addEventListener("keydown", (event) => {
  if (event.key !== "Escape") return;
  if (paymentResubmissionModal.classList.contains("show")) setPlayerModalOpen(paymentResubmissionModal, false);
  if (rejectionReasonModal.classList.contains("show")) setPlayerModalOpen(rejectionReasonModal, false);
});

paymentResubmissionForm.addEventListener("submit", async (event) => {
  event.preventDefault();
  const reference = document.getElementById("resubmissionReference").value.trim();
  const receiptInput = document.getElementById("resubmissionReceipt");
  const receipt = receiptInput.files[0];
  const feedback = document.getElementById("paymentResubmissionFeedback");
  const submitButton = document.getElementById("submitPaymentResubmission");
  const cancelButton = document.getElementById("cancelPaymentResubmission");
  if (!reference || reference.length > 50 || !receipt
    || !["image/jpeg", "image/png", "image/webp"].includes(receipt.type)
    || receipt.size <= 0 || receipt.size > 5 * 1024 * 1024) {
    feedback.textContent = !reference
      ? "Enter the payment reference number."
      : !receipt ? "Choose a replacement receipt image."
        : "Use a JPG, PNG, or WebP image no larger than 5 MB.";
    feedback.classList.add("is-error");
    return;
  }

  submitButton.disabled = true;
  cancelButton.disabled = true;
  submitButton.textContent = "Submitting...";
  feedback.textContent = "Uploading your replacement receipt...";
  feedback.classList.remove("is-error");
  try {
    const response = await fetch("includes/backend-api/resubmit_payment.php", {
      method: "POST",
      body: new FormData(paymentResubmissionForm),
    });
    const result = await response.json();
    if (!response.ok || !result.success) {
      throw new Error(result.message || "Unable to submit this payment.");
    }

    const booking = allBookings.find((item) =>
      String(item.booking_id) === document.getElementById("resubmissionBookingId").value);
    if (booking) {
      booking.booking_status = "pending";
      booking.booking_payment_status = "unpaid";
      booking.payment_id = Number(result.payment_id);
      booking.payment_status = "Pending";
      booking.has_receipt = true;
      booking.rejection_reason = null;
      booking.can_resubmit_payment = false;
    }
    setPlayerModalOpen(paymentResubmissionModal, false);
    renderBookingTab(bookingList.dataset.activeTab || "upcoming");
    await window.showDialog({
      type: "success",
      title: "Payment submitted",
      message: result.message,
    });
  } catch (error) {
    feedback.textContent = error.message || "Unable to submit this payment.";
    feedback.classList.add("is-error");
    await window.showDialog({
      type: "error",
      title: "Payment submission failed",
      message: error.message || "Unable to submit this payment. Please try again.",
    });
  } finally {
    submitButton.disabled = false;
    cancelButton.disabled = false;
    submitButton.textContent = "Submit for review";
  }
});

document.querySelectorAll("[data-booking-tab]").forEach((tabButton) => {
  tabButton.addEventListener("click", () => {
    document.querySelectorAll("[data-booking-tab]").forEach((button) => {
      const active = button === tabButton;
      button.classList.toggle("is-active", active);
      button.setAttribute("aria-selected", String(active));
    });
    renderBookingTab(tabButton.dataset.bookingTab);
  });
});

loadMyBookings();
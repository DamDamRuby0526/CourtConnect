document.addEventListener("DOMContentLoaded", () => {
  const durationModal = document.getElementById("bookingDurationModal");
  const durationForm = document.getElementById("bookingDurationForm");
  const durationSelect = document.getElementById("bookingDurationSelect");
  const durationFeedback = document.getElementById("bookingDurationFeedback");
  const durationPrice = document.getElementById("bookingDurationPrice");
  let durationRate = 0;

  const setModalOpen = (modal, open) => {
    modal.classList.toggle("show", open);
    modal.setAttribute("aria-hidden", String(!open));
  };

  const formatDuration = (minutes) => {
    const hours = minutes / 60;
    const value = Number.isInteger(hours) ? String(hours) : hours.toFixed(2).replace(/0+$/, "").replace(/\.$/, "");
    return `${value} ${hours === 1 ? "Hour" : "Hours"}`;
  };

  const updateDurationPrice = () => {
    const duration = Number(durationSelect.value);
    durationPrice.textContent = duration
      ? `Updated total: ₱${(durationRate * duration / Number(durationSelect.dataset.slotDuration)).toFixed(2)}`
      : "";
  };

  document.querySelectorAll("[data-edit-booking-duration]").forEach((button) => {
    button.addEventListener("click", () => {
      const currentDuration = Number(button.dataset.currentDuration);
      const slotDuration = Number(button.dataset.slotDuration);
      durationRate = Number(button.dataset.rate);
      durationSelect.dataset.slotDuration = String(slotDuration);
      document.getElementById("durationBookingId").value = button.dataset.bookingId;
      durationSelect.replaceChildren();
      for (let duration = slotDuration; duration < currentDuration; duration += slotDuration) {
        const option = document.createElement("option");
        option.value = String(duration);
        option.textContent = formatDuration(duration);
        durationSelect.append(option);
      }
      durationFeedback.textContent = "Duration can only be shortened before payment is submitted.";
      durationFeedback.classList.remove("is-error");
      updateDurationPrice();
      setModalOpen(durationModal, true);
      durationSelect.focus();
    });
  });

  durationSelect.addEventListener("change", updateDurationPrice);
  durationForm.addEventListener("submit", async (event) => {
    event.preventDefault();
    const submitButton = document.getElementById("saveBookingDuration");
    submitButton.disabled = true;
    durationFeedback.textContent = "Updating reservation…";
    durationFeedback.classList.remove("is-error");
    try {
      const response = await fetch("../includes/admin-php/update_booking_duration.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: new URLSearchParams(new FormData(durationForm)),
      });
      const result = await response.json();
      if (!response.ok || !result.success) throw new Error(result.message || "Unable to update this reservation.");
      durationFeedback.textContent = result.message;
      window.location.reload();
    } catch (error) {
      durationFeedback.textContent = error.message || "Unable to update this reservation.";
      durationFeedback.classList.add("is-error");
      submitButton.disabled = false;
    }
  });

  document.querySelectorAll("[data-close-booking-dialog]").forEach((button) => {
    button.addEventListener("click", () => {
      setModalOpen(durationModal, false);
    });
  });

  durationModal.addEventListener("click", (event) => {
    if (event.target === durationModal) setModalOpen(durationModal, false);
  });

  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape") setModalOpen(durationModal, false);
  });
});

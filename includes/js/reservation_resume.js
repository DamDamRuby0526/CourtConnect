document.addEventListener("DOMContentLoaded", () => {
  const button = document.getElementById("confirmReservationButton");
  const message = document.querySelector(".reservation-resume-message");
  if (!button || !message) return;

  button.addEventListener("click", async () => {
    button.disabled = true;
    button.textContent = "Checking availability...";
    message.hidden = true;

    try {
      const scheduleIds = JSON.parse(button.dataset.scheduleIds);
      const response = await fetch("includes/backend-api/make_booking.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ schedule_ids: scheduleIds }),
      });
      if (response.status === 403) {
        window.location.href = "reservation_resume.php";
        return;
      }
      if (response.status === 409) {
        window.location.reload();
        return;
      }
      const result = await response.json();
      if (!response.ok || !result.success) {
        throw new Error(result.message || "Unable to reserve this time. Please try again.");
      }
      window.location.href = result.booking_group_id
        ? `checkout.php?booking_group_id=${encodeURIComponent(result.booking_group_id)}`
        : `checkout.php?booking_id=${encodeURIComponent(result.booking_id)}`;
    } catch (error) {
      message.textContent = error.message || "Unable to reserve this time. Please try again.";
      message.hidden = false;
      button.disabled = false;
      button.textContent = "Confirm and reserve time";
    }
  });
});

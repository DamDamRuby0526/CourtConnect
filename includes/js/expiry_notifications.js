document.addEventListener("DOMContentLoaded", async () => {
  try {
    const response = await fetch("includes/backend-api/consume_expiry_notifications.php");
    const result = await response.json();
    if (!response.ok || !result.success) return;
    if (document.querySelector(".checkout-page") && typeof window.showDialog === "function") {
      for (const message of result.messages) {
        await window.showDialog({ type: "error", title: "Booking expired", message });
      }
    } else if (typeof window.showToast === "function") {
      result.messages.forEach((message) => {
        window.showToast("Booking expired", message, "error");
      });
    }
  } catch (error) {
    console.error("Unable to load booking expiry notices:", error);
  }
});

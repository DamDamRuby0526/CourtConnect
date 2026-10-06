document.addEventListener("DOMContentLoaded", async () => {
  const tbody = document.getElementById("bookingsTable");

  try {
    const response = await fetch("includes/backend-api/view_booking.php");
    const data = await response.json();

    if (!data.success) {
      alert(data.message || "Failed to load bookings.");
      return;
    }

    tbody.replaceChildren();

    if (data.bookings.length === 0) {
      const tr = document.createElement("tr");
      const td = document.createElement("td");
      td.colSpan = 6;
      td.textContent = "You have no bookings yet.";
      tr.append(td);
      tbody.append(tr);
      return;
    }

    for (const booking of data.bookings) {
      const tr = document.createElement("tr");

      const values = [
        `${booking.facility_name} (${booking.municipality})`,
        `Court ${booking.court_no}`,
        booking.court_date,
        booking.court_time,
        booking.schedule_status,
        `₱${booking.total_amount.toFixed(2)}`,
      ];

      for (const value of values) {
        const td = document.createElement("td");
        td.textContent = value;
        tr.append(td);
      }

      tbody.append(tr);
    }
  } catch (error) {
    console.error("Error loading bookings:", error);
  }
});

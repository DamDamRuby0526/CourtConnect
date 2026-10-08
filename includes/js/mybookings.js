const bookingsTable = document.getElementById("bookingsTable");

function appendBookingCell(row, text) {
  const cell = document.createElement("td");
  cell.textContent = text;
  row.append(cell);
  return cell;
}

function createReceiptLink(paymentId) {
  const link = document.createElement("a");
  link.href = `includes/admin-php/view_payment_receipt.php?payment_id=${encodeURIComponent(paymentId)}`;
  link.target = "_blank";
  link.rel = "noopener noreferrer";
  link.className = "receipt-view-link";
  link.textContent = "View uploaded receipt";
  return link;
}

function renderReceiptControls(cell, booking) {
  if (booking.has_receipt) {
    cell.append(createReceiptLink(booking.payment_id));
  } else {
    const missingReceipt = document.createElement("span");
    missingReceipt.className = "receipt-missing";
    missingReceipt.textContent = "No receipt uploaded";
    cell.append(missingReceipt);
  }

  if (!booking.payment_id || booking.payment_status !== "Pending") return;

  const controls = document.createElement("div");
  controls.className = "receipt-upload-controls";
  const input = document.createElement("input");
  input.type = "file";
  input.accept = "image/jpeg,image/png,image/webp";
  input.required = true;
  input.setAttribute("aria-label", "Choose GCash receipt image");
  const button = document.createElement("button");
  button.type = "button";
  button.className = "receipt-upload-button";
  button.textContent = booking.has_receipt ? "Replace receipt" : "Upload receipt";
  button.addEventListener("click", async () => {
    if (!input.files[0]) {
      input.reportValidity();
      return;
    }
    const formData = new FormData();
    formData.append("payment_id", booking.payment_id);
    formData.append("receipt", input.files[0]);
    button.disabled = true;
    button.textContent = "Uploading...";
    try {
      const response = await fetch("includes/backend-api/upload_payment_receipt.php", {
        method: "POST",
        body: formData,
      });
      const result = await response.json();
      if (!result.success) throw new Error(result.message || "Unable to upload the receipt.");
      await loadBookings();
    } catch (error) {
      button.disabled = false;
      button.textContent = booking.has_receipt ? "Replace receipt" : "Upload receipt";
      window.alert(error.message || "Unable to upload the receipt.");
    }
  });
  controls.append(input, button);
  cell.append(controls);
}

async function loadBookings() {
  try {
    const response = await fetch("includes/backend-api/view_booking.php");
    const data = await response.json();
    if (!data.success) throw new Error(data.message || "Failed to load bookings.");
    bookingsTable.replaceChildren();
    if (data.bookings.length === 0) {
      const row = document.createElement("tr");
      const cell = document.createElement("td");
      cell.colSpan = 8;
      cell.textContent = "You have no bookings yet.";
      row.append(cell);
      bookingsTable.append(row);
      return;
    }

    for (const booking of data.bookings) {
      const row = document.createElement("tr");
      appendBookingCell(row, `${booking.facility_name} (${booking.municipality})`);
      appendBookingCell(row, `Court ${booking.court_no}`);
      appendBookingCell(row, booking.court_date);
      appendBookingCell(row, booking.court_time);
      appendBookingCell(row, booking.schedule_status);
      appendBookingCell(row, `₱${Number(booking.total_amount).toFixed(2)}`);
      appendBookingCell(row, booking.payment_status || "Payment unavailable");
      const receiptCell = document.createElement("td");
      renderReceiptControls(receiptCell, booking);
      row.append(receiptCell);
      bookingsTable.append(row);
    }
  } catch (error) {
    console.error("Error loading bookings:", error);
    bookingsTable.replaceChildren();
    const row = document.createElement("tr");
    const cell = document.createElement("td");
    cell.colSpan = 8;
    cell.textContent = error.message || "Unable to load bookings.";
    row.append(cell);
    bookingsTable.append(row);
  }
}

document.addEventListener("DOMContentLoaded", loadBookings);
const paymentRows = document.getElementById("paymentRows");
const paymentStatusFilter = document.getElementById("paymentStatusFilter");
const paymentMessage = document.getElementById("paymentMessage");
let payments = [];
let pendingPaymentId = null;
const markPaidModal = document.getElementById("markPaidModal");

function setMarkPaidModalOpen(open) {
  markPaidModal.classList.toggle("show", open);
  markPaidModal.setAttribute("aria-hidden", String(!open));
  if (open) document.getElementById("confirmMarkPaid").focus();
  else pendingPaymentId = null;
}

function paymentCell(label, value, className = "") {
  const cell = document.createElement("td");
  cell.dataset.label = label;
  if (className) cell.className = className;
  if (value instanceof Node) cell.append(value);
  else cell.textContent = value;
  return cell;
}

function formatPaymentDate(dateString, timeString) {
  const [year, month, day] = dateString.split("-").map(Number);
  const [hours, minutes] = timeString.split(":").map(Number);
  const date = new Date(year, month - 1, day, hours, minutes);
  return date.toLocaleString(undefined, {
    month: "short",
    day: "numeric",
    year: "numeric",
    hour: "numeric",
    minute: "2-digit",
  });
}

function renderPayments() {
  const visiblePayments = payments.filter((payment) =>
    !paymentStatusFilter.value || payment.payment_status.toLowerCase() === paymentStatusFilter.value,
  );
  paymentRows.replaceChildren();
  if (visiblePayments.length === 0) {
    const emptyRow = document.createElement("tr");
    const emptyCell = document.createElement("td");
    emptyCell.colSpan = 9;
    emptyCell.className = "schedule-empty";
    emptyCell.textContent = payments.length === 0
      ? "No payment records found for this facility."
      : "No payments match this status.";
    emptyRow.append(emptyCell);
    paymentRows.append(emptyRow);
    return;
  }

  for (const payment of visiblePayments) {
    const row = document.createElement("tr");
    row.className = "payment-row";
    row.dataset.status = payment.payment_status.toLowerCase();
    const status = document.createElement("span");
    status.className = `payment-status payment-${payment.payment_status.toLowerCase()}`;
    status.textContent = payment.payment_status;
    const action = document.createElement("button");
    action.type = "button";
    action.className = "admin-book-slot-button";
    action.textContent = payment.payment_status !== "Pending"
      ? "Paid"
      : payment.has_receipt ? "Mark paid" : "Awaiting receipt";
    action.disabled = payment.payment_status !== "Pending" || !payment.has_receipt;
    action.dataset.paymentId = payment.payments_id;
    let receipt;
    if (payment.has_receipt) {
      receipt = document.createElement("a");
      receipt.className = "payment-receipt-link";
      receipt.href = `../includes/admin-php/view_payment_receipt.php?payment_id=${encodeURIComponent(payment.payments_id)}`;
      receipt.target = "_blank";
      receipt.rel = "noopener noreferrer";
      receipt.textContent = "View receipt";
    } else {
      receipt = "Not uploaded";
    }
    row.append(
      paymentCell("Booking date", formatPaymentDate(payment.court_date, payment.court_time)),
      paymentCell("Court", `Court ${payment.court_no}`),
      paymentCell("Customer", `${payment.first_name} ${payment.last_name}`),
      paymentCell("Method", payment.payment_method),
      paymentCell("Reference", payment.reference_number || "Not provided"),
      paymentCell("Amount", `₱${Number(payment.total_amount).toFixed(2)}`),
      paymentCell("Receipt", receipt),
      paymentCell("Status", status),
      paymentCell("Action", action),
    );
    paymentRows.append(row);
  }
}

async function loadPayments() {
  paymentRows.setAttribute("aria-busy", "true");
  try {
    const response = await fetch("../includes/admin-php/manage_payments.php");
    const result = await response.json();
    if (!result.success) throw new Error(result.message || "Unable to load payment records.");
    payments = result.payments;
    document.getElementById("pendingPaymentCount").textContent = result.pending_count;
    document.getElementById("paidPaymentTotal").textContent = `₱${Number(result.paid_total).toFixed(2)}`;
    renderPayments();
  } catch (error) {
    paymentMessage.textContent = error.message || "Unable to load payment records.";
    paymentMessage.classList.add("is-error");
  } finally {
    paymentRows.removeAttribute("aria-busy");
  }
}

paymentRows.addEventListener("click", (event) => {
  const button = event.target.closest("button[data-payment-id]");
  if (!button) return;
  pendingPaymentId = button.dataset.paymentId;
  setMarkPaidModalOpen(true);
});

async function markPaymentPaid() {
  if (!pendingPaymentId) return;
  const paymentId = pendingPaymentId;
  const confirmButton = document.getElementById("confirmMarkPaid");
  confirmButton.disabled = true;
  paymentMessage.classList.remove("is-error");
  paymentMessage.textContent = "Updating payment status...";
  setMarkPaidModalOpen(false);
  try {
    const response = await fetch("../includes/admin-php/manage_payments.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ payment_id: paymentId }),
    });
    const result = await response.json();
    if (!result.success) throw new Error(result.message || "Unable to update payment status.");
    paymentMessage.textContent = result.message;
    await loadPayments();
  } catch (error) {
    paymentMessage.textContent = error.message || "Unable to update payment status.";
    paymentMessage.classList.add("is-error");
  } finally {
    confirmButton.disabled = false;
  }
}

document.getElementById("confirmMarkPaid").addEventListener("click", markPaymentPaid);
document.getElementById("cancelMarkPaid").addEventListener("click", () => setMarkPaidModalOpen(false));
markPaidModal.addEventListener("click", (event) => {
  if (event.target === markPaidModal) setMarkPaidModalOpen(false);
});
document.addEventListener("keydown", (event) => {
  if (event.key === "Escape" && markPaidModal.classList.contains("show")) setMarkPaidModalOpen(false);
});

paymentStatusFilter.addEventListener("change", renderPayments);
document.getElementById("refreshPayments").addEventListener("click", loadPayments);
loadPayments();
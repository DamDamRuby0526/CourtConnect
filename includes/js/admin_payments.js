const paymentRows = document.getElementById("paymentRows");
const paymentStatusFilter = document.getElementById("paymentStatusFilter");
const paymentMessage = document.getElementById("paymentMessage");
const editPaymentModal = document.getElementById("editPaymentModal");
const editPaymentForm = document.getElementById("editPaymentForm");
const paymentReceiptModal = document.getElementById("paymentReceiptModal");
const paymentReceiptImage = document.getElementById("paymentReceiptImage");
const pesoFormatter = new Intl.NumberFormat("en-PH", {
  style: "currency",
  currency: "PHP",
  minimumFractionDigits: 2,
});
let payments = [];
let pendingPaymentId = null;
let paymentMessageTimeout = null;
const markPaidModal = document.getElementById("markPaidModal");

function showPaymentMessage(message, isError = false) {
  clearTimeout(paymentMessageTimeout);
  paymentMessage.textContent = message;
  paymentMessage.classList.toggle("is-error", isError);
  if (message && !isError) {
    paymentMessageTimeout = setTimeout(() => {
      paymentMessage.textContent = "";
    }, 5000);
  }
}

function setMarkPaidModalOpen(open) {
  markPaidModal.classList.toggle("show", open);
  markPaidModal.setAttribute("aria-hidden", String(!open));
  if (open) document.getElementById("confirmMarkPaid").focus();
  else pendingPaymentId = null;
}

function setEditPaymentModalOpen(open) {
  editPaymentModal.classList.toggle("show", open);
  editPaymentModal.setAttribute("aria-hidden", String(!open));
  if (open) document.getElementById("editPaymentMethod").focus();
}

function openEditPayment(payment) {
  document.getElementById("editPaymentId").value = payment.payments_id;
  document.getElementById("editPaymentMethod").value = payment.payment_method;
  document.getElementById("editPaymentReference").value = payment.reference_number || "";
  updateReferenceField();
  document.getElementById("editPaymentStatusSelect").value = payment.payment_status;
  const formStatus = document.getElementById("editPaymentFeedback");
  formStatus.textContent = "";
  formStatus.classList.remove("is-error");
  setEditPaymentModalOpen(true);
}

function setPaymentReceiptOpen(open, paymentId = "") {
  paymentReceiptModal.classList.toggle("show", open);
  paymentReceiptModal.setAttribute("aria-hidden", String(!open));
  if (open) {
    paymentReceiptImage.src = `../includes/admin-php/view_payment_receipt.php?payment_id=${encodeURIComponent(paymentId)}`;
    document.getElementById("closePaymentReceipt").focus();
  } else {
    paymentReceiptImage.removeAttribute("src");
  }
}

function updateReferenceField() {
  const isCash = document.getElementById("editPaymentMethod").value === "Cash";
  const field = document.getElementById("editPaymentReferenceField");
  const referenceInput = document.getElementById("editPaymentReference");
  field.hidden = isCash;
  referenceInput.disabled = isCash;
  if (isCash) referenceInput.value = "";
}

function paymentCell(label, value, className = "") {
  const cell = document.createElement("td");
  cell.dataset.label = label;
  if (className) cell.className = className;
  if (Array.isArray(value)) {
    const actions = document.createElement("div");
    actions.className = "payment-row-actions";
    actions.append(...value);
    cell.append(actions);
  } else if (value instanceof Node) cell.append(value);
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

function customerDetails(payment) {
  const customer = document.createElement("div");
  customer.className = "payment-customer";
  const name = document.createElement("strong");
  name.textContent = payment.customer_name || "Walk-in customer";
  customer.append(name);
  if (payment.customer_phone) {
    const phone = document.createElement("small");
    phone.textContent = payment.customer_phone;
    customer.append(phone);
  }
  return customer;
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
    const canMarkPaid = payment.payment_method === "Cash" || payment.has_receipt;
    action.textContent = payment.payment_status !== "Pending"
      ? payment.payment_status
      : canMarkPaid ? "Mark paid" : "Awaiting receipt";
    action.disabled = payment.payment_status !== "Pending" || !canMarkPaid;
    action.dataset.paymentId = payment.payments_id;
    const editButton = document.createElement("button");
    editButton.type = "button";
    editButton.className = "owner-secondary-button payment-edit-button";
    editButton.textContent = "Edit";
    editButton.dataset.editPaymentId = payment.payments_id;
    let receipt;
    if (payment.has_receipt) {
      receipt = document.createElement("button");
      receipt.type = "button";
      receipt.className = "owner-secondary-button payment-receipt-link";
      receipt.dataset.receiptPaymentId = payment.payments_id;
      receipt.textContent = "View receipt";
    } else {
      receipt = "Not uploaded";
    }
    row.append(
      paymentCell("Booking date", formatPaymentDate(payment.court_date, payment.court_time)),
      paymentCell("Court", `Court ${payment.court_no}`),
      paymentCell("Customer", customerDetails(payment)),
      paymentCell("Method", payment.payment_method === "Gcash" ? "GCash" : "Cash"),
      paymentCell("Reference", payment.reference_number || "Not provided"),
      paymentCell("Amount", pesoFormatter.format(Number(payment.total_amount))),
      paymentCell("Receipt", receipt),
      paymentCell("Status", status),
      paymentCell("Action", [editButton, action]),
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
    document.getElementById("rejectedPaymentCount").textContent = result.rejected_count;
    document.getElementById("paidPaymentTotal").textContent = pesoFormatter.format(Number(result.paid_total));
    renderPayments();
  } catch (error) {
    showPaymentMessage(error.message || "Unable to load payment records.", true);
  } finally {
    paymentRows.removeAttribute("aria-busy");
  }
}

paymentRows.addEventListener("click", (event) => {
  const receiptButton = event.target.closest("button[data-receipt-payment-id]");
  if (receiptButton) {
    setPaymentReceiptOpen(true, receiptButton.dataset.receiptPaymentId);
    return;
  }
  const editButton = event.target.closest("button[data-edit-payment-id]");
  if (editButton) {
    const payment = payments.find((item) => String(item.payments_id) === editButton.dataset.editPaymentId);
    if (payment) openEditPayment(payment);
    return;
  }
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
  showPaymentMessage("Updating payment status...");
  setMarkPaidModalOpen(false);
  try {
    const response = await fetch("../includes/admin-php/manage_payments.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ payment_id: paymentId }),
    });
    const result = await response.json();
    if (!result.success) throw new Error(result.message || "Unable to update payment status.");
    showPaymentMessage(result.message);
    await loadPayments();
  } catch (error) {
    showPaymentMessage(error.message || "Unable to update payment status.", true);
  } finally {
    confirmButton.disabled = false;
  }
}

document.getElementById("confirmMarkPaid").addEventListener("click", markPaymentPaid);
document.getElementById("cancelMarkPaid").addEventListener("click", () => setMarkPaidModalOpen(false));
document.getElementById("cancelEditPayment").addEventListener("click", () => setEditPaymentModalOpen(false));
document.getElementById("closePaymentReceipt").addEventListener("click", () => setPaymentReceiptOpen(false));
markPaidModal.addEventListener("click", (event) => {
  if (event.target === markPaidModal) setMarkPaidModalOpen(false);
});
editPaymentModal.addEventListener("click", (event) => {
  if (event.target === editPaymentModal) setEditPaymentModalOpen(false);
});
paymentReceiptModal.addEventListener("click", (event) => {
  if (event.target === paymentReceiptModal) setPaymentReceiptOpen(false);
});
document.addEventListener("keydown", (event) => {
  if (event.key === "Escape" && markPaidModal.classList.contains("show")) setMarkPaidModalOpen(false);
  if (event.key === "Escape" && editPaymentModal.classList.contains("show")) setEditPaymentModalOpen(false);
  if (event.key === "Escape" && paymentReceiptModal.classList.contains("show")) setPaymentReceiptOpen(false);
});

document.getElementById("editPaymentMethod").addEventListener("change", updateReferenceField);

editPaymentForm.addEventListener("submit", async (event) => {
  event.preventDefault();
  const saveButton = document.getElementById("savePaymentChanges");
  const formStatus = document.getElementById("editPaymentFeedback");
  const payload = Object.fromEntries(new FormData(editPaymentForm));
  payload.action = "update";
  saveButton.disabled = true;
  formStatus.textContent = "Saving payment details...";
  formStatus.classList.remove("is-error");
  try {
    const response = await fetch("../includes/admin-php/manage_payments.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload),
    });
    const result = await response.json();
    if (!result.success) throw new Error(result.message || "Unable to save payment details.");
    setEditPaymentModalOpen(false);
    showPaymentMessage(result.message);
    await loadPayments();
  } catch (error) {
    formStatus.textContent = error.message || "Unable to save payment details.";
    formStatus.classList.add("is-error");
  } finally {
    saveButton.disabled = false;
  }
});

paymentStatusFilter.addEventListener("change", renderPayments);
document.getElementById("refreshPayments").addEventListener("click", loadPayments);
loadPayments();
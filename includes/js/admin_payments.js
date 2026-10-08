const paymentRows = document.getElementById("paymentRows");
const paymentStatusFilter = document.getElementById("paymentStatusFilter");
const paymentMessage = document.getElementById("paymentMessage");
const editPaymentModal = document.getElementById("editPaymentModal");
const editPaymentForm = document.getElementById("editPaymentForm");
const paymentReceiptModal = document.getElementById("paymentReceiptModal");
const paymentReceiptImage = document.getElementById("paymentReceiptImage");
let payments = [];
let availableSchedules = [];
let pendingPaymentId = null;
let editingPayment = null;
const markPaidModal = document.getElementById("markPaidModal");

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
  else editingPayment = null;
}

function openEditPayment(payment) {
  editingPayment = payment;
  document.getElementById("editPaymentId").value = payment.payments_id;
  document.getElementById("editCustomerName").value = payment.customer_name || "";
  document.getElementById("editCustomerPhone").value = payment.customer_phone || "";
  document.getElementById("editPaymentMethod").value = payment.payment_method;
  document.getElementById("editPaymentReference").value = payment.reference_number || "";
  updateReferenceField();
  document.getElementById("editPaymentStatusSelect").value = payment.payment_status;
  const scheduleSelect = document.getElementById("editPaymentSchedule");
  scheduleSelect.replaceChildren();
  const currentSchedule = {
    schedule_id: payment.schedule_id,
    court_no: payment.court_no,
    court_date: payment.court_date,
    court_time: payment.court_time,
    slot_duration: payment.slot_duration,
  };
  const schedules = [currentSchedule, ...availableSchedules.filter((schedule) =>
    String(schedule.schedule_id) !== String(payment.schedule_id),
  )];
  for (const schedule of schedules) {
    const option = document.createElement("option");
    option.value = schedule.schedule_id;
    const currentLabel = String(schedule.schedule_id) === String(payment.schedule_id) ? "Current · " : "";
    option.textContent = `${currentLabel}Court ${schedule.court_no} · ${formatPaymentDate(schedule.court_date, schedule.court_time)} · ${schedule.slot_duration} min`;
    scheduleSelect.append(option);
  }
  scheduleSelect.value = payment.schedule_id;
  updateScheduleOptionsForStatus();
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

function updateScheduleOptionsForStatus() {
  const isRejected = document.getElementById("editPaymentStatusSelect").value === "Rejected";
  const scheduleSelect = document.getElementById("editPaymentSchedule");
  for (const option of scheduleSelect.options) {
    option.disabled = isRejected && String(option.value) !== String(editingPayment.schedule_id);
  }
  if (isRejected) scheduleSelect.value = editingPayment.schedule_id;
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
      paymentCell("Customer", `${payment.customer_name}${payment.customer_phone ? ` · ${payment.customer_phone}` : ""}`),
      paymentCell("Method", payment.payment_method),
      paymentCell("Reference", payment.reference_number || "Not provided"),
      paymentCell("Amount", `₱${Number(payment.total_amount).toFixed(2)}`),
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
    availableSchedules = result.available_schedules;
    document.getElementById("pendingPaymentCount").textContent = result.pending_count;
    document.getElementById("rejectedPaymentCount").textContent = result.rejected_count;
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

document.getElementById("editPaymentStatusSelect").addEventListener("change", (event) => {
  updateScheduleOptionsForStatus();
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
    paymentMessage.textContent = result.message;
    paymentMessage.classList.remove("is-error");
    await loadPayments();
    paymentMessage.textContent = result.message;
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
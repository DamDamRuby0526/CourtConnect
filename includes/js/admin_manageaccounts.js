const accountList = document.getElementById("accountList");
const accountStatus = document.getElementById("accountStatus");
const accountModal = document.getElementById("accountModal");
const accountStatusModal = document.getElementById("accountStatusModal");
const adminAccountForm = document.getElementById("adminAccountForm");
const adminPassword = document.getElementById("adminPassword");
let editingAccount = null;
let pendingStatusChange = null;

function setAccountModalOpen(open) {
  accountModal.classList.toggle("show", open);
  accountModal.setAttribute("aria-hidden", String(!open));
  if (open) document.getElementById("adminFirstName").focus();
}

function setStatusModalOpen(open) {
  accountStatusModal.classList.toggle("show", open);
  accountStatusModal.setAttribute("aria-hidden", String(!open));
  if (open) document.getElementById("confirmAccountStatus").focus();
  else pendingStatusChange = null;
}

function openAccountForm(account = null) {
  editingAccount = account;
  adminAccountForm.reset();
  document.getElementById("accountDialogTitle").textContent = account ? "Edit admin account" : "Add an admin";
  document.getElementById("adminPasswordLabel").textContent = account ? "New password (optional)" : "Temporary password";
  document.getElementById("saveAdminButton").textContent = account ? "Save changes" : "Create account";
  adminPassword.required = !account;
  if (account) {
    document.getElementById("adminFirstName").value = account.first_name;
    document.getElementById("adminLastName").value = account.last_name;
    document.getElementById("adminEmail").value = account.email;
    document.getElementById("adminPhone").value = account.phone_number;
  }
  const formStatus = document.getElementById("adminFormStatus");
  formStatus.textContent = "";
  formStatus.classList.remove("is-error");
  setAccountModalOpen(true);
}

async function loadAdminAccounts() {
  const count = document.getElementById("accountCount");
  try {
    const response = await fetch("../includes/admin-php/manage_owner_accounts.php");
    const result = await response.json();
    if (!result.success) throw new Error(result.message || "Unable to load admin accounts.");
    accountList.replaceChildren();
    count.textContent = `${result.accounts.length} ${result.accounts.length === 1 ? "account" : "accounts"}`;
    if (result.accounts.length === 0) {
      const empty = document.createElement("p");
      empty.className = "owner-empty-state";
      empty.textContent = "No admin accounts yet. Add an account to give staff access to facility bookings.";
      accountList.append(empty);
      return;
    }

    for (const account of result.accounts) {
      const row = document.createElement("article");
      row.className = "owner-account-row";
      const details = document.createElement("div");
      details.className = "owner-account-details";
      const name = document.createElement("h3");
      name.textContent = `${account.first_name} ${account.last_name}`;
      const email = document.createElement("p");
      email.textContent = account.email;
      const phone = document.createElement("span");
      phone.textContent = account.phone_number;
      details.append(name, email, phone);

      const status = document.createElement("span");
      status.className = `owner-account-status ${account.status === "Active" ? "is-active" : "is-inactive"}`;
      status.textContent = account.status;
      const actions = document.createElement("div");
      actions.className = "owner-account-actions";
      const editButton = document.createElement("button");
      editButton.type = "button";
      editButton.className = "owner-secondary-button";
      editButton.textContent = "Edit";
      editButton.addEventListener("click", () => openAccountForm(account));

      const statusButton = document.createElement("button");
      statusButton.type = "button";
      statusButton.className = "owner-secondary-button";
      statusButton.textContent = account.status === "Active" ? "Disable" : "Enable";
      statusButton.addEventListener("click", () => {
        const action = account.status === "Active" ? "Disable" : "Enable";
        pendingStatusChange = { account, button: statusButton };
        document.getElementById("accountStatusTitle").textContent = `${action} account?`;
        document.getElementById("accountStatusMessage").textContent = `${action} “${account.first_name} ${account.last_name}” account?`;
        const confirmButton = document.getElementById("confirmAccountStatus");
        confirmButton.textContent = `${action} account`;
        confirmButton.classList.toggle("owner-danger-button", action === "Disable");
        confirmButton.classList.toggle("owner-primary-button", action === "Enable");
        setStatusModalOpen(true);
      });
      actions.append(editButton, statusButton);
      row.append(details, status, actions);
      accountList.append(row);
    }
  } catch (error) {
    count.textContent = "Accounts unavailable";
    accountStatus.textContent = error.message || "Unable to load admin accounts.";
    accountStatus.classList.add("is-error");
  }
}

async function updateAccountStatus(account, button) {
  const nextStatus = account.status === "Active" ? "Inactive" : "Active";
  button.disabled = true;
  try {
    const response = await fetch("../includes/admin-php/manage_owner_accounts.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ action: "set_status", admin_id: account.admin_id, status: nextStatus }),
    });
    const result = await response.json();
    if (!result.success) throw new Error(result.message || "Unable to update account status.");
    accountStatus.textContent = result.message;
    accountStatus.classList.remove("is-error");
    await loadAdminAccounts();
    accountStatus.textContent = result.message;
  } catch (error) {
    accountStatus.textContent = error.message || "Unable to update the account status.";
    accountStatus.classList.add("is-error");
    button.disabled = false;
  }
}

document.getElementById("addAdminButton").addEventListener("click", () => openAccountForm());
document.getElementById("cancelAccountModal").addEventListener("click", () => setAccountModalOpen(false));
document.getElementById("cancelAccountStatus").addEventListener("click", () => setStatusModalOpen(false));
document.getElementById("confirmAccountStatus").addEventListener("click", async () => {
  if (!pendingStatusChange) return;
  const { account, button } = pendingStatusChange;
  setStatusModalOpen(false);
  await updateAccountStatus(account, button);
});
accountModal.addEventListener("click", (event) => {
  if (event.target === accountModal) setAccountModalOpen(false);
});
accountStatusModal.addEventListener("click", (event) => {
  if (event.target === accountStatusModal) setStatusModalOpen(false);
});
document.addEventListener("keydown", (event) => {
  if (event.key === "Escape" && accountModal.classList.contains("show")) setAccountModalOpen(false);
  if (event.key === "Escape" && accountStatusModal.classList.contains("show")) setStatusModalOpen(false);
});

adminAccountForm.addEventListener("submit", async (event) => {
  event.preventDefault();
  const formStatus = document.getElementById("adminFormStatus");
  const saveButton = document.getElementById("saveAdminButton");
  const payload = Object.fromEntries(new FormData(adminAccountForm));
  payload.action = editingAccount ? "update" : "create";
  if (editingAccount) payload.admin_id = editingAccount.admin_id;
  saveButton.disabled = true;
  formStatus.textContent = editingAccount ? "Saving changes..." : "Creating account...";
  formStatus.classList.remove("is-error");
  try {
    const response = await fetch("../includes/admin-php/manage_owner_accounts.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload),
    });
    const result = await response.json();
    if (!result.success) throw new Error(result.message || "Unable to save admin account.");
    setAccountModalOpen(false);
    editingAccount = null;
    await loadAdminAccounts();
    accountStatus.textContent = result.message;
    accountStatus.classList.remove("is-error");
  } catch (error) {
    formStatus.textContent = error.message || "Unable to save the admin account.";
    formStatus.classList.add("is-error");
  } finally {
    saveButton.disabled = false;
  }
});

loadAdminAccounts();

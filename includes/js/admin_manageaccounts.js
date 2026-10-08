const accountList = document.getElementById("accountList");
const accountStatus = document.getElementById("accountStatus");
const accountModal = document.getElementById("accountModal");
const adminAccountForm = document.getElementById("adminAccountForm");
const accountStatusModal = document.getElementById("accountStatusModal");
const adminPassword = document.getElementById("adminPassword");
let editingAccount = null;
let pendingDisable = null;

function setAccountModalOpen(open) {
  accountModal.classList.toggle("show", open);
  accountModal.setAttribute("aria-hidden", String(!open));
  if (open) document.getElementById("adminFirstName").focus();
}

function setDisableModalOpen(open) {
  accountStatusModal.classList.toggle("show", open);
  accountStatusModal.setAttribute("aria-hidden", String(!open));
  if (open) document.getElementById("confirmAccountStatus").focus();
  else pendingDisable = null;
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
  document.getElementById("adminFormStatus").textContent = "";
  document.getElementById("adminFormStatus").classList.remove("is-error");
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
      editButton.setAttribute("aria-label", `Edit ${account.first_name} ${account.last_name}`);
      editButton.addEventListener("click", () => openAccountForm(account));

      const button = document.createElement("button");
      button.type = "button";
      button.className = "owner-secondary-button";
      button.textContent = account.status === "Active" ? "Disable" : "Enable";
      button.setAttribute("aria-label", `${button.textContent} ${account.first_name} ${account.last_name}`);
      button.addEventListener("click", () => {
        const action = account.status === "Active" ? "Disable" : "Enable";
        pendingDisable = { account, button };
        document.getElementById("accountStatusTitle").textContent = `${action} account?`;
        document.getElementById("accountStatusMessage").textContent = `${action} “${account.first_name} ${account.last_name}” account?`;
        const confirmButton = document.getElementById("confirmAccountStatus");
        confirmButton.textContent = `${action} account`;
        confirmButton.classList.toggle("owner-danger-button", action === "Disable");
        confirmButton.classList.toggle("owner-primary-button", action === "Enable");
        setDisableModalOpen(true);
      });
      actions.append(editButton, button);

      row.append(details, status, actions);
      accountList.append(row);
    }
  } catch (error) {
    count.textContent = "Accounts unavailable";
    accountStatus.textContent = error.message;
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
    accountStatus.textContent = result.message;
    accountStatus.classList.toggle("is-error", !result.success);
    if (result.success) await loadAdminAccounts();
  } catch (error) {
    accountStatus.textContent = "Unable to update the account status.";
    accountStatus.classList.add("is-error");
  } finally {
    button.disabled = false;
  }
}

document.getElementById("addAdminButton").addEventListener("click", () => openAccountForm());
document.getElementById("cancelAccountModal").addEventListener("click", () => setAccountModalOpen(false));
document.getElementById("cancelAccountStatus").addEventListener("click", () => setDisableModalOpen(false));
document.getElementById("confirmAccountStatus").addEventListener("click", async () => {
  if (!pendingDisable) return;
  const { account, button } = pendingDisable;
  setDisableModalOpen(false);
  await updateAccountStatus(account, button);
});
accountModal.addEventListener("click", (event) => {
  if (event.target === accountModal) setAccountModalOpen(false);
});
accountStatusModal.addEventListener("click", (event) => {
  if (event.target === accountStatusModal) setDisableModalOpen(false);
});
document.addEventListener("keydown", (event) => {
  if (event.key === "Escape" && accountModal.classList.contains("show")) setAccountModalOpen(false);
  if (event.key === "Escape" && accountStatusModal.classList.contains("show")) setDisableModalOpen(false);
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
    formStatus.textContent = result.message;
    formStatus.classList.toggle("is-error", !result.success);
    if (result.success) {
      adminAccountForm.reset();
      setAccountModalOpen(false);
      accountStatus.textContent = result.message;
      accountStatus.classList.remove("is-error");
      await loadAdminAccounts();
      editingAccount = null;
    }
  } catch (error) {
    formStatus.textContent = editingAccount ? "Unable to update the admin account." : "Unable to create the admin account.";
    formStatus.classList.add("is-error");
  } finally {
    saveButton.disabled = false;
  }
});

loadAdminAccounts();
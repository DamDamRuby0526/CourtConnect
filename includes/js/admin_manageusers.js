document.addEventListener("DOMContentLoaded", () => {
  const tbody = document.getElementById("usersTable");
  const status = document.getElementById("usersStatus");
  const count = document.getElementById("userCount");
  const search = document.getElementById("userSearch");
  const overlay = document.getElementById("modal_container");
  const form = document.getElementById("registerForm");
  const formStatus = document.getElementById("userFormStatus");
  const saveButton = form.querySelector(".save-btn");
  const csrfToken = document.querySelector(".staff-users-content").dataset.csrfToken;
  let users = [];
  let activeUser = null;

  function setFormStatus(message, isError = false) {
    formStatus.textContent = message;
    formStatus.classList.toggle("is-error", isError);
  }

  function closeModal() {
    overlay.classList.remove("show");
    overlay.setAttribute("aria-hidden", "true");
    activeUser = null;
  }

  function openModal(user) {
    activeUser = user;
    form.elements.first_name.value = user.first_name || "";
    form.elements.last_name.value = user.last_name || "";
    form.elements.email.value = user.email || "";
    form.elements.phone_number.value = user.phone_number || "";
    setFormStatus("");
    overlay.classList.add("show");
    overlay.setAttribute("aria-hidden", "false");
    form.elements.first_name.focus();
  }

  function renderUsers() {
    const query = search.value.trim().toLocaleLowerCase();
    const filteredUsers = users.filter((user) =>
      [user.first_name, user.last_name, user.email, user.phone_number, user.user_id]
        .some((value) => String(value ?? "").toLocaleLowerCase().includes(query)),
    );

    count.textContent = query
      ? `${filteredUsers.length} of ${users.length} users`
      : `${users.length} ${users.length === 1 ? "user" : "users"}`;
    tbody.replaceChildren();

    if (!filteredUsers.length) {
      const row = document.createElement("tr");
      const cell = document.createElement("td");
      cell.colSpan = 5;
      cell.className = "staff-users-empty";
      cell.textContent = query ? "No users match your search." : "No user accounts found.";
      row.append(cell);
      tbody.append(row);
      return;
    }

    for (const user of filteredUsers) {
      const row = document.createElement("tr");
      const userCell = document.createElement("td");
      userCell.dataset.label = "User";
      const identity = document.createElement("div");
      identity.className = "staff-user-identity";
      const avatar = document.createElement("span");
      avatar.className = "staff-user-avatar";
      avatar.setAttribute("aria-hidden", "true");
      avatar.textContent = `${user.first_name?.[0] || ""}${user.last_name?.[0] || ""}`.toLocaleUpperCase();
      const name = document.createElement("strong");
      name.textContent = `${user.first_name || ""} ${user.last_name || ""}`.trim() || "Unnamed user";
      identity.append(avatar, name);
      userCell.append(identity);

      const emailCell = document.createElement("td");
      emailCell.dataset.label = "Email";
      emailCell.textContent = user.email || "—";

      const phoneCell = document.createElement("td");
      phoneCell.dataset.label = "Contact number";
      phoneCell.textContent = user.phone_number || "—";

      const idCell = document.createElement("td");
      idCell.dataset.label = "User ID";
      idCell.textContent = `#${user.user_id}`;

      const actionCell = document.createElement("td");
      actionCell.dataset.label = "Action";
      const editButton = document.createElement("button");
      editButton.type = "button";
      editButton.className = "owner-secondary-button staff-user-edit";
      editButton.textContent = "Edit";
      editButton.setAttribute("aria-label", `Edit ${name.textContent}`);
      editButton.addEventListener("click", () => openModal(user));
      actionCell.append(editButton);

      row.append(userCell, emailCell, phoneCell, idCell, actionCell);
      tbody.append(row);
    }
  }

  async function readJson(response) {
    const data = await response.json();
    if (!response.ok || !data.success) {
      throw new Error(data.message || "The request could not be completed.");
    }
    return data;
  }

  async function loadUsers() {
    status.textContent = "Loading users...";
    try {
      const response = await fetch("../includes/admin-php/get_users.php", {
        headers: { Accept: "application/json" },
      });
      const data = await readJson(response);
      users = data.users;
      renderUsers();
      status.textContent = "";
    } catch (error) {
      status.textContent = error.message || "Unable to load users.";
      status.classList.add("is-error");
      count.textContent = "Users unavailable";
      window.showDialog({
        type: "error",
        title: "Unable to load users",
        message: error.message || "Please refresh the page and try again.",
      });
    }
  }

  search.addEventListener("input", renderUsers);

  document.querySelectorAll(".cancel-btn").forEach((button) => {
    button.addEventListener("click", closeModal);
  });
  overlay.addEventListener("click", (event) => {
    if (event.target === overlay) closeModal();
  });
  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape" && overlay.classList.contains("show")) closeModal();
  });

  form.addEventListener("submit", async (event) => {
    event.preventDefault();
    if (!activeUser || !form.reportValidity()) return;

    saveButton.disabled = true;
    setFormStatus("Saving changes...");
    const payload = {
      user_id: activeUser.user_id,
      first_name: form.elements.first_name.value.trim(),
      last_name: form.elements.last_name.value.trim(),
      email: form.elements.email.value.trim(),
      phone_number: form.elements.phone_number.value.trim(),
    };

    try {
      const response = await fetch("../includes/admin-php/update_user.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "X-CSRF-Token": csrfToken,
        },
        body: JSON.stringify(payload),
      });
      const data = await readJson(response);
      users = users.map((user) => Number(user.user_id) === Number(payload.user_id)
        ? { ...user, ...payload }
        : user);
      renderUsers();
      closeModal();
      window.showDialog({
        type: "success",
        title: "User updated",
        message: data.message || "User details updated successfully.",
      });
    } catch (error) {
      setFormStatus(error.message || "Unable to save changes.", true);
    } finally {
      saveButton.disabled = false;
    }
  });

  loadUsers();
});

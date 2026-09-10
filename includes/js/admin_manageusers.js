document.addEventListener("DOMContentLoaded", async () => {
  // define display table
  const tbody = document.getElementById("usersTable");

  try {
    const response = await fetch("../includes/admin-php/get_users.php");
    const data = await response.json();

    if (!data.success) {
      alert("Failed to load users.");
      return;
    }

    tbody.replaceChildren();

    // populate table with user data
    for (const user of data.users) {
      const newTr = document.createElement("tr");

      const values = [
        user.user_id,
        user.first_name,
        user.last_name,
        user.email,
        user.phone_number,
      ];

      // User data columns
      for (const value of values) {
        const newTd = document.createElement("td");
        newTd.textContent = value;
        newTr.append(newTd);
      }

      // add one more column for edit button
      const actionTd = document.createElement("td");
      const editBtn = document.createElement("button");

      editBtn.textContent = "Update";
      editBtn.className = "update-btn";
      editBtn.dataset.userId = user.user_id;

      // append update button to the last column
      actionTd.append(editBtn);
      newTr.append(actionTd);
      tbody.append(newTr);
    }
  } catch (error) {
    console.error("Error loading users:", error);
  }
});

// open edit modal form
document.addEventListener("click", async (e) => {
  if (e.target.classList.contains("update-btn")) {
    const userId = e.target.dataset.userId;

    const res = await fetch(
      `../includes/admin-php/update_users.php?user_id=${userId}`,
    );
    const result = await res.json();

    if (!result.success) {
      alert(result.message);
      return;
    }

    const user = result.user;

    document.getElementById("registerForm").dataset.userId = user.user_id;
    document.getElementById("first_name").value = user.first_name;
    document.getElementById("last_name").value = user.last_name;
    document.getElementById("email").value = user.email;
    document.getElementById("phone_number").value = user.phone_number;

    modal_container.classList.add("show");
  }
});

//save button
document.addEventListener("click", async (e) => {
  if (e.target.classList.contains("save-btn")) {
    const form = document.getElementById("registerForm");
    const userId = form.dataset.userId;

    const payload = {
      user_id: userId,
      first_name: document.getElementById("first_name").value,
      last_name: document.getElementById("last_name").value,
      email: document.getElementById("email").value,
      phone_number: document.getElementById("phone_number").value,
    };

    const res = await fetch("../includes/admin-php/update_user.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload),
    });

    const result = await res.json();

    if (!result.success) {
      alert(result.message);
      return;
    }

    modal_container.classList.remove("show");
    location.reload(); // refresh table to show updated data
  }
});

//cancel button
document.addEventListener("click", (e) => {
  if (e.target.classList.contains("cancel-btn")) {
    modal_container.classList.remove("show");
  }
});

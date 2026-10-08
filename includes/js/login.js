document.getElementById("loginForm").addEventListener("submit", async (e) => {
  e.preventDefault();

  const form = new FormData(e.target);

  const data = {
    email: form.get("email"),
    password: form.get("password"),
  };

  const response = await fetch("includes/backend-api/login.php", {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
    },
    body: JSON.stringify(data),
  });

  const result = await response.json();

  if (!result.success) {
    if (result.requires_review) {
      window.location.href = "adminregister.php?applicationStatus=1";
      return;
    }
    if (result.requires_verification) {
      if (result.is_facility_owner) {
        window.location.href = "adminregister.php?resumeVerification=1";
        return;
      }
      alert("Your account is not yet verified. Please verify your email.");
      window.location.href = `verify.html?user_id=${result.user_id}`;
      return;
    }

    alert(result.message);
    return;
  }

  let destination = "index.php";
  let destinationLabel = "your CourtConnect home.";

  if (result.user.role_name === "courtconnect") {
    destination = "Admin dashboard/reviewregistrations.php";
    destinationLabel = "the developer review dashboard.";
  } else if (result.user.admin_id || result.user.role_name === "facility_owner") {
    destination = "Admin dashboard/admindex.php";
    destinationLabel = "your facility dashboard.";
  }

  const notification = document.createElement("div");
  const loginRedirectDelay = 2000;
  notification.className = "login-success-overlay";
  notification.style.setProperty("--login-redirect-delay", `${loginRedirectDelay}ms`);
  notification.setAttribute("role", "status");
  notification.innerHTML = `
    <div class="login-success-card">
      <span class="login-success-icon" aria-hidden="true">&#10003;</span>
      <strong>Welcome, ${result.user.first_name}!</strong>
      <small>Taking you to ${destinationLabel}</small>
      <span class="login-success-loader" aria-hidden="true"></span>
    </div>
  `;
  document.body.appendChild(notification);

  window.setTimeout(() => {
    window.location.href = destination;
  }, loginRedirectDelay);
});

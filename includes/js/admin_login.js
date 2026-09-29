document.getElementById("loginForm").addEventListener("submit", async (e) => {
  e.preventDefault();

  const form = new FormData(e.target);

  const data = {
    email: form.get("email"),
    password: form.get("password"),
  };

  const response = await fetch("./includes/backend-api/adminlogin.php", {
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
      window.location.href = "adminregister.php?resumeVerification=1";
      return;
    }
    alert(result.message);
    return;
  }

  const notification = document.createElement("div");
  const loginRedirectDelay = 2000;
  notification.className = "login-success-overlay";
  notification.style.setProperty("--login-redirect-delay", `${loginRedirectDelay}ms`);
  notification.setAttribute("role", "status");
  notification.innerHTML = `
    <div class="login-success-card">
      <span class="login-success-icon" aria-hidden="true">&#10003;</span>
      <strong>Welcome back, ${result.user.first_name}!</strong>
      <small>Taking you to your admin dashboard.</small>
      <span class="login-success-loader" aria-hidden="true"></span>
    </div>
  `;
  document.body.appendChild(notification);

  window.setTimeout(() => {
    window.location.href = "Admin dashboard/admindex.php"; 
  }, loginRedirectDelay);
});
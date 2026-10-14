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
      const userId = result.user_id ?? result.user?.user_id;
      if (result.is_facility_owner) {
        await window.showFacilityOtp(userId);
        return;
      }
      const redirectUrl = new URL("register.php", window.location.href);
      redirectUrl.searchParams.set("resumeVerification", "1");
      if (userId) {
        redirectUrl.searchParams.set("user_id", String(userId));
      }
      window.location.href = redirectUrl.toString();
      return;
    }

    window.showDialog({
      type: "error",
      title: "Sign-in failed",
      message: result.message || "Check your details and try again.",
    });
    return;
  }

  let destination = "index.php";
  let destinationLabel = "your CourtConnect home.";

  if (result.owner_application) {
    destination = "owner_application.php";
    destinationLabel = "your application status.";
  } else if (result.user.role_name === "courtconnect") {
    destination = "Admin dashboard/reviewregistrations.php";
    destinationLabel = "the developer review dashboard.";
  } else if (result.user.admin_id || result.user.role_name === "facility_owner") {
    destination = "Admin dashboard/admindex.php";
    destinationLabel = "your facility dashboard.";
  } else {
    const returnTo = new URLSearchParams(window.location.search).get("return_to");
    if (returnTo) {
      try {
        const returnUrl = new URL(returnTo, window.location.href);
        const appDirectory = window.location.pathname.slice(0, window.location.pathname.lastIndexOf("/") + 1);
        const returnRoute = returnUrl.pathname.slice(appDirectory.length);
        const allowedRoutes = ["courts.php", "mybookings.php", "checkout.php"];
        if (returnUrl.origin === window.location.origin
          && returnUrl.pathname.startsWith(appDirectory)
          && allowedRoutes.includes(returnRoute)) {
          destination = `${returnUrl.pathname}${returnUrl.search}${returnUrl.hash}`;
          destinationLabel = "your requested page.";
        }
      } catch {
        destination = "index.php";
      }
    }
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

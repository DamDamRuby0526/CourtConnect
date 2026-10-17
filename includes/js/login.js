document.getElementById("loginForm").addEventListener("submit", async (e) => {
  e.preventDefault();

  const form = new FormData(e.target);

  const data = {
    email: form.get("email"),
    password: form.get("password"),
  };

  let result;
  try {
    const response = await fetch("includes/backend-api/login.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify(data),
    });
    result = await response.json();
  } catch (error) {
    window.showDialog({
      type: "error",
      title: "Unable to sign in",
      message: error.message || "Could not complete sign-in. Please try again.",
    });
    return;
  }

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
      title: result.account_disabled ? "Account disabled" : "Sign-in failed",
      message: result.message || "Check your details and try again.",
    });
    return;
  }

  let destination = "index.php";
  let destinationLabel = "your CourtConnect home.";

  if (result.owner_application) {
    destination = "owner_application.php";
    destinationLabel = "your application status.";
  } else if (Number(result.user.role_id) === 4) {
    destination = "Admin dashboard/reviewregistrations.php";
    destinationLabel = "the facility applications dashboard.";
  } else if (Number(result.user.role_id) === 5) {
    destination = "Admin dashboard/superadmin.php";
    destinationLabel = "the Superadmin dashboard.";
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
        const allowedRoutes = ["courts.php", "mybookings.php", "checkout.php", "reservation_resume.php"];
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

  if (destination === "Admin dashboard/admindex.php") {
    window.location.assign(destination);
    return;
  }

  const notification = document.createElement("div");
  const loginRedirectDelay = 2000;
  notification.className = "login-success-overlay";
  notification.style.setProperty("--login-redirect-delay", `${loginRedirectDelay}ms`);
  notification.setAttribute("role", "status");
  const card = document.createElement("div");
  card.className = "login-success-card";
  const icon = document.createElement("span");
  icon.className = "login-success-icon";
  icon.setAttribute("aria-hidden", "true");
  icon.textContent = "\u2713";
  const welcome = document.createElement("strong");
  welcome.textContent = `Welcome, ${result.user.first_name}!`;
  const destinationMessage = document.createElement("small");
  destinationMessage.textContent = `Taking you to ${destinationLabel}`;
  const loader = document.createElement("span");
  loader.className = "login-success-loader";
  loader.setAttribute("aria-hidden", "true");
  card.append(icon, welcome, destinationMessage, loader);
  notification.appendChild(card);
  document.body.appendChild(notification);

  window.setTimeout(() => {
    window.location.href = destination;
  }, loginRedirectDelay);
});

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
    if (result.requires_verification) {
      alert("Your account is not yet verified. Please verify your email.");
      window.location.href = `verify.html?user_id=${result.user_id}`;
      return;
    }

    alert(result.message);
    return;
  }

  alert(result.message);
  window.location.href = "index.php";
});

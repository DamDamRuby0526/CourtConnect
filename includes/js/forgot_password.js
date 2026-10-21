const forgotPasswordForm = document.getElementById("forgotPasswordForm");
const forgotEmail = document.getElementById("resetEmail");
const forgotEmailError = document.getElementById("resetEmailError");
const forgotFeedback = document.getElementById("forgotPasswordFeedback");
const forgotSubmit = document.getElementById("forgotPasswordSubmit");
let forgotSubmitting = false;

forgotPasswordForm.addEventListener("submit", async (event) => {
  event.preventDefault();
  if (forgotSubmitting) return;
  forgotEmailError.hidden = true;
  forgotEmailError.textContent = "";
  forgotEmail.setAttribute("aria-invalid", "false");
  forgotFeedback.textContent = "";
  forgotFeedback.classList.remove("is-error", "is-success");

  if (!forgotEmail.validity.valid) {
    forgotEmailError.textContent = forgotEmail.validity.valueMissing
      ? "Enter your email address."
      : "Enter a valid email address.";
    forgotEmailError.hidden = false;
    forgotEmail.setAttribute("aria-invalid", "true");
    forgotEmail.focus();
    return;
  }

  forgotSubmitting = true;
  forgotSubmit.disabled = true;
  forgotSubmit.textContent = "Sending...";
  try {
    const response = await fetch("includes/backend-api/request_password_reset.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ email: forgotEmail.value.trim() }),
    });
    const result = await response.json();
    if (!response.ok || !result.success) {
      throw new Error(result.message || "We couldn’t process your request. Please try again.");
    }
    forgotFeedback.textContent = result.message;
    forgotFeedback.classList.add("is-success");
  } catch (error) {
    forgotFeedback.textContent = error instanceof Error
      ? error.message
      : "We couldn’t process your request. Please try again.";
    forgotFeedback.classList.add("is-error");
  } finally {
    forgotSubmitting = false;
    forgotSubmit.disabled = false;
    forgotSubmit.textContent = "Send Reset Link";
  }
});

forgotEmail.addEventListener("input", () => {
  forgotEmailError.hidden = true;
  forgotEmail.setAttribute("aria-invalid", "false");
});

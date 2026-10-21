const resetForm = document.getElementById("resetPasswordForm");
const newPassword = document.getElementById("newPassword");
const confirmNewPassword = document.getElementById("confirmNewPassword");
const resetFeedback = document.getElementById("resetPasswordFeedback");
const resetSubmit = document.getElementById("resetPasswordSubmit");
let resetSubmitting = false;

function setPasswordError(input, errorId, message) {
  const error = document.getElementById(errorId);
  error.textContent = message;
  error.hidden = message === "";
  input.setAttribute("aria-invalid", String(message !== ""));
}

document.querySelectorAll(".password-reset-visibility").forEach((button) => {
  button.addEventListener("click", () => {
    const input = document.getElementById(button.dataset.passwordTarget);
    const visible = input.type === "password";
    input.type = visible ? "text" : "password";
    button.setAttribute("aria-pressed", String(visible));
    button.setAttribute("aria-label", visible ? "Hide password" : "Show password");
  });
});

function validateResetPasswords() {
  let valid = true;
  const passwordValue = newPassword.value.trim();
  const confirmationValue = confirmNewPassword.value.trim();
  if (passwordValue.length < 8) {
    setPasswordError(newPassword, "newPasswordError", passwordValue ? "Use at least 8 characters." : "Enter a new password.");
    valid = false;
  } else {
    setPasswordError(newPassword, "newPasswordError", "");
  }
  if (!confirmNewPassword.value) {
    setPasswordError(confirmNewPassword, "confirmNewPasswordError", "Confirm your new password.");
    valid = false;
  } else if (passwordValue !== confirmationValue) {
    setPasswordError(confirmNewPassword, "confirmNewPasswordError", "Passwords do not match.");
    valid = false;
  } else {
    setPasswordError(confirmNewPassword, "confirmNewPasswordError", "");
  }
  return valid;
}

newPassword.addEventListener("input", () => {
  setPasswordError(newPassword, "newPasswordError", "");
  if (confirmNewPassword.value) {
    const matching = newPassword.value.trim() === confirmNewPassword.value.trim();
    setPasswordError(
      confirmNewPassword,
      "confirmNewPasswordError",
      matching ? "" : "Passwords do not match."
    );
  }
});
confirmNewPassword.addEventListener("input", () => {
  setPasswordError(
    confirmNewPassword,
    "confirmNewPasswordError",
    !confirmNewPassword.value
      ? "Confirm your new password."
      : newPassword.value.trim() !== confirmNewPassword.value.trim()
        ? "Passwords do not match."
    : ""
  );
});

resetForm.addEventListener("submit", async (event) => {
  event.preventDefault();
  if (resetSubmitting) return;
  resetFeedback.textContent = "";
  resetFeedback.classList.remove("is-error", "is-success");
  if (!validateResetPasswords()) {
    resetForm.querySelector('[aria-invalid="true"]').focus();
    return;
  }

  resetSubmitting = true;
  resetSubmit.disabled = true;
  resetSubmit.textContent = "Updating...";
  try {
    const response = await fetch("includes/backend-api/reset_password.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        token: document.getElementById("resetToken").value,
        password: newPassword.value.trim(),
        confirm_password: confirmNewPassword.value.trim(),
      }),
    });
    const result = await response.json();
    if (!response.ok || !result.success) {
      if (result.field === "confirm_password") {
        setPasswordError(confirmNewPassword, "confirmNewPasswordError", result.message);
        return;
      } else if (result.field === "password") {
        setPasswordError(newPassword, "newPasswordError", result.message);
        return;
      } else if (result.code === "invalid_token") {
        resetFeedback.textContent = result.message || "This reset link is invalid or has expired.";
        const requestLink = document.createElement("a");
        requestLink.href = "forgot_password.php";
        requestLink.textContent = " Request a new reset link.";
        resetFeedback.appendChild(requestLink);
        resetFeedback.classList.add("is-error");
        return;
      }
      throw new Error(result.message || "Unable to update your password. Please try again.");
    }
    resetForm.hidden = true;
    document.getElementById("resetPasswordSuccess").hidden = false;
  } catch (error) {
    resetFeedback.textContent = error instanceof Error
      ? error.message
      : "Unable to update your password. Please try again.";
    resetFeedback.classList.add("is-error");
  } finally {
    resetSubmitting = false;
    if (!resetForm.hidden) {
      resetSubmit.disabled = false;
      resetSubmit.textContent = "Update Password";
    }
  }
});

(() => {
  const overlay = document.getElementById("facilityOtpOverlay");
  const closeButton = document.getElementById("facilityOtpClose");
  const form = document.getElementById("facilityOtpForm");
  const codeInput = document.getElementById("facilityOtpCode");
  const status = document.getElementById("facilityOtpStatus");
  const resendButton = document.getElementById("facilityOtpResend");
  const submitButton = document.getElementById("facilityOtpSubmit");
  if (!overlay || !closeButton || !form || !codeInput || !status || !resendButton || !submitButton) return;

  let currentUserId = null;
  let busy = false;

  function setBusy(isBusy) {
    busy = isBusy;
    resendButton.disabled = isBusy;
    submitButton.disabled = isBusy;
    closeButton.disabled = isBusy;
  }

  function closeModal() {
    if (busy) return;
    overlay.classList.remove("is-open");
    overlay.setAttribute("aria-hidden", "true");
  }

  async function requestCode() {
    if (!currentUserId || busy) return;
    setBusy(true);
    status.textContent = "Sending a verification code…";
    try {
      const response = await fetch("includes/backend-api/sendOTP.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ user_id: currentUserId }),
      });
      const result = await response.json();
      if (!response.ok || !result.success) {
        throw new Error(result.message || "Unable to send a verification code.");
      }
      status.textContent = "A verification code was sent to your email.";
      codeInput.focus();
    } catch (error) {
      status.textContent = "";
      await window.showDialog({
        type: "error",
        title: "Unable to send code",
        message: error.message || "Please try again.",
      });
    } finally {
      setBusy(false);
    }
  }

  window.showFacilityOtp = async (userId) => {
    const parsedUserId = Number(userId);
    if (!Number.isInteger(parsedUserId) || parsedUserId < 1) {
      await window.showDialog({
        type: "error",
        title: "Unable to verify account",
        message: "The sign-in response did not include a valid account.",
      });
      return;
    }
    currentUserId = parsedUserId;
    codeInput.value = "";
    overlay.classList.add("is-open");
    overlay.setAttribute("aria-hidden", "false");
    await requestCode();
  };

  closeButton.addEventListener("click", closeModal);
  resendButton.addEventListener("click", requestCode);
  overlay.addEventListener("click", (event) => {
    if (event.target === overlay) closeModal();
  });
  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape" && overlay.classList.contains("is-open")) closeModal();
  });

  form.addEventListener("submit", async (event) => {
    event.preventDefault();
    if (!currentUserId || busy) return;
    setBusy(true);
    status.textContent = "Verifying your code…";
    try {
      const response = await fetch("includes/backend-api/verifyOTP.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ user_id: currentUserId, otp: codeInput.value.trim() }),
      });
      const result = await response.json();
      if (!response.ok || !result.success) {
        throw new Error(result.message || "Unable to verify this code.");
      }
      status.textContent = "Email verified. Redirecting to your dashboard…";
      window.location.assign("Admin dashboard/admindex.php");
    } catch (error) {
      status.textContent = "";
      await window.showDialog({
        type: "error",
        title: "Verification failed",
        message: error.message || "Please check the code and try again.",
      });
      codeInput.focus();
    } finally {
      setBusy(false);
    }
  });
})();

(() => {
  const overlay = document.getElementById("facilityOtpOverlay");
  const form = document.getElementById("facilityOtpForm");
  const inputsContainer = document.getElementById("facilityOtpInputs");
  const codeInputs = inputsContainer ? [...inputsContainer.querySelectorAll("input")] : [];
  const status = document.getElementById("facilityOtpStatus");
  const resendButton = document.getElementById("facilityOtpResend");
  const resendCountdown = document.getElementById("facilityOtpResendCountdown");
  const submitButton = document.getElementById("facilityOtpSubmit");
  if (!overlay || !form || codeInputs.length !== 6 || !status || !resendButton || !resendCountdown || !submitButton) return;

  let currentUserId = null;
  let busy = false;
  let resendTimer = null;

  function setBusy(isBusy) {
    busy = isBusy;
    submitButton.disabled = isBusy;
    if (isBusy) resendButton.setAttribute("aria-disabled", "true");
    else if (!resendCountdown.textContent) resendButton.removeAttribute("aria-disabled");
  }

  function clearCode() {
    codeInputs.forEach((input) => {
      input.value = "";
    });
    codeInputs[0].focus();
  }

  function startResendCountdown(seconds) {
    window.clearInterval(resendTimer);
    let remaining = seconds;
    const update = () => {
      const minutes = Math.floor(remaining / 60).toString().padStart(2, "0");
      const secondsRemaining = (remaining % 60).toString().padStart(2, "0");
      resendCountdown.textContent = `(${minutes}:${secondsRemaining})`;
    };
    resendButton.setAttribute("aria-disabled", "true");
    update();
    resendTimer = window.setInterval(() => {
      remaining -= 1;
      if (remaining <= 0) {
        window.clearInterval(resendTimer);
        resendCountdown.textContent = "";
        if (!busy) resendButton.removeAttribute("aria-disabled");
        return;
      }
      update();
    }, 1000);
  }

  async function requestCode() {
    if (!currentUserId || busy || resendButton.getAttribute("aria-disabled") === "true") return;
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
      status.textContent = "";
      clearCode();
      startResendCountdown(60);
    } catch (error) {
      status.textContent = error.message || "Unable to send a verification code.";
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
    clearCode();
    status.textContent = "";
    overlay.classList.add("show");
    overlay.setAttribute("aria-hidden", "false");
    codeInputs[0].focus();
    await requestCode();
  };

  resendButton.addEventListener("click", (event) => {
    event.preventDefault();
    requestCode();
  });
  overlay.addEventListener("click", (event) => {
    if (event.target === overlay && !busy) {
      overlay.classList.remove("show");
      overlay.setAttribute("aria-hidden", "true");
    }
  });
  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape" && overlay.classList.contains("show") && !busy) {
      overlay.classList.remove("show");
      overlay.setAttribute("aria-hidden", "true");
    }
    const focusedIndex = codeInputs.indexOf(document.activeElement);
    if (focusedIndex < 0) return;
    if (event.key === "ArrowLeft" && focusedIndex > 0) codeInputs[focusedIndex - 1].focus();
    if (event.key === "ArrowRight" && focusedIndex < codeInputs.length - 1) codeInputs[focusedIndex + 1].focus();
  });

  inputsContainer.addEventListener("input", (event) => {
    const input = event.target;
    const start = codeInputs.indexOf(input);
    if (start < 0) return;
    const digits = input.value.replace(/\D/g, "");
    if (!busy) status.textContent = "";
    if (digits.length > 1) {
      [...digits].slice(0, codeInputs.length - start).forEach((digit, offset) => {
        codeInputs[start + offset].value = digit;
      });
      codeInputs[Math.min(start + digits.length, codeInputs.length - 1)].focus();
      return;
    }
    input.value = digits;
    if (digits && input.nextElementSibling) input.nextElementSibling.focus();
  });
  inputsContainer.addEventListener("keydown", (event) => {
    const index = codeInputs.indexOf(event.target);
    if (event.key === "Backspace" && !event.target.value && index > 0) {
      codeInputs[index - 1].focus();
    }
  });
  inputsContainer.addEventListener("paste", (event) => {
    const pasted = event.clipboardData.getData("text").replace(/\D/g, "").slice(0, 6);
    if (!pasted) return;
    event.preventDefault();
    if (!busy) status.textContent = "";
    codeInputs.forEach((input, index) => {
      input.value = pasted[index] || "";
    });
    codeInputs[Math.min(pasted.length, codeInputs.length - 1)].focus();
  });

  form.addEventListener("submit", async (event) => {
    event.preventDefault();
    if (!currentUserId || busy) return;
    const otp = codeInputs.map((input) => input.value).join("");
    if (!/^\d{6}$/.test(otp)) {
      status.textContent = "Enter all six digits of the code.";
      codeInputs.find((input) => !input.value)?.focus();
      return;
    }
    setBusy(true);
    status.textContent = "Verifying your code…";
    try {
      const response = await fetch("includes/backend-api/verifyOTP.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ user_id: currentUserId, otp }),
      });
      const result = await response.json();
      if (!response.ok || !result.success) {
        throw new Error(result.message || "Unable to verify this code.");
      }
      status.textContent = "Email verified. Redirecting to your dashboard…";
      window.location.assign("Admin dashboard/admindex.php");
    } catch (error) {
      status.textContent = error.message || "Please check the code and try again.";
      clearCode();
    } finally {
      setBusy(false);
    }
  });
})();

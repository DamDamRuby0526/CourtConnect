document
  .getElementById("registerForm")
  .addEventListener("submit", async (e) => {
    e.preventDefault();

    const form = new FormData(e.target);

    if (form.get("email") !== form.get("confirm_email")) {
      alert("Emails do not match.");
      return;
    }

    if (form.get("password") !== form.get("confirm_password")) {
      alert("Passwords do not match.");
      return;
    }

    //get data from form
    const data = {
      first_name: form.get("first_name"),
      last_name: form.get("last_name"),
      email: form.get("email"),
      phone_number: form.get("phone_number"),
      password: form.get("password"),
    };

    const response = await fetch("includes/backend-api/register.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify(data),
    });

    const result = await response.json();

    if (!result.success) {
      alert(result.message);
      return;
    }

    // open OTP modal
    const otpForm = document.getElementById("otpForm");
    otpForm.dataset.userId = result.user_id;

    document.getElementById("modal_container").classList.add("show");

    // Automatically send the OTP as soon as the modal opens
    const sendResponse = await fetch("includes/backend-api/sendOTP.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify({
        user_id: result.user_id,
      }),
    });

    const sendResult = await sendResponse.json();

    if (!sendResult.success) {
      alert(sendResult.message);
    }
  });

// Move through the six OTP fields as the user types.

const inputs = document.getElementById("inputs");
const otpInputs = Array.from(inputs.querySelectorAll('input[name="otp"]'));

inputs.addEventListener("input", function (e) {
  const target = e.target;
  const val = target.value.replace(/\D/g, "").slice(-1);

  target.value = val;

  if (val != "") {
    const next = target.nextElementSibling;
    if (next) {
      next.focus();
    }
  }
});

inputs.addEventListener("keyup", function (e) {
  const target = e.target;
  const key = e.key.toLowerCase();

  if (key == "backspace" || key == "delete") {
    target.value = "";
    const prev = target.previousElementSibling;
    if (prev) {
      prev.focus();
    }
    return;
  }
});

// form actions

document.getElementById("otpForm").addEventListener("submit", async (e) => {
  e.preventDefault();

  const otpForm = e.target;
  const userId = otpForm.dataset.userId;
  const otp = otpInputs.map((input) => input.value).join("");
  const btn = document.getElementById("submitOtpButton");

  if (otp.length !== 6) {
    alert("Please enter all 6 digits of the code sent to your email.");
    return;
  }

  // disable button after submitting once
  btn.disabled = true;
  btn.innerText = "Submitting...";

  try {
    const response = await fetch("includes/backend-api/verifyOTP.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify({
        user_id: userId,
        otp: otp,
      }),
    });

    const result = await response.json();
    alert(result.message);
    // redirect to signin page if successful
    if (result.success) {
      window.location.href = "signin.php";
    }
  } catch (error) {
    alert("Network error. Please try again.");
    btn.disabled = false;
    btn.innerText = "Submit Code";
  }
});

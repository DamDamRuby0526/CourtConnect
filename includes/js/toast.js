const toastInstances = new WeakMap();

function showToast(title, message, type = "success", options = {}) {
  let container = document.getElementById("toast-container");
  if (!container) {
    container = document.createElement("div");
    container.id = "toast-container";
    container.className = "toast-container";
    container.setAttribute("aria-live", "polite");
    container.setAttribute("aria-atomic", "false");
    document.body.append(container);
  }

  const dedupeKey = options.dedupeKey ? String(options.dedupeKey) : "";
  const existingToast = dedupeKey
    ? Array.from(container.querySelectorAll(".toast[data-toast-key]")).find((item) => item.dataset.toastKey === dedupeKey)
    : null;
  if (existingToast) {
    const instance = toastInstances.get(existingToast);
    if (instance) {
      window.clearTimeout(instance.timeout);
      window.clearTimeout(instance.dismissTimeout);
      delete existingToast.dataset.dismissing;
      existingToast.style.animation = "";
      existingToast.querySelector("strong").textContent = title;
      existingToast.querySelector("p").textContent = message;
      existingToast.setAttribute("role", type === "error" ? "alert" : "status");
      instance.scheduleDismiss();
      return existingToast;
    }
  }

  const toast = document.createElement("div");
  const centered = options.position === "center";
  const bookingNotice = options.position === "booking";
  let closeButton = null;
  toast.className = `toast${type === "error" ? " is-error" : ""}${centered ? " is-centered" : ""}${bookingNotice ? " is-booking-notice" : ""}`;
  if (dedupeKey) toast.dataset.toastKey = dedupeKey;
  if (centered) container.classList.add("is-centered");
  if (bookingNotice) container.classList.add("is-booking-notice");
  toast.setAttribute("role", type === "error" ? "alert" : "status");

  const content = document.createElement("div");
  const heading = document.createElement("strong");
  heading.textContent = title;
  const details = document.createElement("p");
  details.textContent = message;
  content.append(heading, details);
  if (bookingNotice) {
    const icon = document.createElement("span");
    icon.className = "toast-booking-icon";
    icon.setAttribute("aria-hidden", "true");
    icon.innerHTML = '<svg viewBox="0 0 20 20" focusable="false"><circle cx="10" cy="10" r="7.25"></circle><path d="M10 5.7v4.6l3 1.8"></path></svg>';

    closeButton = document.createElement("button");
    closeButton.className = "toast-close";
    closeButton.type = "button";
    closeButton.setAttribute("aria-label", "Dismiss notification");
    closeButton.textContent = "×";
    toast.append(icon);
    toast.append(content);
    toast.append(closeButton);
  } else {
    toast.append(content);
  }
  container.append(toast);

  const instance = { timeout: 0, dismissTimeout: 0, scheduleDismiss: null };
  const dismiss = () => {
    if (toast.dataset.dismissing) return;
    toast.dataset.dismissing = "true";
    toast.style.animation = bookingNotice ? "toast-booking-out .2s ease-in forwards" : "toast-out .25s ease-in forwards";
    window.clearTimeout(instance.timeout);
    instance.dismissTimeout = window.setTimeout(() => {
      toast.remove();
      if (!container.querySelector(".toast.is-centered")) container.classList.remove("is-centered");
      if (!container.querySelector(".toast.is-booking-notice")) container.classList.remove("is-booking-notice");
    }, 250);
  };
  instance.scheduleDismiss = () => {
    window.clearTimeout(instance.timeout);
    instance.timeout = window.setTimeout(dismiss, 4000);
  };
  toastInstances.set(toast, instance);

  if (closeButton) {
    closeButton.addEventListener("click", (event) => {
      event.stopPropagation();
      dismiss();
    });
  } else {
    toast.addEventListener("click", dismiss);
  }
  instance.scheduleDismiss();
  return toast;
}

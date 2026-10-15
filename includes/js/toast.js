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

  const toast = document.createElement("div");
  const centered = options.position === "center";
  toast.className = `toast${type === "error" ? " is-error" : ""}${centered ? " is-centered" : ""}`;
  if (centered) container.classList.add("is-centered");
  toast.setAttribute("role", type === "error" ? "alert" : "status");

  const content = document.createElement("div");
  const heading = document.createElement("strong");
  heading.textContent = title;
  const details = document.createElement("p");
  details.textContent = message;
  content.append(heading, details);
  toast.append(content);
  container.append(toast);

  let timeout;
  const dismiss = () => {
    if (toast.dataset.dismissing) return;
    toast.dataset.dismissing = "true";
    toast.style.animation = "toast-out .25s ease-in forwards";
    window.clearTimeout(timeout);
    window.setTimeout(() => {
      toast.remove();
      if (!container.querySelector(".toast.is-centered")) container.classList.remove("is-centered");
    }, 250);
  };

  toast.addEventListener("click", dismiss);
  timeout = window.setTimeout(dismiss, 4000);
}

function showToast(title, message, type = "success") {
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
  toast.className = `toast${type === "error" ? " is-error" : ""}`;
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
    window.setTimeout(() => toast.remove(), 250);
  };

  toast.addEventListener("click", dismiss);
  timeout = window.setTimeout(dismiss, 4000);
}

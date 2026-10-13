(function () {
  const dialogQueue = [];
  let activeDialog = null;

  function closeActiveDialog(result) {
    if (!activeDialog) return;

    const { overlay, resolve, trigger } = activeDialog;
    activeDialog = null;

    if (overlay && overlay.parentNode) {
      overlay.remove();
    }

    resolve(result);

    const next = dialogQueue.shift();
    if (next) {
      openDialog(next);
    }

    if (trigger && typeof trigger.focus === "function") {
      window.setTimeout(() => trigger.focus(), 0);
    }
  }

  function openDialog(entry) {
    if (!entry) return;

    const { config, resolve, trigger } = entry;
    const overlay = document.createElement("div");
    overlay.className = "admin-dialog-overlay";
    overlay.setAttribute("role", "presentation");

    const card = document.createElement("div");
    card.className = "admin-dialog-card";
    card.setAttribute("role", "dialog");
    card.setAttribute("aria-modal", "true");
    card.setAttribute("aria-labelledby", "adminDialogTitle");
    card.setAttribute("aria-label", config.title || "Dialog");

    const icon = document.createElement("div");
    icon.className = "admin-dialog-icon";
    if (config.type === "error") {
      icon.textContent = "!";
      icon.classList.add("is-error");
    } else if (config.type === "success") {
      icon.textContent = "✓";
      icon.classList.add("is-success");
    } else {
      icon.textContent = "?";
      icon.classList.add("is-confirm");
    }

    const title = document.createElement("h3");
    title.id = "adminDialogTitle";
    title.className = "admin-dialog-title";
    title.textContent = config.title || "Notice";

    const message = document.createElement("p");
    message.className = "admin-dialog-message";
    message.textContent = config.message || "";

    const actions = document.createElement("div");
    actions.className = "admin-dialog-actions";

    if (config.type === "confirm") {
      const cancelButton = document.createElement("button");
      cancelButton.type = "button";
      cancelButton.className = "admin-dialog-button admin-dialog-button-secondary";
      cancelButton.textContent = config.cancelText || "Cancel";
      cancelButton.addEventListener("click", () => closeActiveDialog(false));

      const confirmButton = document.createElement("button");
      confirmButton.type = "button";
      confirmButton.className = "admin-dialog-button admin-dialog-button-primary";
      confirmButton.textContent = config.confirmText || "Confirm";
      confirmButton.addEventListener("click", () => closeActiveDialog(true));

      actions.append(cancelButton, confirmButton);
      card.append(icon, title, message, actions);
      confirmButton.focus();
    } else if (config.type === "error") {
      const closeButton = document.createElement("button");
      closeButton.type = "button";
      closeButton.className = "admin-dialog-button admin-dialog-button-primary admin-dialog-button-danger";
      closeButton.textContent = "OK";
      closeButton.addEventListener("click", () => closeActiveDialog(false));

      actions.append(closeButton);
      card.append(icon, title, message, actions);
      closeButton.focus();
    } else {
      const closeButton = document.createElement("button");
      closeButton.type = "button";
      closeButton.className = "admin-dialog-button admin-dialog-button-primary";
      closeButton.textContent = "Close";
      closeButton.addEventListener("click", () => closeActiveDialog(false));

      actions.append(closeButton);
      card.append(icon, title, message, actions);
      closeButton.focus();

      window.setTimeout(() => {
        if (activeDialog && activeDialog.overlay === overlay) {
          closeActiveDialog(false);
        }
      }, 2500);
    }

    overlay.addEventListener("click", (event) => {
      if (event.target === overlay) {
        if (config.type === "confirm" && config.isLoading) return;
        closeActiveDialog(false);
      }
    });

    document.body.append(overlay);
    overlay.append(card);

    const modalEntry = { overlay, resolve, trigger, config };
    activeDialog = modalEntry;

    if (config.type === "confirm") {
      const primary = card.querySelector(".admin-dialog-button-primary");
      if (primary) primary.focus();
    }
  }

  document.addEventListener("keydown", (event) => {
    if (event.key !== "Escape" || !activeDialog) return;
    if (activeDialog.config.type === "confirm" && activeDialog.config.isLoading) return;
    closeActiveDialog(false);
  });

  window.showDialog = function showDialog(options = {}) {
    const config = {
      type: "confirm",
      title: "",
      message: "",
      confirmText: "Confirm",
      cancelText: "Cancel",
      isLoading: false,
      ...options,
    };

    return new Promise((resolve) => {
      const entry = {
        config,
        resolve,
        trigger: document.activeElement && document.activeElement instanceof HTMLElement ? document.activeElement : null,
      };

      if (activeDialog) {
        dialogQueue.push(entry);
        return;
      }

      openDialog(entry);
    });
  };

  window.showDialog.setLoading = function setLoading(isLoading) {
    if (!activeDialog || activeDialog.config.type !== "confirm") return;
    activeDialog.config.isLoading = !!isLoading;
    const primary = activeDialog.overlay.querySelector(".admin-dialog-button-primary");
    const secondary = activeDialog.overlay.querySelector(".admin-dialog-button-secondary");
    if (primary) primary.disabled = !!isLoading;
    if (secondary) secondary.disabled = !!isLoading;
  };
})();
document.addEventListener("DOMContentLoaded", () => {
  const list = document.getElementById("pendingRegistrations");
  const statusMessage = document.getElementById("reviewStatus");
  const page = document.querySelector(".staff-application-content");
  const searchInput = document.getElementById("application-search");
  const sortSelect = document.getElementById("application-sort");
  const overlay = document.getElementById("review-modal-overlay");
  const dialog = overlay.querySelector(".application-review-modal");
  const viewer = document.getElementById("document-viewer");
  const docHeading = document.getElementById("document-heading");
  const detailsList = document.getElementById("review-applicant-details");
  const strip = document.getElementById("document-strip");
  const approveButton = document.getElementById("approve-application");
  const rejectionForm = document.getElementById("rejection-form");
  const rejectionReason = document.getElementById("rejection-reason");
  const rejectionMessage = document.getElementById("rejection-validation-message");
  const decisionArea = document.querySelector(".review-decision-area");
  const docs = [
    { key: "gov_id", label: "Government ID" },
    { key: "business_registration", label: "Business registration" },
    { key: "mayors_permit", label: "Mayor's permit" },
    { key: "proof_of_property", label: "Proof of property" },
  ];
  let registrations = [];
  let activeFilter = "Pending";
  let activeApplication = null;
  let currentDocumentIndex = 0;
  let viewedDocuments = new Set();
  let returnFocus = null;
  let imageScale = 1;
  let imageRotation = 0;
  let imagePosition = { x: 0, y: 0 };
  let dragOrigin = null;
  let requestPending = false;
  let documentRequestId = 0;
  const csrfToken = page.dataset.csrfToken;
  const relativeTime = new Intl.RelativeTimeFormat(undefined, { numeric: "auto" });

  function elapsedLabel(dateValue) {
    const date = new Date(dateValue.replace(" ", "T"));
    if (Number.isNaN(date.getTime())) return dateValue || "Date unavailable";
    const seconds = Math.round((date.getTime() - Date.now()) / 1000);
    const units = [
      ["year", 31536000],
      ["month", 2592000],
      ["week", 604800],
      ["day", 86400],
      ["hour", 3600],
      ["minute", 60],
    ];
    for (const [unit, size] of units) {
      if (Math.abs(seconds) >= size) return relativeTime.format(Math.round(seconds / size), unit);
    }
    return relativeTime.format(seconds, "second");
  }

  function fullDate(dateValue) {
    if (!dateValue) return "Not available";
    const date = new Date(dateValue.replace(" ", "T"));
    return Number.isNaN(date.getTime()) ? dateValue : date.toLocaleString();
  }

  function statusLabel(status) {
    return status === "Active" ? "Approved" : status;
  }

  function updateTabCounts() {
    const counts = {
      Pending: registrations.filter((application) => application.status === "Pending").length,
      Active: registrations.filter((application) => application.status === "Active").length,
      Rejected: registrations.filter((application) => application.status === "Rejected").length,
      All: registrations.length,
    };
    for (const [key, count] of Object.entries(counts)) {
      const target = document.querySelector(`[data-tab-count="${key}"]`);
      target.textContent = String(count);
    }
    const badge = document.querySelector(".staff-pending-badge");
    if (badge) badge.textContent = String(counts.Pending);
  }

  function createTextElement(tag, className, text) {
    const element = document.createElement(tag);
    if (className) element.className = className;
    element.textContent = text;
    return element;
  }

  function createDocumentChip(application, documentInfo) {
    const uploaded = application.documents[documentInfo.key]?.available;
    const button = document.createElement("button");
    button.type = "button";
    button.className = `application-document-chip ${uploaded ? "is-uploaded" : "is-missing"}`;
    button.setAttribute("aria-label", `${documentInfo.label}: ${uploaded ? "uploaded, open to review" : "Missing, open to review"}`);
    const icon = createTextElement("span", "application-document-icon", uploaded ? "✓" : "!");
    icon.setAttribute("aria-hidden", "true");
    const label = createTextElement("span", "", uploaded ? documentInfo.label : `${documentInfo.label} · Missing`);
    button.append(icon, label);
    button.addEventListener("click", () => openReview(application, docs.indexOf(documentInfo), button));
    return button;
  }

  function createApplicationCard(application) {
    const card = document.createElement("article");
    card.className = "application-card";
    card.dataset.applicationId = application.admin_id;

    const identity = document.createElement("div");
    identity.className = "application-card-identity";
    identity.append(
      createTextElement("h2", "", `${application.first_name} ${application.last_name}`),
      createTextElement("p", "application-contact", application.email),
      createTextElement("p", "application-contact", application.phone_number || "Phone not added"),
    );

    const facility = document.createElement("div");
    facility.className = "application-card-facility";
    facility.append(
      createTextElement("h3", "", application.facility_name || "Not added yet"),
      createTextElement("p", "", application.municipality || "Not added yet"),
      createTextElement("small", "", `${application.sport_type || "Facility"} · ${application.address || "Address not added"}`),
    );

    const documents = document.createElement("div");
    documents.className = "application-card-documents";
    for (const documentInfo of docs) documents.append(createDocumentChip(application, documentInfo));

    const meta = document.createElement("div");
    meta.className = "application-card-meta";
    const status = createTextElement("span", `application-status-badge is-${application.status.toLowerCase()}`, statusLabel(application.status));
    const submitted = createTextElement("time", "application-submitted", `${elapsedLabel(application.created_at)} · ${fullDate(application.created_at)}`);
    submitted.dateTime = application.created_at;
    meta.append(status, submitted);

    const action = document.createElement("button");
    action.type = "button";
    action.className = "owner-primary-button application-review-button";
    action.textContent = "Review";
    action.addEventListener("click", () => openReview(application, 0, action));

    card.append(identity, facility, documents, meta, action);
    return card;
  }

  function renderApplications() {
    const query = searchInput.value.trim().toLocaleLowerCase();
    const visible = registrations.filter((application) => {
      const matchesStatus = activeFilter === "All"
        || (activeFilter === "Active" ? application.status === "Active" : application.status === activeFilter);
      const searchable = [
        application.first_name,
        application.last_name,
        application.email,
        application.facility_name,
      ].join(" ").toLocaleLowerCase();
      return matchesStatus && searchable.includes(query);
    });
    visible.sort((left, right) => {
      const direction = sortSelect.value === "oldest" ? 1 : -1;
      return direction * (new Date(left.created_at) - new Date(right.created_at));
    });
    list.replaceChildren();
    if (!visible.length) {
      const empty = document.createElement("div");
      empty.className = "application-empty-state";
      const emptyTitle = activeFilter === "Pending" ? "No applications waiting for review" : `No ${activeFilter.toLowerCase()} applications`;
      empty.append(createTextElement("h2", "", emptyTitle));
      empty.append(createTextElement("p", "", query ? "Try a different search." : "New applications will appear here when submitted."));
      list.append(empty);
      return;
    }
    visible.forEach((application) => list.append(createApplicationCard(application)));
  }

  async function loadRegistrations() {
    statusMessage.textContent = "Loading applications...";
    try {
      const response = await fetch("../includes/admin-php/get_pending_registrations.php", { cache: "no-store" });
      const result = await response.json();
      if (!response.ok || !result.success) throw new Error(result.message || "Unable to load applications.");
      registrations = result.registrations;
      updateTabCounts();
      renderApplications();
      statusMessage.textContent = `${result.pending_count} application${result.pending_count === 1 ? "" : "s"} waiting for review.`;
    } catch (error) {
      statusMessage.textContent = error.message || "Unable to load applications.";
      await window.showDialog({ type: "error", title: "Unable to load applications", message: statusMessage.textContent });
    }
  }

  function documentUrl(documentInfo) {
    return `../includes/admin-php/view_document.php?application_id=${encodeURIComponent(activeApplication.admin_id)}&doc=${encodeURIComponent(documentInfo.key)}`;
  }

  function resetImageTransform() {
    imageScale = 1;
    imageRotation = 0;
    imagePosition = { x: 0, y: 0 };
    updateImageTransform();
  }

  function updateImageTransform() {
    const image = viewer.querySelector("img");
    if (!image) return;
    image.style.transform = `translate(${imagePosition.x}px, ${imagePosition.y}px) scale(${imageScale}) rotate(${imageRotation}deg)`;
    image.classList.toggle("is-zoomed", imageScale > 1);
  }

  function markDocumentViewed(documentInfo) {
    viewedDocuments.add(documentInfo.key);
    const thumbnail = strip.querySelector(`[data-document-key="${documentInfo.key}"]`);
    if (thumbnail) {
      thumbnail.classList.add("is-viewed");
      thumbnail.setAttribute("aria-label", `${documentInfo.label}, viewed`);
    }
    updateDecisionState();
  }

  function renderDocumentStrip() {
    strip.replaceChildren();
    docs.forEach((documentInfo, index) => {
      const button = document.createElement("button");
      button.type = "button";
      button.className = "review-document-step";
      button.dataset.documentKey = documentInfo.key;
      button.setAttribute("aria-label", `${documentInfo.label}${viewedDocuments.has(documentInfo.key) ? ", viewed" : ""}`);
      const check = createTextElement("span", "review-document-step-check", viewedDocuments.has(documentInfo.key) ? "✓" : String(index + 1));
      const label = createTextElement("span", "", documentInfo.label);
      button.append(check, label);
      if (index === currentDocumentIndex) button.classList.add("is-current");
      if (viewedDocuments.has(documentInfo.key)) button.classList.add("is-viewed");
      button.addEventListener("click", () => showDocument(index));
      strip.append(button);
    });
  }

  async function showDocument(index) {
    if (!activeApplication) return;
    const requestId = ++documentRequestId;
    currentDocumentIndex = (index + docs.length) % docs.length;
    const documentInfo = docs[currentDocumentIndex];
    const documentState = activeApplication.documents[documentInfo.key];
    docHeading.textContent = `Document ${currentDocumentIndex + 1} of ${docs.length}: ${documentInfo.label}`;
    document.getElementById("document-previous").disabled = requestPending;
    document.getElementById("document-next").disabled = requestPending;
    renderDocumentStrip();
    viewer.replaceChildren();
    document.getElementById("image-controls").hidden = true;
    const openNew = document.getElementById("document-open-new");
    openNew.hidden = true;
    openNew.removeAttribute("href");

    if (!documentState?.available) {
      viewer.append(createTextElement("p", "review-document-placeholder is-missing", "File missing"));
      updateDecisionState();
      return;
    }

    const url = documentUrl(documentInfo);
    try {
      const response = await fetch(url, { method: "HEAD", cache: "no-store" });
      if (requestId !== documentRequestId) return;
      if (!response.ok) throw new Error("Document file missing");
      openNew.href = url;
      openNew.hidden = false;
      if (documentState.mime === "application/pdf") {
        const object = document.createElement("object");
        object.type = "application/pdf";
        object.data = url;
        object.setAttribute("aria-label", `${documentInfo.label}, PDF document`);
        object.addEventListener("load", () => markDocumentViewed(documentInfo), { once: true });
        object.addEventListener("error", () => {
          if (viewer.contains(object)) {
            viewer.replaceChildren(createTextElement("p", "review-document-placeholder is-missing", "File missing"));
          }
        }, { once: true });
        object.append(createTextElement("p", "", "Unable to display this PDF. Use Open in new tab."));
        viewer.append(object);
      } else if (["image/jpeg", "image/png", "image/webp"].includes(documentState.mime)) {
        const image = document.createElement("img");
        image.alt = `${documentInfo.label}, page 1`;
        image.onload = () => markDocumentViewed(documentInfo);
        image.onerror = () => {
          if (viewer.contains(image)) {
            viewer.replaceChildren(createTextElement("p", "review-document-placeholder is-missing", "File missing"));
            document.getElementById("image-controls").hidden = true;
            openNew.hidden = true;
          }
        };
        image.src = url;
        viewer.append(image);
        document.getElementById("image-controls").hidden = false;
        resetImageTransform();
      } else {
        viewer.append(createTextElement("p", "review-document-placeholder is-missing", "File missing"));
      }
    } catch {
      if (requestId !== documentRequestId) return;
      viewer.replaceChildren(createTextElement("p", "review-document-placeholder is-missing", "File missing"));
      document.getElementById("image-controls").hidden = true;
      openNew.hidden = true;
    }
  }

  function updateDecisionState() {
    const allAvailable = docs.every((documentInfo) => activeApplication?.documents[documentInfo.key]?.available);
    const allViewed = docs.every((documentInfo) => viewedDocuments.has(documentInfo.key));
    const pending = activeApplication?.status === "Pending";
    approveButton.disabled = requestPending || !pending || !allAvailable || !allViewed;
    document.getElementById("approve-help").textContent = !pending
      ? "This application has already been reviewed."
      : !allAvailable
        ? "All four documents must be uploaded before approval."
        : allViewed
          ? "All documents have been opened."
          : "Open all documents to enable approval.";
    document.getElementById("show-rejection").disabled = requestPending || !pending;
  }

  function addDetail(label, value) {
    const term = createTextElement("dt", "", label);
    const description = createTextElement("dd", "", value || "Not added yet");
    detailsList.append(term, description);
  }

  function openReview(application, documentIndex, trigger) {
    activeApplication = application;
    currentDocumentIndex = documentIndex;
    viewedDocuments = new Set();
    requestPending = false;
    returnFocus = trigger;
    detailsList.replaceChildren();
    addDetail("Applicant", `${application.first_name} ${application.last_name}`);
    addDetail("Email", application.email);
    addDetail("Phone", application.phone_number);
    addDetail("ID type", "Not specified");
    addDetail("Facility", application.facility_name);
    addDetail("Address", application.address);
    addDetail("Municipality", application.municipality);
    addDetail("Submitted", fullDate(application.created_at));
    document.getElementById("review-modal-title").textContent = `${application.first_name} ${application.last_name}`;
    const readonly = document.getElementById("review-readonly-state");
    readonly.hidden = application.status === "Pending";
    readonly.textContent = application.status === "Pending"
      ? ""
      : `Already ${statusLabel(application.status).toLowerCase()}${application.reviewer_first_name ? ` by ${application.reviewer_first_name} ${application.reviewer_last_name}` : ""}${application.reviewed_at ? ` on ${fullDate(application.reviewed_at)}` : ""}.`;
    rejectionForm.hidden = true;
    rejectionReason.value = "";
    document.getElementById("rejection-character-count").textContent = "0 / 500";
    document.getElementById("rejection-validation-message").textContent = "";
    document.getElementById("show-rejection").textContent = "Reject";
    document.querySelectorAll('input[name="rejected_documents"]').forEach((input) => { input.checked = false; });
    document.querySelector(".review-modal-columns").classList.remove("is-rejecting");
    updateDecisionState();
    overlay.classList.add("show");
    overlay.setAttribute("aria-hidden", "false");
    dialog.focus();
    showDocument(documentIndex);
  }

  function closeReview() {
    documentRequestId++;
    overlay.classList.remove("show");
    overlay.setAttribute("aria-hidden", "true");
    activeApplication = null;
    if (returnFocus && document.contains(returnFocus)) returnFocus.focus();
  }

  async function submitDecision(decision, reason = "", rejectedDocuments = []) {
    if (!activeApplication || requestPending) return;
    if (decision === "Rejected") {
      const reasonLength = [...reason.trim()].length;
      if (reasonLength < 10 || reasonLength > 500) {
        rejectionMessage.textContent = "Enter a rejection reason between 10 and 500 characters.";
        await window.showDialog({
          type: "error",
          title: "Rejection reason required",
          message: "Enter a reason between 10 and 500 characters before sending the rejection.",
        });
        return;
      }
      if (!rejectedDocuments.length) {
        rejectionMessage.textContent = "Select at least one document that needs to be replaced.";
        await window.showDialog({
          type: "error",
          title: "Select documents to replace",
          message: "Choose at least one document that the owner needs to replace.",
        });
        return;
      }
    }

    const confirmed = await window.showDialog({
      type: "confirm",
      title: decision === "Approved" ? "Approve this application?" : "Send this rejection?",
      message: decision === "Approved"
        ? "The owner will be able to access their dashboard."
        : "The owner will see your reason and can resubmit.",
      confirmText: decision === "Approved" ? "Approve" : "Send rejection",
      cancelText: "Cancel",
    });
    if (!confirmed) return;

    requestPending = true;
    approveButton.disabled = true;
    document.getElementById("show-rejection").disabled = true;
    document.getElementById("send-rejection").disabled = true;
    try {
      const response = await fetch("../includes/admin-php/review_application.php", {
        method: "POST",
        headers: { "Content-Type": "application/json", "X-CSRF-Token": csrfToken },
        body: JSON.stringify({
          application_id: activeApplication.admin_id,
          decision,
          reason,
          rejected_documents: rejectedDocuments,
        }),
      });
      const result = await response.json();
      if (result.already_reviewed) {
        await window.showDialog({ type: "error", title: "Already reviewed", message: result.message });
        closeReview();
        await loadRegistrations();
        return;
      }
      if (!response.ok || !result.success) throw new Error(result.message || "Unable to save the decision.");

      closeReview();
      await loadRegistrations();
      const nextPending = registrations
        .filter((application) => application.status === "Pending")
        .sort((left, right) => new Date(left.created_at) - new Date(right.created_at))[0];
      const nextAction = await window.showDialog({
        type: "success",
        title: decision === "Approved" ? "Application approved" : "Application rejected",
        message: result.email_sent
          ? "The application row and status counts have been updated. The owner was emailed."
          : "The application row and status counts have been updated. The email could not be sent; the failure was logged.",
        ...(nextPending ? { actionText: "Review next", closeText: "Done" } : {}),
      });
      if (nextAction === "action" && nextPending) {
        const nextCard = list.querySelector(`[data-application-id="${nextPending.admin_id}"]`);
        openReview(nextPending, 0, nextCard?.querySelector(".application-review-button") || null);
        window.setTimeout(() => dialog.focus(), 0);
      }
    } catch (error) {
      await window.showDialog({ type: "error", title: "Unable to save decision", message: error.message || "Please try again." });
    } finally {
      requestPending = false;
      document.getElementById("send-rejection").disabled = false;
      updateDecisionState();
    }
  }

  document.querySelectorAll("[data-status-filter]").forEach((button) => {
    button.addEventListener("click", () => {
      activeFilter = button.dataset.statusFilter;
      document.querySelectorAll("[data-status-filter]").forEach((tab) => {
        const selected = tab === button;
        tab.classList.toggle("is-active", selected);
        tab.setAttribute("aria-selected", String(selected));
      });
      if (activeFilter === "Pending") sortSelect.value = "oldest";
      renderApplications();
    });
  });
  searchInput.addEventListener("input", renderApplications);
  sortSelect.addEventListener("change", renderApplications);
  document.getElementById("document-previous").addEventListener("click", () => showDocument(currentDocumentIndex - 1));
  document.getElementById("document-next").addEventListener("click", () => showDocument(currentDocumentIndex + 1));
  document.querySelectorAll("[data-close-review]").forEach((button) => button.addEventListener("click", closeReview));
  overlay.addEventListener("click", (event) => {
    if (event.target === overlay && !requestPending) closeReview();
  });
  document.getElementById("approve-application").addEventListener("click", () => submitDecision("Approved"));
  document.getElementById("show-rejection").addEventListener("click", () => {
    rejectionForm.hidden = !rejectionForm.hidden;
    document.querySelector(".review-modal-columns").classList.toggle("is-rejecting", !rejectionForm.hidden);
    document.getElementById("show-rejection").textContent = rejectionForm.hidden ? "Reject" : "Cancel rejection";
    if (!rejectionForm.hidden) rejectionReason.focus();
  });
  rejectionReason.addEventListener("input", () => {
    document.getElementById("rejection-character-count").textContent = `${[...rejectionReason.value].length} / 500`;
    rejectionMessage.textContent = "";
  });
  document.querySelectorAll("[data-reason]").forEach((button) => {
    button.addEventListener("click", () => {
      const text = button.dataset.reason;
      rejectionReason.value = text === "Other: " ? text : `${text} `;
      rejectionReason.focus();
      rejectionReason.setSelectionRange(rejectionReason.value.length, rejectionReason.value.length);
      rejectionReason.dispatchEvent(new Event("input"));
    });
  });
  rejectionForm.addEventListener("submit", async (event) => {
    event.preventDefault();
    const selected = [...document.querySelectorAll('input[name="rejected_documents"]:checked')].map((input) => input.value);
    await submitDecision("Rejected", rejectionReason.value, selected);
  });
  document.querySelectorAll("[data-image-action]").forEach((button) => {
    button.addEventListener("click", () => {
      if (button.dataset.imageAction === "zoom-in") imageScale = Math.min(4, imageScale + 0.25);
      if (button.dataset.imageAction === "zoom-out") imageScale = Math.max(1, imageScale - 0.25);
      if (button.dataset.imageAction === "rotate") imageRotation = (imageRotation + 90) % 360;
      if (button.dataset.imageAction === "reset") resetImageTransform();
      updateImageTransform();
    });
  });
  viewer.addEventListener("wheel", (event) => {
    if (!viewer.querySelector("img")) return;
    event.preventDefault();
    imageScale = Math.max(1, Math.min(4, imageScale + (event.deltaY < 0 ? 0.1 : -0.1)));
    updateImageTransform();
  }, { passive: false });
  viewer.addEventListener("pointerdown", (event) => {
    if (imageScale <= 1 || !viewer.querySelector("img")) return;
    dragOrigin = { x: event.clientX - imagePosition.x, y: event.clientY - imagePosition.y };
    viewer.setPointerCapture(event.pointerId);
  });
  viewer.addEventListener("pointermove", (event) => {
    if (!dragOrigin) return;
    imagePosition = { x: event.clientX - dragOrigin.x, y: event.clientY - dragOrigin.y };
    updateImageTransform();
  });
  viewer.addEventListener("pointerup", () => { dragOrigin = null; });
  document.addEventListener("keydown", (event) => {
    if (!overlay.classList.contains("show")) return;
    if (document.querySelector(".admin-dialog-overlay")) return;
    if (event.key === "Escape") {
      if (!requestPending) closeReview();
    } else if (event.key === "ArrowLeft" && !["INPUT", "TEXTAREA"].includes(document.activeElement.tagName)) {
      showDocument(currentDocumentIndex - 1);
    } else if (event.key === "ArrowRight" && !["INPUT", "TEXTAREA"].includes(document.activeElement.tagName)) {
      showDocument(currentDocumentIndex + 1);
    } else if (event.key === "Tab") {
      const focusable = [...dialog.querySelectorAll('button:not([disabled]), a[href], input:not([disabled]), textarea:not([disabled]), select:not([disabled]), [tabindex="0"]')]
        .filter((element) => !element.hidden && element.getClientRects().length);
      if (!focusable.length) return;
      const first = focusable[0];
      const last = focusable[focusable.length - 1];
      if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
      } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
      }
    }
  });

  loadRegistrations();
});

document.addEventListener("DOMContentLoaded", () => {
  const tableBody = document.getElementById("pendingRegistrations");
  const statusMessage = document.getElementById("reviewStatus");

  const addCell = (row, content) => {
    const cell = document.createElement("td");
    if (content instanceof Node) {
      cell.append(content);
    } else {
      cell.textContent = content || "Not provided";
    }
    row.append(cell);
    return cell;
  };

  const addFileLink = (container, folder, fileName, label) => {
    if (!fileName) return;
    const link = document.createElement("a");
    link.href = `../includes/uploads/${folder}/${encodeURIComponent(fileName)}`;
    link.target = "_blank";
    link.rel = "noopener";
    link.textContent = label;
    container.append(link, document.createElement("br"));
  };

  async function loadRegistrations() {
    statusMessage.textContent = "Loading applications...";

    try {
      const response = await fetch("../includes/admin-php/get_pending_registrations.php");
      const result = await response.json();
      if (!result.success) throw new Error(result.message);

      tableBody.replaceChildren();
      if (result.registrations.length === 0) {
        statusMessage.textContent = "There are no applications waiting for review.";
        return;
      }

      statusMessage.textContent = `${result.registrations.length} application(s) waiting for review.`;

      for (const application of result.registrations) {
        const row = document.createElement("tr");
        addCell(row, `${application.first_name} ${application.last_name}\n${application.email}\n${application.phone_number}`);
        addCell(row, `${application.facility_name}\n${application.sport_type}\n${application.address}, ${application.municipality}`);

        const documents = document.createElement("div");
        addFileLink(documents, "verification", application.government_id_img, "Government ID");
        addFileLink(documents, "verification", application.business_reg_img, "DTI / SEC registration");
        addFileLink(documents, "verification", application.mayors_permit_img, "Business permit");
        addFileLink(documents, "verification", application.proof_of_property_img, "Ownership / lease proof");
        addFileLink(documents, "facility", application.facility_img, "Facility photo");
        addCell(row, documents);

        const actions = document.createElement("div");
        for (const [label, status] of [["Approve", "Active"], ["Reject", "Rejected"]]) {
          const button = document.createElement("button");
          button.type = "button";
          button.textContent = label;
          button.addEventListener("click", async () => {
            const reason = status === "Rejected" ? prompt("Reason for rejection:") : "";
            if (status === "Rejected" && !reason?.trim()) return;

            button.disabled = true;
            try {
              const reviewResponse = await fetch("../includes/admin-php/review_registration.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ admin_id: application.admin_id, status, rejection_reason: reason })
              });
              const reviewResult = await reviewResponse.json();
              if (!reviewResult.success) throw new Error(reviewResult.message);
              await loadRegistrations();
            } catch (error) {
              statusMessage.textContent = error.message;
              button.disabled = false;
            }
          });
          actions.append(button, document.createTextNode(" "));
        }
        addCell(row, actions);
        tableBody.append(row);
      }
    } catch (error) {
      statusMessage.textContent = error.message || "Unable to load applications.";
    }
  }

  loadRegistrations();
});
(() => {
  const allowedDocumentTypes = new Set(["application/pdf"]);
  const allowedImageTypes = new Set(["image/jpeg", "image/png", "image/webp"]);

  function fileType(file, allowedTypes, invalidTypeMessage) {
    const extensions = {
      jpg: "image/jpeg",
      jpeg: "image/jpeg",
      png: "image/png",
      webp: "image/webp",
      pdf: "application/pdf",
    };
    const extension = file.name.split(".").pop()?.toLowerCase() || "";
    const type = file.type || extensions[extension] || "";
    if (allowedTypes.has("application/pdf")) {
      return extension === "pdf" && (!file.type || file.type === "application/pdf")
        ? ""
        : invalidTypeMessage;
    }
    return allowedTypes.has(type) ? "" : invalidTypeMessage;
  }

  window.setUploadInputFiles = (input, files) => {
    const transfer = new DataTransfer();
    files.forEach((file) => transfer.items.add(file));
    input.files = transfer.files;
    input.dispatchEvent(new Event("change", { bubbles: true }));
  };

  window.initializeFileUploadZone = ({
    input,
    zone,
    error,
    multiple = false,
    maxFiles = 1,
    maxFileSize = 5 * 1024 * 1024,
    allowedTypes = allowedDocumentTypes,
    invalidTypeMessage = "Choose an allowed file type.",
    onFilesChanged = () => {},
  }) => {
    const renderState = (selectedFiles) => {
      const files = [...selectedFiles];
      let message = "";
      if (files.length > maxFiles) {
        message = `Choose no more than ${maxFiles} ${multiple ? "files" : "file"}.`;
      } else {
        for (const file of files) {
          message = fileType(file, allowedTypes, invalidTypeMessage);
          if (message) break;
          if (file.size > maxFileSize) {
            message = `Each file must be ${Math.round(maxFileSize / (1024 * 1024))} MB or smaller.`;
            break;
          }
        }
      }

      if (message) {
        input.value = "";
        files.length = 0;
      }
      error.textContent = message;
      error.hidden = !message;
      zone.classList.toggle("has-file-error", Boolean(message));
      onFilesChanged(files, message);
    };

    input.addEventListener("change", () => renderState(input.files));
    zone.addEventListener("keydown", (event) => {
      if (event.key === "Enter" || event.key === " ") {
        event.preventDefault();
        input.click();
      }
    });
    zone.addEventListener("dragover", (event) => {
      event.preventDefault();
      zone.classList.add("is-dragging");
    });
    zone.addEventListener("dragleave", (event) => {
      if (!zone.contains(event.relatedTarget)) zone.classList.remove("is-dragging");
    });
    zone.addEventListener("drop", (event) => {
      event.preventDefault();
      zone.classList.remove("is-dragging");
      const droppedFiles = [...(event.dataTransfer?.files || [])];
      if (!droppedFiles.length) return;
      const files = multiple ? [...input.files, ...droppedFiles] : droppedFiles.slice(0, 1);
      window.setUploadInputFiles(input, files);
    });

    return {
      clear() {
        window.setUploadInputFiles(input, []);
      },
      setFiles(files) {
        window.setUploadInputFiles(input, files);
      },
    };
  };

  window.addEventListener("dragover", (event) => event.preventDefault());
  window.addEventListener("drop", (event) => event.preventDefault());
  window.facilityUploadTypes = {
    documents: allowedDocumentTypes,
    pdfDocuments: allowedDocumentTypes,
    images: allowedImageTypes,
  };
})();

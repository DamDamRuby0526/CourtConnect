document.addEventListener("DOMContentLoaded", () => {
  const filters = document.getElementById("facilityFilters");
  const search = filters?.elements.search;
  let searchTimer;

  search?.addEventListener("input", () => {
    window.clearTimeout(searchTimer);
    searchTimer = window.setTimeout(() => filters.requestSubmit(), 300);
  });

  document.querySelectorAll(".facility-card-image").forEach((image) => {
    const fallback = image.parentElement.querySelector(".facility-image-fallback");
    const showFallback = () => {
      image.hidden = true;
    };
    image.addEventListener("load", () => {
      image.classList.remove("is-loading");
      if (fallback) fallback.hidden = true;
    }, { once: true });
    image.addEventListener("error", showFallback, { once: true });
    if (image.complete) {
      if (image.naturalWidth === 0) showFallback();
      else {
        image.classList.remove("is-loading");
        if (fallback) fallback.hidden = true;
      }
    }
  });
});
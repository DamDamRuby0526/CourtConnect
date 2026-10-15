document.addEventListener("DOMContentLoaded", () => {
  const slideshow = document.querySelector("[data-court-slideshow]");
  const slides = slideshow ? Array.from(slideshow.querySelectorAll(".home-hero-slide")) : [];
  const prefersReducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)");

  if (slideshow && slides.length > 1) {
    let activeIndex = slides.findIndex((slide) => slide.classList.contains("is-active"));
    let slideshowTimer;

    const showSlide = (index) => {
      activeIndex = (index + slides.length) % slides.length;
      slides.forEach((slide, slideIndex) => {
        const isActive = slideIndex === activeIndex;
        slide.classList.toggle("is-active", isActive);
        slide.setAttribute("aria-hidden", String(!isActive));
      });
    };

    const stopSlideshow = () => {
      window.clearInterval(slideshowTimer);
    };

    const startSlideshow = () => {
      stopSlideshow();
      if (!prefersReducedMotion.matches) {
        slideshowTimer = window.setInterval(() => showSlide(activeIndex + 1), 5000);
      }
    };

    slideshow.querySelector("[data-slide-previous]")?.addEventListener("click", () => {
      showSlide(activeIndex - 1);
      startSlideshow();
    });
    slideshow.querySelector("[data-slide-next]")?.addEventListener("click", () => {
      showSlide(activeIndex + 1);
      startSlideshow();
    });
    slideshow.addEventListener("mouseenter", stopSlideshow);
    slideshow.addEventListener("mouseleave", startSlideshow);
    slideshow.addEventListener("focusin", stopSlideshow);
    slideshow.addEventListener("focusout", (event) => {
      if (!slideshow.contains(event.relatedTarget)) startSlideshow();
    });
    prefersReducedMotion.addEventListener("change", startSlideshow);
    startSlideshow();
  }

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
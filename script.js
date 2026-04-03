const loadingScreen = document.getElementById("loadingScreen");
const menuToggle = document.getElementById("menuToggle");
const navMenu = document.getElementById("navMenu");

window.addEventListener("load", () => {
  if (loadingScreen) {
    setTimeout(() => {
      loadingScreen.classList.add("hidden");
    }, 560);
  }
});

if (menuToggle && navMenu) {
  menuToggle.addEventListener("click", () => {
    const expanded = menuToggle.getAttribute("aria-expanded") === "true";
    menuToggle.setAttribute("aria-expanded", String(!expanded));
    navMenu.classList.toggle("open");
  });
}

if ("IntersectionObserver" in window) {
  const observer = new IntersectionObserver(
    (entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          entry.target.classList.add("visible");
          observer.unobserve(entry.target);
        }
      });
    },
    {
      threshold: 0.12,
    }
  );

  document.querySelectorAll(".reveal").forEach((node) => observer.observe(node));
} else {
  document.querySelectorAll(".reveal").forEach((node) => node.classList.add("visible"));
}

const applicationForm = document.getElementById("applicationForm");
if (applicationForm) {
  applicationForm.addEventListener("submit", (event) => {
    if (!applicationForm.checkValidity()) {
      event.preventDefault();
      applicationForm.reportValidity();
    }
  });
}

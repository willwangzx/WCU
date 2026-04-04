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
const applicationMessage = document.getElementById("applicationMessage");

if (applicationForm) {
  applicationForm.addEventListener("submit", (event) => {
    if (!applicationForm.checkValidity()) {
      event.preventDefault();
      if (applicationMessage) {
        applicationMessage.textContent = "";
        applicationMessage.classList.remove("form-note-success");
      }
      applicationForm.reportValidity();
      return;
    }

    event.preventDefault();

    if (applicationMessage) {
      applicationMessage.textContent = "Thank you. Your required confirmation has been completed, and your application is ready for review.";
      applicationMessage.classList.add("form-note-success");
    }
  });
}

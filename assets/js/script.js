const loadingScreen = document.getElementById("loadingScreen");
const menuToggle = document.getElementById("menuToggle");
const navMenu = document.getElementById("navMenu");
const basicForm = document.getElementById("basicInformationForm");
const writingForm = document.getElementById("writingMaterialsForm");
const applicationForm = document.getElementById("applicationForm");
const currentPage = window.location.pathname.split("/").pop();
const storageKeys = {
  basic: "wcuApplicationBasic",
  writing: "wcuApplicationWriting"
};

window.addEventListener("load", () => {
  if (loadingScreen) {
    setTimeout(() => {
      loadingScreen.classList.add("hidden");
    }, 560);
  }

  if (currentPage === "application-success.html") {
    sessionStorage.removeItem(storageKeys.basic);
    sessionStorage.removeItem(storageKeys.writing);
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

function saveFormData(form, storageKey) {
  const formData = new FormData(form);
  const entries = {};

  formData.forEach((value, key) => {
    entries[key] = value;
  });

  form.querySelectorAll("input[type='checkbox']").forEach((checkbox) => {
    entries[checkbox.name] = checkbox.checked;
  });

  sessionStorage.setItem(storageKey, JSON.stringify(entries));
}

function loadFormData(form, storageKey) {
  const savedValue = sessionStorage.getItem(storageKey);

  if (!savedValue) {
    return;
  }

  const savedData = JSON.parse(savedValue);

  Object.entries(savedData).forEach(([name, value]) => {
    const field = form.elements.namedItem(name);

    if (!field || field instanceof RadioNodeList) {
      return;
    }

    if (field.type === "checkbox") {
      field.checked = value === true;
      return;
    }

    field.value = value;
  });
}

function enableAutosave(form, storageKey) {
  form.addEventListener("input", () => saveFormData(form, storageKey));
  form.addEventListener("change", () => saveFormData(form, storageKey));
}

if (basicForm) {
  loadFormData(basicForm, storageKeys.basic);
  enableAutosave(basicForm, storageKeys.basic);

  basicForm.addEventListener("submit", (event) => {
    if (!basicForm.checkValidity()) {
      event.preventDefault();
      basicForm.reportValidity();
      return;
    }

    event.preventDefault();
    saveFormData(basicForm, storageKeys.basic);
    window.location.href = "apply-writing.html";
  });
}

if (writingForm) {
  if (!sessionStorage.getItem(storageKeys.basic)) {
    window.location.href = "apply-basic.html";
  } else {
    loadFormData(writingForm, storageKeys.writing);
    enableAutosave(writingForm, storageKeys.writing);

    writingForm.addEventListener("submit", (event) => {
      if (!writingForm.checkValidity()) {
        event.preventDefault();
        writingForm.reportValidity();
        return;
      }

      saveFormData(writingForm, storageKeys.writing);
    });
  }
}

if (applicationForm) {
  applicationForm.addEventListener("submit", (event) => {
    if (!applicationForm.checkValidity()) {
      event.preventDefault();
      applicationForm.reportValidity();
    }
  });
}

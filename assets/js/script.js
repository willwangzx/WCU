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

async function ensureSplitFlowCsrfToken(form) {
  const tokenField = form.querySelector("#splitFlowCsrfToken");
  if (!tokenField || tokenField.value) {
    return tokenField?.value || "";
  }

  const response = await fetch("apply.php", {
    credentials: "same-origin",
    headers: {
      "X-Requested-With": "fetch"
    }
  });

  if (!response.ok) {
    throw new Error("Unable to load application security token.");
  }

  const html = await response.text();
  const parser = new DOMParser();
  const doc = parser.parseFromString(html, "text/html");
  const remoteToken = doc.querySelector('input[name="csrf_token"]');

  if (!remoteToken || !remoteToken.value) {
    throw new Error("Application security token is missing.");
  }

  tokenField.value = remoteToken.value;
  return tokenField.value;
}

function normalizeProgramName(program) {
  const map = {
    "School of Mathematics and Computer Science": "School of Mathematics and Computer Science",
    "School of Engineering and Natural Science": "School of Engineering and Natural Science",
    "School of Business and Management": "School of Business and Management",
    "School of Art and Literature": "School of Art and Literature",
    "School of Humanities and Social Science": "School of Humanities and Social Science",
    "School of Interdisciplinary Studies": "School of Interdisciplinary Studies"
  };

  return map[program] || program;
}

function populateSplitApplicationPayload(form) {
  const basicValue = sessionStorage.getItem(storageKeys.basic);

  if (!basicValue) {
    throw new Error("Basic information is missing.");
  }

  const basicData = JSON.parse(basicValue);
  const fieldMap = {
    firstName: "splitFirstName",
    lastName: "splitLastName",
    email: "splitEmail",
    phone: "splitPhone",
    Nationality: "splitCitizenship",
    entryTerm: "splitEntryTerm",
    program: "splitProgram",
    schoolName: "splitSchoolName"
  };

  Object.entries(fieldMap).forEach(([sourceKey, targetId]) => {
    const target = form.querySelector(`#${targetId}`);
    if (!target) {
      return;
    }

    if (sourceKey === "program") {
      target.value = normalizeProgramName(basicData[sourceKey] || "");
      return;
    }

    target.value = basicData[sourceKey] || "";
  });
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
    void ensureSplitFlowCsrfToken(writingForm).catch(() => {});

    writingForm.addEventListener("submit", async (event) => {
      if (!writingForm.checkValidity()) {
        event.preventDefault();
        writingForm.reportValidity();
        return;
      }

      event.preventDefault();
      saveFormData(writingForm, storageKeys.writing);

      try {
        populateSplitApplicationPayload(writingForm);
        await ensureSplitFlowCsrfToken(writingForm);
        writingForm.submit();
      } catch (error) {
        console.error(error);
        window.alert("We could not prepare your application for submission. Please refresh the page and try again.");
      }
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

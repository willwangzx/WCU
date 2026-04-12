const loadingScreen = document.getElementById("loadingScreen");
const menuToggle = document.getElementById("menuToggle");
const navMenu = document.getElementById("navMenu");
const basicForm = document.getElementById("basicInformationForm");
const writingForm = document.getElementById("writingMaterialsForm");
const applicationForm = document.getElementById("applicationForm");
const currentPage = window.location.pathname.split("/").pop();
const siteConfig = window.WCU_CONFIG || {};
const storageKeys = {
  basic: "wcuApplicationBasic",
  writing: "wcuApplicationWriting"
};

window.addEventListener("load", () => {
  if (loadingScreen) {
    setTimeout(() => {
      loadingScreen.classList.add("hidden");
    }, 600);
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
      threshold: 0.12
    }
  );

  document.querySelectorAll(".reveal").forEach((node) => observer.observe(node));
} else {
  document.querySelectorAll(".reveal").forEach((node) => node.classList.add("visible"));
}

function getApiBaseUrl() {
  const configured = typeof siteConfig.apiBaseUrl === "string" ? siteConfig.apiBaseUrl.trim() : "";
  return configured.replace(/\/+$/, "");
}

function getApplicationEndpoint() {
  const apiBaseUrl = getApiBaseUrl();
  return apiBaseUrl ? `${apiBaseUrl}/api/application.php` : "/api/application.php";
}

function getApplicationMessageNode(form) {
  return form?.querySelector("#applicationMessage") || document.getElementById("applicationMessage");
}

function setApplicationMessage(form, message, type = "info") {
  const messageNode = getApplicationMessageNode(form);
  const normalizedMessage = String(message || "").trim();

  if (!messageNode) {
    if (normalizedMessage && type === "error") {
      window.alert(normalizedMessage);
    }
    return;
  }

  messageNode.textContent = normalizedMessage;
  messageNode.classList.toggle("is-visible", normalizedMessage !== "");
  messageNode.classList.toggle("error-note", type === "error");
  messageNode.classList.toggle("success-note", type === "success");
}

function loadStoredData(storageKey) {
  const rawValue = sessionStorage.getItem(storageKey);

  if (!rawValue) {
    return null;
  }

  try {
    const parsedValue = JSON.parse(rawValue);
    return parsedValue && typeof parsedValue === "object" ? parsedValue : null;
  } catch (error) {
    console.error(`Failed to parse saved form data for ${storageKey}:`, error);
    sessionStorage.removeItem(storageKey);
    return null;
  }
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
  const savedData = loadStoredData(storageKey);

  if (!savedData) {
    return;
  }

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

function buildSplitApplicationPayload(form) {
  const basicData = loadStoredData(storageKeys.basic);

  if (!basicData) {
    throw new Error("Please complete the basic information step again before submitting.");
  }

  const requiredBasicKeys = [
    "firstName",
    "lastName",
    "email",
    "phone",
    "birthMonth",
    "birthDay",
    "birthYear",
    "gender",
    "Nationality",
    "entryTerm",
    "program",
    "schoolName"
  ];

  const missingKey = requiredBasicKeys.find((key) => !String(basicData[key] || "").trim());
  if (missingKey) {
    throw new Error("Your saved basic information is incomplete. Please review Step 2 and try again.");
  }

  const formData = new FormData(form);

  return {
    first_name: String(basicData.firstName || "").trim(),
    last_name: String(basicData.lastName || "").trim(),
    email: String(basicData.email || "").trim(),
    phone: String(basicData.phone || "").trim(),
    birth_month: String(basicData.birthMonth || "").trim(),
    birth_day: String(basicData.birthDay || "").trim(),
    birth_year: String(basicData.birthYear || "").trim(),
    gender: String(basicData.gender || "").trim(),
    citizenship: String(basicData.Nationality || "").trim(),
    entry_term: String(basicData.entryTerm || "").trim(),
    program: normalizeProgramName(String(basicData.program || "").trim()),
    school_name: String(basicData.schoolName || "").trim(),
    personal_statement: String(formData.get("statement") || "").trim(),
    portfolio_url: String(formData.get("portfolio") || "").trim(),
    additional_notes: String(formData.get("notes") || "").trim(),
    application_confirmation: form.querySelector("#confirmation")?.checked === true
  };
}

async function parseApiResponse(response) {
  const contentType = response.headers.get("content-type") || "";

  if (contentType.includes("application/json")) {
    return response.json();
  }

  const text = await response.text();
  return {
    ok: response.ok,
    message: text.trim()
  };
}

async function submitSplitApplication(form) {
  const payload = buildSplitApplicationPayload(form);
  const endpoint = getApplicationEndpoint();
  const response = await fetch(endpoint, {
    method: "POST",
    headers: {
      Accept: "application/json",
      "Content-Type": "application/json"
    },
    body: JSON.stringify(payload)
  });

  const result = await parseApiResponse(response);
  if (!response.ok || !result.ok) {
    const errors = Array.isArray(result.errors) ? result.errors : [];
    throw new Error(errors[0] || result.message || "Application submission failed.");
  }

  return result;
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
  if (!loadStoredData(storageKeys.basic)) {
    window.alert("Please complete the basic information step before continuing.");
    window.location.href = "apply-basic.html";
  } else {
    loadFormData(writingForm, storageKeys.writing);
    enableAutosave(writingForm, storageKeys.writing);
    setApplicationMessage(writingForm, "Your application will be submitted securely on this site.", "info");

    writingForm.addEventListener("submit", async (event) => {
      const submitButton = writingForm.querySelector("button[type='submit']");
      const originalLabel = submitButton?.textContent || "Submit Application";

      if (!writingForm.checkValidity()) {
        event.preventDefault();
        writingForm.reportValidity();
        return;
      }

      event.preventDefault();
      saveFormData(writingForm, storageKeys.writing);
      setApplicationMessage(writingForm, "Submitting your application...", "info");

      if (submitButton) {
        submitButton.disabled = true;
        submitButton.textContent = "Submitting...";
      }

      try {
        await submitSplitApplication(writingForm);
        sessionStorage.removeItem(storageKeys.basic);
        sessionStorage.removeItem(storageKeys.writing);
        setApplicationMessage(writingForm, "Application submitted successfully. Redirecting...", "success");
        window.location.href = "application-success.html";
      } catch (error) {
        console.error(error);
        setApplicationMessage(
          writingForm,
          error instanceof Error ? error.message : "We could not submit your application right now.",
          "error"
        );
      } finally {
        if (submitButton) {
          submitButton.disabled = false;
          submitButton.textContent = originalLabel;
        }
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

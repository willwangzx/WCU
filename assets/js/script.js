const loadingScreen = document.getElementById("loadingScreen");
const menuToggle = document.getElementById("menuToggle");
const navMenu = document.getElementById("navMenu");
const basicForm = document.getElementById("basicInformationForm");
const writingForm = document.getElementById("writingMaterialsForm");
const applicationForm = document.getElementById("applicationForm");
const currentPage = window.location.pathname.split("/").pop();
const currentScriptSource = document.currentScript?.getAttribute("src") || "";
const siteConfig = window.WCU_CONFIG || {};
const recaptchaWidgets = new WeakMap();
let recaptchaLoadPromise = null;
const storageKeys = {
  basic: "wcuApplicationBasic",
  writing: "wcuApplicationWriting"
};

function getDefaultFaviconPath() {
  const normalizedSource = currentScriptSource.trim();
  if (normalizedSource) {
    return normalizedSource.replace(/js\/script\.js(?:\?.*)?$/, "favicon.ico");
  }

  return "assets/favicon.ico";
}

function ensureSiteFavicon() {
  const existingIcon = document.querySelector("link[rel='icon'], link[rel='shortcut icon']");
  if (existingIcon) {
    return;
  }

  const faviconLink = document.createElement("link");
  faviconLink.rel = "icon";
  faviconLink.href = getDefaultFaviconPath();
  document.head.appendChild(faviconLink);
}

ensureSiteFavicon();

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

function normalizeApiBaseUrl(value) {
  return typeof value === "string" ? value.trim().replace(/\/+$/, "") : "";
}

function getApiBaseUrl(form) {
  const formConfigured = normalizeApiBaseUrl(form?.dataset?.apiBase);
  if (formConfigured) {
    return formConfigured;
  }

  return normalizeApiBaseUrl(siteConfig.apiBaseUrl);
}

function getApplicationEndpoint(form) {
  const apiBaseUrl = getApiBaseUrl(form);
  if (apiBaseUrl) {
    return `${apiBaseUrl}/api/application.php`;
  }

  const formAction = typeof form?.action === "string" ? form.action.trim() : "";
  return formAction || "/api/application.php";
}

function getRecaptchaSiteKey(form) {
  const formConfigured = typeof form?.dataset?.recaptchaSiteKey === "string" ? form.dataset.recaptchaSiteKey.trim() : "";
  if (formConfigured) {
    return formConfigured;
  }

  return typeof siteConfig.recaptchaSiteKey === "string" ? siteConfig.recaptchaSiteKey.trim() : "";
}

function getRecaptchaContainer(form) {
  return form?.querySelector("#applicationRecaptcha");
}

function loadRecaptchaApi() {
  if (window.grecaptcha?.render) {
    return Promise.resolve(window.grecaptcha);
  }

  if (recaptchaLoadPromise) {
    return recaptchaLoadPromise;
  }

  recaptchaLoadPromise = new Promise((resolve, reject) => {
    const handleLoad = () => {
      if (window.grecaptcha?.render) {
        resolve(window.grecaptcha);
        return;
      }

      reject(new Error("reCAPTCHA did not load correctly."));
    };
    const handleError = () => reject(new Error("Unable to load reCAPTCHA."));
    const existingScript = document.querySelector("script[data-wcu-recaptcha='true']");

    if (existingScript) {
      existingScript.addEventListener("load", handleLoad, { once: true });
      existingScript.addEventListener("error", handleError, { once: true });
      return;
    }

    const script = document.createElement("script");
    script.src = "https://www.google.com/recaptcha/api.js?render=explicit";
    script.async = true;
    script.defer = true;
    script.dataset.wcuRecaptcha = "true";
    script.addEventListener("load", handleLoad, { once: true });
    script.addEventListener("error", handleError, { once: true });
    document.head.appendChild(script);
  });

  return recaptchaLoadPromise;
}

async function initializeApplicationRecaptcha(form) {
  const siteKey = getRecaptchaSiteKey(form);
  const container = getRecaptchaContainer(form);

  if (!siteKey || !container) {
    return;
  }

  container.classList.add("is-enabled");
  const grecaptcha = await loadRecaptchaApi();

  if (!recaptchaWidgets.has(form)) {
    recaptchaWidgets.set(form, grecaptcha.render(container, { sitekey: siteKey }));
  }
}

function getApplicationRecaptchaToken(form) {
  if (!getRecaptchaSiteKey(form)) {
    return "";
  }

  const widgetId = recaptchaWidgets.get(form);
  if (window.grecaptcha?.getResponse && widgetId !== undefined) {
    return String(window.grecaptcha.getResponse(widgetId) || "").trim();
  }

  return String(form?.querySelector("textarea[name='g-recaptcha-response']")?.value || "").trim();
}

function resetApplicationRecaptcha(form) {
  const widgetId = recaptchaWidgets.get(form);

  if (window.grecaptcha?.reset && widgetId !== undefined) {
    window.grecaptcha.reset(widgetId);
  }
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

function syncSplitHiddenFields(form, basicData) {
  if (!form || !basicData) {
    return;
  }

  const hiddenFieldMap = {
    splitFirstName: "firstName",
    splitLastName: "lastName",
    splitEmail: "email",
    splitPhone: "phone",
    splitBirthMonth: "birthMonth",
    splitBirthDay: "birthDay",
    splitBirthYear: "birthYear",
    splitGender: "gender",
    splitCitizenship: "Nationality",
    splitEntryTerm: "entryTerm",
    splitProgram: "program",
    splitSchoolName: "schoolName"
  };

  Object.entries(hiddenFieldMap).forEach(([fieldId, storageKeyName]) => {
    const field = form.querySelector(`#${fieldId}`);
    if (field) {
      field.value = String(basicData[storageKeyName] || "").trim();
    }
  });
}

function buildSplitApplicationPayload(form) {
  const basicData = loadStoredData(storageKeys.basic);
  const recaptchaToken = getApplicationRecaptchaToken(form);

  if (!basicData) {
    throw new Error("Please complete the basic information step again before submitting.");
  }
  if (getRecaptchaSiteKey(form) && !recaptchaToken) {
    throw new Error("Please complete the reCAPTCHA challenge.");
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

  syncSplitHiddenFields(form, basicData);
  const formData = new FormData(form);

  return {
    website: "",
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
    program: String(basicData.program || "").trim(),
    school_name: String(basicData.schoolName || "").trim(),
    personal_statement: String(formData.get("statement") || "").trim(),
    portfolio_url: String(formData.get("portfolio") || "").trim(),
    additional_notes: String(formData.get("notes") || "").trim(),
    application_confirmation: form.querySelector("#confirmation")?.checked === true,
    recaptcha_token: recaptchaToken
  };
}

async function parseApiResponse(response) {
  const contentType = response.headers.get("content-type") || "";

  if (contentType.includes("application/json")) {
    try {
      return await response.json();
    } catch (error) {
      console.error("Failed to parse JSON response from application endpoint:", error);
      return {
        ok: response.ok,
        message: "The admissions service returned an unreadable response."
      };
    }
  }

  const text = await response.text();
  return {
    ok: response.ok,
    message: text.trim()
  };
}

async function submitSplitApplication(form) {
  const payload = buildSplitApplicationPayload(form);
  const endpoint = getApplicationEndpoint(form);
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
  const basicData = loadStoredData(storageKeys.basic);

  if (!basicData) {
    window.alert("Please complete the basic information step before continuing.");
    window.location.href = "apply-basic.html";
  } else {
    loadFormData(writingForm, storageKeys.writing);
    syncSplitHiddenFields(writingForm, basicData);
    enableAutosave(writingForm, storageKeys.writing);
    initializeApplicationRecaptcha(writingForm).catch((error) => {
      console.error(error);
      setApplicationMessage(writingForm, "We could not load reCAPTCHA. Please refresh and try again.", "error");
    });
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
      syncSplitHiddenFields(writingForm, loadStoredData(storageKeys.basic));
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
        resetApplicationRecaptcha(writingForm);
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

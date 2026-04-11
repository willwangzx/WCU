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

  const response = await fetch("apply.php?csrf=1", {
    credentials: "same-origin",
    headers: {
      Accept: "application/json"
    }
  });

  if (!response.ok) {
    throw new Error("Unable to load application security token.");
  }

  const payload = await response.json();

  if (!payload.csrf_token) {
    throw new Error("Application security token is missing.");
  }
  console.log(payload.csrf_token);

  tokenField.value = payload.csrf_token;
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
  const requiredBasicKeys = [
    "firstName",
    "lastName",
    "email",
    "phone",
    "Nationality",
    "entryTerm",
    "program",
    "schoolName"
  ];

  const missingKey = requiredBasicKeys.find((key) => !String(basicData[key] || "").trim());
  if (missingKey) {
    throw new Error(`Missing required basic information: ${missingKey}`);
  }

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
  // 检查是否已经填写过基本信息
  if (!sessionStorage.getItem(storageKeys.basic)) {
    window.location.href = "apply-basic.html";
  } else {
    // 加载已保存的写作部分数据
    loadFormData(writingForm, storageKeys.writing);
    enableAutosave(writingForm, storageKeys.writing);
    
    // 预先填充隐藏字段（从 sessionStorage 读取基本信息）
    try {
      populateSplitApplicationPayload(writingForm);
    } catch (error) {
      console.error("Failed to populate basic data:", error);
      alert("无法加载基本信息，请返回上一步重新填写。");
      window.location.href = "apply-basic.html";
    }

    // 获取 CSRF token（需要后端支持）
    ensureSplitFlowCsrfToken(writingForm).catch((err) => {
      console.error("CSRF token error:", err);
      alert("无法获取安全令牌，请刷新页面重试。");
    });

    // 监听表单提交，使用 AJAX
    writingForm.addEventListener("submit", async (event) => {
      event.preventDefault();  // 完全阻止原生提交

      // 前端验证
      if (!writingForm.checkValidity()) {
        writingForm.reportValidity();
        return;
      }

      // 保存当前表单数据到 sessionStorage（自动保存功能）
      saveFormData(writingForm, storageKeys.writing);

      // 确保 CSRF token 已经填充（如果没有，再次尝试获取）
      let csrfToken = writingForm.querySelector("#splitFlowCsrfToken")?.value;
      console.log(csrfToken);
      if (!csrfToken) {
        try {
          csrfToken = await ensureSplitFlowCsrfToken(writingForm);
        } catch (err) {
          alert("无法获取安全令牌，请刷新页面后重试。");
          return;
        }
      }

      // 收集表单数据（包括所有 hidden 字段）
      const formData = new FormData(writingForm);

      // 禁用提交按钮，防止重复提交
      const submitBtn = writingForm.querySelector('button[type="submit"]');
      const originalText = submitBtn?.textContent || "Submit Application";
      if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.textContent = "Submitting...";
      }

      try {
        // 发送 AJAX POST 请求
        const response = await fetch(writingForm.action, {
          method: "POST",
          body: formData,
          credentials: "same-origin"
        });

        const result = await response.json();
        console.log(result);

        if (result.success) {
          // 成功后清除 sessionStorage 并跳转到成功页面（或显示成功消息后跳转）
          sessionStorage.removeItem(storageKeys.basic);
          sessionStorage.removeItem(storageKeys.writing);
          alert(result.message || "Application submitted successfully!");
          window.location.href = "application-success.html";  // 或直接跳转到首页
        } else {
          // 显示后端返回的错误信息
          const errors = result.errors ? result.errors.join("\n") : "Submission failed. Please try again.";
          alert("Error:\n" + errors);
          if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
          }
        }
      } catch (error) {
        console.error("AJAX error:", error);
        alert("Network error. Please check your connection and try again.");
        if (submitBtn) {
          submitBtn.disabled = false;
          submitBtn.textContent = originalText;
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


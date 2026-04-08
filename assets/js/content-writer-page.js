(function () {
  const form = document.getElementById("contentWriterForm");
  const preview = document.getElementById("writerPreview");
  const status = document.getElementById("writerStatus");
  const saveDraftBtn = document.getElementById("saveDraftBtn");
  const clearDraftBtn = document.getElementById("clearDraftBtn");
  const copyJsonBtn = document.getElementById("copyJsonBtn");
  const publishBtn = document.getElementById("publishBtn");
  const draftKey = "wcuNewsWriterDraft";

  if (!form || !preview || !status) {
    return;
  }

  function escapeHtml(value) {
    return String(value)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#39;");
  }

  function formatInlineMarkdown(text) {
    let output = escapeHtml(text);

    output = output.replace(/`([^`]+)`/g, "<code>$1</code>");
    output = output.replace(/\*\*([^*]+)\*\*/g, "<strong>$1</strong>");
    output = output.replace(/\*([^*]+)\*/g, "<em>$1</em>");
    output = output.replace(/\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/g, '<a href="$2" target="_blank" rel="noreferrer">$1</a>');

    return output;
  }

  function markdownToHtml(markdown) {
    const lines = String(markdown || "").replace(/\r\n/g, "\n").split("\n");
    const blocks = [];
    let paragraph = [];
    let listItems = [];
    let inCodeBlock = false;
    let codeLines = [];

    function flushParagraph() {
      if (paragraph.length === 0) {
        return;
      }

      blocks.push(`<p>${formatInlineMarkdown(paragraph.join(" "))}</p>`);
      paragraph = [];
    }

    function flushList() {
      if (listItems.length === 0) {
        return;
      }

      blocks.push(`<ul>${listItems.map((item) => `<li>${formatInlineMarkdown(item)}</li>`).join("")}</ul>`);
      listItems = [];
    }

    function flushCodeBlock() {
      if (codeLines.length === 0) {
        return;
      }

      blocks.push(`<pre><code>${escapeHtml(codeLines.join("\n"))}</code></pre>`);
      codeLines = [];
    }

    lines.forEach((rawLine) => {
      const line = rawLine.trimEnd();
      const trimmed = line.trim();

      if (trimmed.startsWith("```")) {
        flushParagraph();
        flushList();

        if (inCodeBlock) {
          flushCodeBlock();
        }

        inCodeBlock = !inCodeBlock;
        return;
      }

      if (inCodeBlock) {
        codeLines.push(rawLine);
        return;
      }

      if (trimmed === "") {
        flushParagraph();
        flushList();
        return;
      }

      const headingMatch = trimmed.match(/^(#{1,3})\s+(.*)$/);
      if (headingMatch) {
        flushParagraph();
        flushList();
        const level = headingMatch[1].length;
        blocks.push(`<h${level + 1}>${formatInlineMarkdown(headingMatch[2])}</h${level + 1}>`);
        return;
      }

      if (/^(-|\*)\s+/.test(trimmed)) {
        flushParagraph();
        listItems.push(trimmed.replace(/^(-|\*)\s+/, ""));
        return;
      }

      if (/^>\s+/.test(trimmed)) {
        flushParagraph();
        flushList();
        blocks.push(`<blockquote>${formatInlineMarkdown(trimmed.replace(/^>\s+/, ""))}</blockquote>`);
        return;
      }

      if (/^---+$/.test(trimmed)) {
        flushParagraph();
        flushList();
        blocks.push("<hr />");
        return;
      }

      paragraph.push(trimmed);
    });

    flushParagraph();
    flushList();
    flushCodeBlock();

    return blocks.join("");
  }

  function stripMarkdownForContent(markdown) {
    return String(markdown || "")
      .replace(/\r\n/g, "\n")
      .split(/\n\s*\n/)
      .map((block) => block
        .replace(/^#{1,6}\s+/gm, "")
        .replace(/^>\s+/gm, "")
        .replace(/^(-|\*)\s+/gm, "")
        .replace(/`([^`]+)`/g, "$1")
        .replace(/\*\*([^*]+)\*\*/g, "$1")
        .replace(/\*([^*]+)\*/g, "$1")
        .replace(/\[([^\]]+)\]\(([^)]+)\)/g, "$1")
        .replace(/```[\s\S]*?```/g, "")
        .trim())
      .filter(Boolean);
  }

  function buildPayload() {
    const formData = new FormData(form);
    const markdown = String(formData.get("content") || "").trim();

    return {
      id: String(formData.get("id") || "").trim(),
      title: String(formData.get("title") || "").trim(),
      summary: String(formData.get("summary") || "").trim(),
      date: String(formData.get("date") || "").trim(),
      category: String(formData.get("category") || "").trim(),
      tag: String(formData.get("tag") || "").trim(),
      href: "",
      markdown,
      content: stripMarkdownForContent(markdown)
    };
  }

  function formatDate(dateValue) {
    if (!dateValue) {
      return "Date not set";
    }

    return new Date(dateValue).toLocaleDateString("en-US", {
      year: "numeric",
      month: "long",
      day: "2-digit"
    });
  }

  function renderPreview() {
    const payload = buildPayload();
    const title = payload.title || "Article title preview";
    const summary = payload.summary || "Summary preview will appear here.";
    const markdown = payload.markdown || "# Start writing\n\nMarkdown preview will appear here as you type.";

    preview.innerHTML = `
      <div class="writer-pane-header writer-preview-header">
        <div>
          <p class="writer-pane-label">Live Preview</p>
          <p class="writer-pane-copy">Rendered from markdown in real time.</p>
        </div>
      </div>
      <div class="news-detail-head">
        <div class="news-meta-row">
          <span>${formatDate(payload.date)}</span>
          <span>${escapeHtml(payload.category || "Category")}</span>
          <span>${escapeHtml(payload.tag || "Tag")}</span>
        </div>
        <h2>${escapeHtml(title)}</h2>
        <p class="news-detail-summary">${escapeHtml(summary)}</p>
      </div>
      <div class="news-detail-content writer-markdown-preview">
        ${markdownToHtml(markdown)}
      </div>
    `;
  }

  function saveDraft() {
    const payload = buildPayload();
    sessionStorage.setItem(draftKey, JSON.stringify(payload));
    status.textContent = "Draft saved locally in this browser session.";
  }

  function loadDraft() {
    const raw = sessionStorage.getItem(draftKey);
    if (!raw) {
      return;
    }

    try {
      const data = JSON.parse(raw);
      form.elements.namedItem("id").value = data.id || "";
      form.elements.namedItem("title").value = data.title || "";
      form.elements.namedItem("summary").value = data.summary || "";
      form.elements.namedItem("date").value = data.date || "";
      form.elements.namedItem("category").value = data.category || "";
      form.elements.namedItem("tag").value = data.tag || "";
      form.elements.namedItem("content").value = data.markdown || (Array.isArray(data.content) ? data.content.join("\n\n") : "");
      status.textContent = "Draft loaded from this browser session.";
    } catch (error) {
      status.textContent = "Could not load draft data.";
    }
  }

  async function copyJsonEntry() {
    if (!form.checkValidity()) {
      form.reportValidity();
      return;
    }

    const payload = buildPayload();
    const text = JSON.stringify(payload, null, 2);

    try {
      await navigator.clipboard.writeText(text);
      status.textContent = "JSON entry copied with markdown support for the news database.";
    } catch (error) {
      status.textContent = "Clipboard failed. Copy manually from browser dev tools if needed.";
      console.error(error);
    }
  }

  async function publishToServer(_payload) {
    // TODO: Add your backend endpoint and request code here after server setup.
    // Example (not active):
    // return fetch("https://your-domain.com/api/news", { method: "POST", ... });
    throw new Error("Server is not connected yet.");
  }

  async function tryPublish() {
    if (!form.checkValidity()) {
      form.reportValidity();
      return;
    }

    const payload = buildPayload();

    try {
      await publishToServer(payload);
      status.textContent = "Published successfully.";
    } catch (error) {
      status.textContent = "Publish is intentionally blank until server rental/setup is complete.";
    }
  }

  form.addEventListener("input", () => {
    saveDraft();
    renderPreview();
  });

  saveDraftBtn?.addEventListener("click", () => {
    saveDraft();
    renderPreview();
  });

  clearDraftBtn?.addEventListener("click", () => {
    sessionStorage.removeItem(draftKey);
    form.reset();
    status.textContent = "Draft cleared.";
    renderPreview();
  });

  copyJsonBtn?.addEventListener("click", () => {
    void copyJsonEntry();
  });

  publishBtn?.addEventListener("click", () => {
    void tryPublish();
  });

  loadDraft();
  renderPreview();
})();

(function () {
  const root = document.getElementById("newsDetailRoot");
  if (!root) {
    return;
  }

  const source = Array.isArray(window.WCU_NEWS_DATABASE) ? window.WCU_NEWS_DATABASE : [];
  const params = new URLSearchParams(window.location.search);
  const storyId = params.get("id");
  const item = source.find((entry) => entry.id === storyId);

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

      blocks.push(`<ul>${listItems.map((entry) => `<li>${formatInlineMarkdown(entry)}</li>`).join("")}</ul>`);
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

  function formatDate(dateValue) {
    return new Date(dateValue).toLocaleDateString("en-US", {
      year: "numeric",
      month: "long",
      day: "2-digit"
    });
  }

  if (!item) {
    root.innerHTML = `
      <p class="news-empty">Story not found.</p>
      <a class="text-link" href="news.html">Back to all news</a>
    `;
    return;
  }

  const category = item.category || "General";
  const markdownBody = String(item.markdown || "").trim();
  const fallbackParagraphs = Array.isArray(item.content) && item.content.length > 0
    ? item.content.map((paragraph) => `<p>${escapeHtml(paragraph)}</p>`).join("")
    : `<p>${escapeHtml(item.summary || "No additional story details are available right now.")}</p>`;
  const renderedBody = markdownBody ? markdownToHtml(markdownBody) : fallbackParagraphs;

  document.title = `${item.title} | News | William Chichi University`;

  root.innerHTML = `
    <div class="news-detail-head">
      <a class="text-link" href="news.html">Back to all news</a>
      <div class="news-meta-row">
        <span>${formatDate(item.date)}</span>
        <span>${escapeHtml(category)}</span>
        <span>${escapeHtml(item.tag || "Update")}</span>
      </div>
      <h2>${escapeHtml(item.title)}</h2>
      <p class="news-detail-summary">${escapeHtml(item.summary || "")}</p>
    </div>
    <div class="news-detail-content writer-markdown-preview">
      ${renderedBody}
    </div>
  `;
})();

(function () {
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
    output = output.replace(
      /\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/g,
      '<a href="$2" target="_blank" rel="noreferrer">$1</a>'
    );

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

  function markdownToParagraphs(markdown) {
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

  window.WCU_CONTENT_FORMAT = {
    escapeHtml,
    markdownToHtml,
    markdownToParagraphs
  };
})();

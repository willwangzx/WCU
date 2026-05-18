(function () {
  const root = document.getElementById("newsDetailRoot");
  const contentFormat = window.WCU_CONTENT_FORMAT;

  if (!root || !contentFormat) {
    return;
  }

  const { escapeHtml, markdownToHtml } = contentFormat;
  const source = Array.isArray(window.WCU_NEWS_DATABASE) ? window.WCU_NEWS_DATABASE : [];
  const params = new URLSearchParams(window.location.search);
  const storyId = params.get("id");
  const item = source.find((entry) => entry.id === storyId);

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

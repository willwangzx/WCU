(function () {
  const root = document.getElementById("newsRoot");
  const featuredRoot = document.getElementById("featuredNews");
  const filtersRoot = document.getElementById("newsFilters");
  const resultCount = document.getElementById("newsResultCount");

  if (!root || !featuredRoot || !filtersRoot || !resultCount) {
    return;
  }

  const source = Array.isArray(window.WCU_NEWS_DATABASE) ? window.WCU_NEWS_DATABASE : [];
  if (source.length === 0) {
    root.innerHTML = '<p class="news-empty">No news is available right now. Please check back soon.</p>';
    featuredRoot.innerHTML = "";
    resultCount.textContent = "0 stories";
    return;
  }

  const items = [...source]
    .filter((item) => item && item.title && item.date)
    .sort((a, b) => new Date(b.date) - new Date(a.date));

  const categories = ["All", ...new Set(items.map((item) => item.category || "General"))];
  let activeCategory = "All";

  function formatDate(dateValue) {
    return new Date(dateValue).toLocaleDateString("en-US", {
      year: "numeric",
      month: "short",
      day: "2-digit"
    });
  }

  function getDetailUrl(item) {
    if (item.href && item.href.trim() !== "") {
      return item.href;
    }

    return `news-detail.html?id=${encodeURIComponent(item.id)}`;
  }

  function renderFeatured(item) {
    const category = item.category || "General";
    featuredRoot.innerHTML = `
      <article class="news-featured-card reveal visible">
        <p class="eyebrow">Featured Story</p>
        <h2>${item.title}</h2>
        <p>${item.summary || "Read the latest update from William Chichi University."}</p>
        <div class="news-meta-row">
          <span>${formatDate(item.date)}</span>
          <span>${category}</span>
          <span>${item.tag || "Update"}</span>
        </div>
        <a class="btn btn-solid" href="${getDetailUrl(item)}">Read Story</a>
      </article>
    `;
  }

  function renderFilters() {
    filtersRoot.innerHTML = categories
      .map((category) => {
        const isActive = category === activeCategory;
        return `<button type="button" class="news-filter${isActive ? " active" : ""}" data-category="${category}">${category}</button>`;
      })
      .join("");
  }

  function renderList() {
    const filtered = items.filter((item) => activeCategory === "All" || (item.category || "General") === activeCategory);

    resultCount.textContent = `${filtered.length} ${filtered.length === 1 ? "story" : "stories"}`;

    if (filtered.length === 0) {
      root.innerHTML = '<p class="news-empty">No stories found in this category.</p>';
      return;
    }

    root.innerHTML = filtered
      .map((item) => {
        const category = item.category || "General";
        return `
          <article class="news-item reveal visible">
            <div class="news-item-head">
              <span class="news-item-date">${formatDate(item.date)}</span>
              <span class="news-item-category">${category}</span>
            </div>
            <h3>${item.title}</h3>
            <p>${item.summary || "Read the latest update from William Chichi University."}</p>
            <a class="text-link" href="${getDetailUrl(item)}">Read more</a>
          </article>
        `;
      })
      .join("");
  }

  filtersRoot.addEventListener("click", (event) => {
    const button = event.target.closest("button[data-category]");
    if (!button) {
      return;
    }

    activeCategory = button.dataset.category || "All";
    renderFilters();
    renderList();
  });

  renderFeatured(items[0]);
  renderFilters();
  renderList();
})();

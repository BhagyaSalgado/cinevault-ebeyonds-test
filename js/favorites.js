/* "Collect your favorites" — search TVMaze, add results to the grid, remove them again.
   API docs: https://www.tvmaze.com/api  (no key required, CORS-friendly). */
(function () {
  const form = document.getElementById('searchForm');
  const input = document.getElementById('movieSearch');
  const resultsWrap = document.getElementById('searchResults');
  const grid = document.getElementById('favoritesGrid');
  if (!form || !input || !resultsWrap || !grid) return;

  const FALLBACK_IMG = 'assets/img/fallback-poster.svg';
  const addedIds = new Set(); // tracks TVMaze show ids already added to the grid
  let debounceTimer = null;
  let activeController = null;

  // The three "default" favorites shown on page load — real TVMaze show ids
  // for well-known titles, fetched live just like a search result so the
  // poster art/description always comes straight from the API (no bundled
  // copyrighted images). Swap these ids for any other TVMaze show to change
  // the defaults: look the id up via https://api.tvmaze.com/singlesearch/shows?q=<title>
  const DEFAULT_SHOW_IDS = [169, 2993, 82]; // Breaking Bad, Stranger Things, Game of Thrones

  function escapeHtml(str) {
    return String(str).replace(/[&<>"']/g, (c) => ({
      '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    }[c]));
  }

  function stripHtml(html) {
    if (!html) return '';
    const tmp = document.createElement('div');
    tmp.innerHTML = html;
    return tmp.textContent || tmp.innerText || '';
  }

  async function searchShows(query) {
    if (activeController) activeController.abort();
    activeController = new AbortController();
    const url = `https://api.tvmaze.com/search/shows?q=${encodeURIComponent(query)}`;
    const res = await fetch(url, { signal: activeController.signal });
    if (!res.ok) throw new Error(`TVMaze responded ${res.status}`);
    return res.json();
  }

  function renderResults(matches) {
    if (!matches.length) {
      resultsWrap.innerHTML = '<p class="search-status">No matches. Try a different title.</p>';
      return;
    }
    resultsWrap.innerHTML = matches
      .slice(0, 8)
      .map(({ show }) => {
        const img = show.image ? show.image.medium : FALLBACK_IMG;
        const year = show.premiered ? show.premiered.slice(0, 4) : '—';
        const genres = (show.genres || []).slice(0, 2).join(' · ') || 'Unrated genre';
        const already = addedIds.has(show.id);
        return `
          <div class="search-result-item" data-id="${show.id}">
            <img src="${img}" alt="" loading="lazy" onerror="this.src='${FALLBACK_IMG}'">
            <div class="sr-meta">
              <h5>${escapeHtml(show.name)}</h5>
              <p class="sr-tags">${escapeHtml(genres)} · ${year}</p>
            </div>
            <button type="button" class="btn-add" data-action="add" data-id="${show.id}" ${already ? 'disabled' : ''}>
              ${already ? 'Added' : 'Add'}
            </button>
          </div>`;
      })
      .join('');
  }

  function buildCard(show) {
    const img = show.image ? (show.image.medium || show.image.original) : FALLBACK_IMG;
    const year = show.premiered ? show.premiered.slice(0, 4) : '—';
    const rating = show.rating && show.rating.average ? show.rating.average.toFixed(1) : null;
    const genres = (show.genres || []).slice(0, 3);
    const summary = stripHtml(show.summary).slice(0, 110);

    const card = document.createElement('article');
    card.className = 'fav-card';
    card.dataset.id = show.id;
    card.innerHTML = `
      <button type="button" class="remove-btn" aria-label="Remove ${escapeHtml(show.name)} from your collection">&times;</button>
      <img src="${img}" alt="Poster art for ${escapeHtml(show.name)}" loading="lazy" onerror="this.src='${FALLBACK_IMG}'">
      <div class="fav-card-body">
        <h4>${escapeHtml(show.name)}</h4>
        <p>${escapeHtml(summary)}${summary.length === 110 ? '…' : ''}</p>
        <div class="badge-row">
          ${rating ? `<span class="badge rating">★ ${rating}</span>` : ''}
          <span class="badge">${year}</span>
          ${genres.map((g) => `<span class="badge">${escapeHtml(g)}</span>`).join('')}
        </div>
      </div>`;

    card.querySelector('.remove-btn').addEventListener('click', () => {
      addedIds.delete(show.id);
      card.classList.add('is-leaving');
      card.addEventListener('transitionend', () => card.remove(), { once: true });
      // Re-enable the matching "Add" button if it's still in the results list.
      const staleBtn = resultsWrap.querySelector(`[data-action="add"][data-id="${show.id}"]`);
      if (staleBtn) { staleBtn.disabled = false; staleBtn.textContent = 'Add'; }
    });

    return card;
  }

  async function runSearch(query) {
    if (!query.trim()) {
      resultsWrap.innerHTML = '';
      return;
    }
    resultsWrap.innerHTML = '<p class="search-status">Searching…</p>';
    try {
      const matches = await searchShows(query.trim());
      renderResults(matches);
    } catch (err) {
      if (err.name === 'AbortError') return;
      resultsWrap.innerHTML = '<p class="search-status">Couldn’t reach TVMaze right now. Please try again.</p>';
    }
  }

  async function loadDefaultFavorites() {
    const status = document.createElement('p');
    status.className = 'search-status';
    status.id = 'defaultsStatus';
    status.textContent = 'Loading your collection…';
    grid.appendChild(status);

    const results = await Promise.allSettled(
      DEFAULT_SHOW_IDS.map((id) => fetch(`https://api.tvmaze.com/shows/${id}`).then((res) => {
        if (!res.ok) throw new Error(`TVMaze responded ${res.status}`);
        return res.json();
      }))
    );

    status.remove();
    results.forEach((result) => {
      if (result.status !== 'fulfilled') return; // skip any show that failed to load, rather than breaking the whole grid
      const show = result.value;
      if (addedIds.has(show.id)) return;
      addedIds.add(show.id);
      grid.appendChild(buildCard(show));
    });

    if (results.every((r) => r.status === 'rejected')) {
      const failed = document.createElement('p');
      failed.className = 'search-status';
      failed.textContent = 'Couldn’t load the default collection — check your connection and refresh.';
      grid.appendChild(failed);
    }
  }

  loadDefaultFavorites();

  form.addEventListener('submit', (e) => {
    e.preventDefault();
    clearTimeout(debounceTimer);
    runSearch(input.value);
  });

  // Live search as the user types (debounced) — nice-to-have on top of the required submit search.
  input.addEventListener('input', () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => runSearch(input.value), 400);
  });

  resultsWrap.addEventListener('click', async (e) => {
    const btn = e.target.closest('[data-action="add"]');
    if (!btn || btn.disabled) return;
    const id = btn.dataset.id;
    btn.disabled = true;
    btn.textContent = 'Adding…';
    try {
      const res = await fetch(`https://api.tvmaze.com/shows/${id}`);
      if (!res.ok) throw new Error('lookup failed');
      const show = await res.json();
      addedIds.add(show.id);
      grid.appendChild(buildCard(show));
      btn.textContent = 'Added';
    } catch (err) {
      btn.disabled = false;
      btn.textContent = 'Add';
    }
  });
})();
const skeletonRow = (count = 6) => {
  let cards = '';
  for (let i = 0; i < count; i++) {
    cards += '<div class="skeleton-card"></div>';
  }
  return `<div class="row-shell">${cards}</div>`;
};

const escapeHtml = (value = '') => String(value)
  .replace(/&/g, '&amp;')
  .replace(/</g, '&lt;')
  .replace(/>/g, '&gt;')
  .replace(/"/g, '&quot;')
  .replace(/'/g, '&#39;');

const slugify = (value = '') => String(value)
  .toLowerCase()
  .trim()
  .replace(/[^a-z0-9]+/g, '-')
  .replace(/^-+|-+$/g, '');

function renderCarousel(items) {
  const wrap = document.getElementById('featuredCarousel');
  if (!wrap) return;

  if (!Array.isArray(items) || !items.length) {
    wrap.innerHTML = '<div class="empty-row-card"><i class="fas fa-star"></i><div><strong>No featured titles</strong><p>Featured content will appear here when available.</p></div></div>';
    return;
  }

  const slidesMarkup = items.map((it, idx) => {
    const type = it.type === 'series' ? 'series' : 'movie';
    const id = Number.parseInt(it.id, 10);
    if (!Number.isFinite(id) || id <= 0) return '';

    const title = escapeHtml(it.title || 'Untitled');
    const genre = escapeHtml(it.genre || '');
    const banner = escapeHtml((it.banner_url || it.poster_url || '').trim());
    const detailsHref = `/${type}.php?id=${id}`;

    return `
      <div class="slide ${idx === 0 ? 'active' : ''}" data-idx="${idx}">
        <div class="slide-bg" style="background-image:url('${banner}')"></div>
        <div class="slide-overlay">
          <span class="badge">${type.toUpperCase()}</span>
          <h2>${title}</h2>
          <p class="muted">${genre}</p>
          <a class="button-pill" style="width:fit-content;" href="${detailsHref}">Details</a>
        </div>
      </div>`;
  }).join('');

  wrap.innerHTML = slidesMarkup;

  const slides = wrap.querySelectorAll('.slide');
  if (slides.length <= 1) return;

  let current = 0;
  const show = (i) => {
    slides.forEach((s, idx) => s.classList.toggle('active', idx === i));
  };

  show(0);
  setInterval(() => {
    current = (current + 1) % slides.length;
    show(current);
  }, 5000);
}

function renderContentCard(item) {
  const type = item.content_type === 'series' || item.type === 'series' ? 'series' : 'movie';
  const id = Number.parseInt(item.content_id || item.id, 10);
  if (!Number.isFinite(id) || id <= 0) return '';

  const title = escapeHtml(item.title || 'Untitled');
  const posterUrl = escapeHtml((item.poster_url || item.poster || '').trim());
  const href = `/${type}.php?id=${id}`;

  return `
    <a class="movie-card" href="${href}" aria-label="${title}">
      <div class="movie-poster ${posterUrl ? '' : 'no-poster'}">
        ${posterUrl
          ? `<img src="${posterUrl}" alt="${title}" loading="lazy">`
          : '<div class="poster-fallback"><i class="fas fa-film" aria-hidden="true"></i></div>'}
        <div class="movie-overlay"><span class="btn-play" aria-hidden="true">▶</span></div>
      </div>
      <div class="movie-meta" title="${title}">${title}</div>
    </a>`;
}

function renderEmptyRail(label, hint) {
  return `
    <div class="row-shell">
      <div class="empty-row-card">
        <i class="fas fa-circle-info" aria-hidden="true"></i>
        <div>
          <strong>${escapeHtml(label)}</strong>
          <p>${escapeHtml(hint)}</p>
        </div>
      </div>
    </div>`;
}

function renderRow(containerId, items, emptyLabel, emptyHint) {
  const root = document.getElementById(containerId);
  if (!root) return;

  if (!Array.isArray(items) || !items.length) {
    root.innerHTML = renderEmptyRail(emptyLabel, emptyHint);
    return;
  }

  root.innerHTML = `<div class="row-shell">${items.map(renderContentCard).join('')}</div>`;
}

function renderGenreRows(genres) {
  const genresWrap = document.getElementById('genreRows');
  const catList = document.getElementById('categoriesList');
  if (!genresWrap) return;

  if (!Array.isArray(genres) || !genres.length) {
    genresWrap.innerHTML = renderEmptyRail('No genres available', 'New genre rows will appear here when content is available.');
    if (catList) catList.innerHTML = '<li><span style="color: var(--muted);">No categories yet</span></li>';
    return;
  }

  genresWrap.innerHTML = genres.map((row) => {
    const genre = escapeHtml(row.genre || 'More to Watch');
    const genreSlug = slugify(row.genre || 'more-to-watch');
    const items = Array.isArray(row.items) ? row.items : [];

    return `
      <section class="section row-block" id="genre-${genreSlug}">
        <div class="section-title"><span>${genre}</span></div>
        ${items.length
          ? `<div class="row-shell">${items.map(renderContentCard).join('')}</div>`
          : renderEmptyRail(`${genre} is empty`, 'No titles are available in this row yet.')}
      </section>`;
  }).join('');

  if (catList) {
    catList.innerHTML = genres.map((row) => {
      const genre = escapeHtml(row.genre || 'More to Watch');
      const genreSlug = slugify(row.genre || 'more-to-watch');
      return `<li><a href="#genre-${genreSlug}">${genre}</a></li>`;
    }).join('');
  }
}

async function loadHome() {
  const cw = document.getElementById('continueRow');
  const ml = document.getElementById('myListRow');
  const genresWrap = document.getElementById('genreRows');

  [cw, ml, genresWrap].forEach((el) => {
    if (el) el.innerHTML = skeletonRow();
  });

  try {
    const res = await fetch('/api/home.php', {
      headers: { Accept: 'application/json' }
    });

    if (!res.ok) {
      throw new Error(`Home API failed with status ${res.status}`);
    }

    const data = await res.json();

    renderCarousel(data.featured || []);
    renderRow(
      'continueRow',
      (data.continue_watching || []).map((item) => ({ ...item, type: item.content_type })),
      'Nothing in continue watching',
      'Start a movie or series and it will appear here.'
    );
    renderRow('myListRow', data.my_list || [], 'Your list is empty', 'Add movies or series to build your list.');
    renderGenreRows(data.genres || []);
  } catch (error) {
    console.error(error);
    renderRow('continueRow', [], 'Unable to load continue watching', 'Try refreshing the page.');
    renderRow('myListRow', [], 'Unable to load your list', 'Try refreshing the page.');
    renderGenreRows([]);
  }
}

document.addEventListener('DOMContentLoaded', loadHome);

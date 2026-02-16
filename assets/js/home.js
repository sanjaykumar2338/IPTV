const skeletonRow = (count = 6) => {
  let cards = '';
  for (let i = 0; i < count; i++) {
    cards += `<div class="skeleton-card"></div>`;
  }
  return `<div class="row-shell">${cards}</div>`;
};

function renderCarousel(items) {
  const wrap = document.getElementById('featuredCarousel');
  if (!wrap || !items || !items.length) return;
  wrap.innerHTML = items.map((it, idx) => `
    <div class="slide ${idx === 0 ? 'active' : ''}" data-idx="${idx}">
      <div class="slide-bg" style="background-image:url('${it.banner_url || it.poster_url || ''}')"></div>
      <div class="slide-overlay">
        <span class="badge">${(it.type || '').toUpperCase()}</span>
        <h2>${it.title}</h2>
        <p class="muted">${it.genre || ''}</p>
        <a class="button-pill" style="width:fit-content;" href="/${it.type === 'movie' ? 'movie' : 'series'}.php?id=${it.id}">Details</a>
      </div>
    </div>`).join('');
  let current = 0;
  const slides = wrap.querySelectorAll('.slide');
  const show = (i) => {
    slides.forEach((s, idx) => s.classList.toggle('active', idx === i));
  };
  show(0);
  setInterval(() => {
    current = (current + 1) % slides.length;
    show(current);
  }, 5000);
}

function renderRow(containerId, title, items) {
  const root = document.getElementById(containerId);
  if (!root) return;
  if (!items || !items.length) { root.innerHTML = `<div class="row-header"><h3>${title}</h3></div><p class="muted">Nothing here yet — start watching to see items.</p>`; return; }
  root.innerHTML = `
    <div class="row-header"><h3>${title}</h3></div>
    <div class="row-shell">
      ${items.map(it => {
        const type = (it.content_type || it.type || 'movie');
        const href = `/${type === 'series' ? 'series' : 'movie'}.php?id=${it.content_id || it.id}`;
        const poster = it.poster_url || it.poster || '';
        return `
          <a class="movie-card" href="${href}">
            <div class="movie-poster" style="background-image:url('${poster}')">
              <div class="movie-overlay"><button class="btn-play">▶</button></div>
            </div>
            <div class="movie-meta">${it.title}</div>
          </a>`;
      }).join('')}
    </div>`;
}

async function loadHome() {
  const carousel = document.getElementById('featuredCarousel');
  const cw = document.getElementById('continueRow');
  const ml = document.getElementById('myListRow');
  const genresWrap = document.getElementById('genreRows');
  [cw, ml, genresWrap].forEach(el => el && (el.innerHTML = skeletonRow()));

  const res = await fetch('/api/home.php');
  const data = await res.json();

  if (data.featured) renderCarousel(data.featured);
  if (data.continue_watching) renderRow('continueRow', 'Continue Watching', data.continue_watching.map(i=>({...i, type:i.content_type})));
  if (data.my_list) renderRow('myListRow', 'My List', data.my_list);
  if (data.genres) {
    genresWrap.innerHTML = data.genres.map(row => `
      <div class="row-block">
        <div class="row-header"><h3>${row.genre}</h3></div>
        <div class="movie-grid">
          ${row.items.map(it => `
            <a class="movie-card" href="/${it.type === 'series' ? 'series' : 'movie'}.php?id=${it.id}">
              <div class="movie-poster" style="background-image:url('${it.poster_url || ''}')">
                <div class="movie-overlay"><button class="btn-play">▶</button></div>
              </div>
              <div class="movie-meta">${it.title}</div>
            </a>`).join('')}
        </div>
      </div>`).join('');
    // populate sidebar categories
    const catList = document.getElementById('categoriesList');
    if (catList) {
      catList.innerHTML = data.genres.map(g => `<li><a href="#genre-${g.genre.replace(/\\s+/g,'-').toLowerCase()}">${g.genre}</a></li>`).join('');
    }
  }
}

document.addEventListener('DOMContentLoaded', loadHome);

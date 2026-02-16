const fetchFavs = async () => {
  const res = await fetch('/api/channel_favorites.php');
  return (await res.json()).favorites || [];
};

const toggleFav = async (id, action) => {
  await fetch('/api/channel_favorites.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: `channel_id=${id}&action=${action}`
  });
};

const loadEPG = async () => {
  const res = await fetch('/api/epg.php');
  return await res.json();
};

function renderEPG(data, favIds, tab='all') {
  const grid = document.getElementById('epgGrid');
  if (!grid) return;
  grid.innerHTML = '';
  data.channels.forEach(ch => {
    if (tab === 'fav' && !favIds.includes(ch.id)) return;
    const row = document.createElement('div');
    row.className = 'epg-row';
    const favActive = favIds.includes(ch.id);
    row.innerHTML = `
      <div class="epg-channel">
        <span class="fav" data-id="${ch.id}"><i class="${favActive ? 'fas' : 'far'} fa-star"></i></span>
        <div>
          <div>${ch.name}</div>
          ${ch.category ? `<div style="color:var(--muted); font-size:12px;">${ch.category}</div>`:''}
        </div>
      </div>
      <div class="epg-timeline epg-scroll no-scrollbar"><div class="epg-track"></div></div>`;
    const track = row.querySelector('.epg-track');
    ch.programs.forEach(p => {
      const now = Date.now();
      const start = new Date(p.start).getTime();
      const end = new Date(p.end).getTime();
      const width = Math.max(120, (end-start)/1000/60 * 4); // 4px per minute
      const prog = document.createElement('div');
      prog.className = 'epg-program' + (now>=start && now<=end ? ' now' : '');
      prog.style.width = width + 'px';
      prog.innerHTML = `<div class="title">${p.title}</div><div class="time">${p.start_time} - ${p.end_time}</div><div class="epg-desc">${p.description||'No description'}</div>`;
      prog.onclick = () => { window.location = `/player.php?stream=${encodeURIComponent(ch.stream_url)}&name=${encodeURIComponent(ch.name)}`; };
      track.appendChild(prog);
    });
    grid.appendChild(row);
  });

  grid.querySelectorAll('.fav').forEach(el => {
    el.addEventListener('click', async (e) => {
      e.stopPropagation();
      const id = el.dataset.id;
      const isFav = favIds.includes(Number(id));
      await toggleFav(id, isFav ? 'remove' : 'add');
      const favs = await fetchFavs();
      renderEPG(data, favs, currentTab);
    });
  });
}

let currentTab = 'all';

document.addEventListener('DOMContentLoaded', async () => {
  const data = await loadEPG();
  let favs = await fetchFavs();
  renderEPG(data, favs, 'all');

  document.getElementById('tabAll')?.addEventListener('click', async ()=>{
    currentTab='all';
    favs = await fetchFavs();
    renderEPG(data, favs, 'all');
  });
  document.getElementById('tabFav')?.addEventListener('click', async ()=>{
    currentTab='fav';
    favs = await fetchFavs();
    renderEPG(data, favs, 'fav');
  });
});

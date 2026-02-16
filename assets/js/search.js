const debounce = (fn, wait=300) => {
  let t; return (...args)=>{ clearTimeout(t); t=setTimeout(()=>fn(...args), wait); };
};

document.addEventListener('DOMContentLoaded', () => {
  const input = document.getElementById('globalSearch');
  const box = document.getElementById('searchSuggestions');
  if (!input || !box) return;

  const render = (data) => {
    if (!data || (!data.movies?.length && !data.series?.length)) { box.style.display='none'; return; }
    let html = '';
    if (data.movies?.length) {
      html += '<div style="padding:6px 10px; color:#9ca3af; font-size:12px;">Movies</div>';
      data.movies.forEach(m => {
        html += `<a class="nav-link" style="display:flex; gap:8px; padding:8px 10px; align-items:center;" href="/movie.php?id=${m.id}">
                    <img src="${m.poster||''}" style="width:32px; height:48px; object-fit:cover; border-radius:6px;" alt=""> 
                    <span>${m.title} ${m.year?`(${m.year})`:''}</span>
                 </a>`;
      });
    }
    if (data.series?.length) {
      html += '<div style="padding:6px 10px; color:#9ca3af; font-size:12px;">Series</div>';
      data.series.forEach(s => {
        html += `<a class="nav-link" style="display:flex; gap:8px; padding:8px 10px; align-items:center;" href="/series.php?id=${s.id}">
                    <img src="${s.poster||''}" style="width:32px; height:48px; object-fit:cover; border-radius:6px;" alt=""> 
                    <span>${s.title} ${s.year?`(${s.year})`:''}</span>
                 </a>`;
      });
    }
    box.innerHTML = html;
    box.style.display = 'block';
  };

  const fetcher = debounce(async (q) => {
    if (!q || q.length < 2) { box.style.display='none'; return; }
    const res = await fetch(`/api/search.php?q=${encodeURIComponent(q)}`);
    const data = await res.json();
    render(data);
  }, 300);

  input.addEventListener('input', () => fetcher(input.value.trim()));
  document.addEventListener('click', (e) => {
    if (!box.contains(e.target) && e.target !== input) box.style.display='none';
  });
});

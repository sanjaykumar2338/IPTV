async function toggleMyList(contentType, contentId) {
    const btn = document.getElementById('toggleMyList');
    if (!btn) return;
    const action = btn.dataset.inlist === '1' ? 'remove' : 'add';
    const body = `action=${action}&content_type=${encodeURIComponent(contentType)}&content_id=${encodeURIComponent(contentId)}`;
    await fetch('/api/my_list.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body
    });
    if (action === 'add') {
        btn.dataset.inlist = '1';
        btn.innerHTML = '<i class=\"fas fa-check\"></i> In My List';
    } else {
        btn.dataset.inlist = '0';
        btn.innerHTML = '<i class=\"fas fa-plus\"></i> My List';
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const btn = document.getElementById('toggleMyList');
    if (!btn) return;
    btn.addEventListener('click', () => {
        toggleMyList(btn.dataset.type, btn.dataset.id);
    });
});

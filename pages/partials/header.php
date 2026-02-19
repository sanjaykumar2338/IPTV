<?php
if (!isset($site_name)) {
    $site_name = getSetting($pdo, 'site_name', 'Premium IPTV');
}
?>
<header class="header" style="position:sticky; top:0; z-index:1200;">
    <nav class="navbar">
        <div class="nav-container" style="height:72px;">
            <div class="nav-logo">
                <a href="/index.php">
                    <i class="fas fa-tv"></i>
                    <?php echo $site_name; ?>
                </a>
            </div>
            <ul class="nav-menu">
                <li class="nav-item"><a href="/index.php" class="nav-link">Home</a></li>
                <li class="nav-item"><a href="/live-tv.php" class="nav-link">Live TV</a></li>
                <li class="nav-item"><a href="/movies.php" class="nav-link">Movies</a></li>
                <li class="nav-item"><a href="/series-list.php" class="nav-link">Series</a></li>
                <li class="nav-item"><a href="/my-list.php" class="nav-link">My List</a></li>
            </ul>
            <div class="nav-actions" style="display:flex; align-items:center; gap:14px;">
                <button class="sidebar-toggle" id="mobileMenuToggle" aria-label="Open menu" aria-expanded="false" aria-controls="mobileMenu">
                    <i class="fa-solid fa-bars"></i>
                </button>
                <div class="search" style="position:relative;">
                    <input type="text" id="globalSearch" placeholder="Search..." style="padding:10px 12px; border-radius:10px; border:1px solid rgba(255,255,255,0.15); background:rgba(255,255,255,0.06); color:white;">
                    <div id="searchSuggestions" style="position:absolute; top:42px; right:0; left:0; background:rgba(8,10,20,0.95); border:1px solid rgba(255,255,255,0.08); border-radius:10px; display:none; z-index:1500;"></div>
                </div>
                <div class="profile" style="position:relative;">
                    <button id="profileBtn" style="background:none; border:none; color:white; cursor:pointer; font-size:18px;"><i class="fas fa-user-circle"></i></button>
                    <div id="profileMenu" style="display:none; position:absolute; right:0; margin-top:10px; background:rgba(8,10,20,0.95); border:1px solid rgba(255,255,255,0.1); border-radius:10px; min-width:160px;">
                        <a href="#" class="nav-link" style="display:block; padding:10px 12px;">My Account</a>
                        <a href="#" class="nav-link" style="display:block; padding:10px 12px;">Settings</a>
                        <a href="/index.php?logout=1" class="nav-link" style="display:block; padding:10px 12px;">Logout</a>
                    </div>
                </div>
            </div>
        </div>
    </nav>
</header>
<div id="mobileMenuOverlay" class="mobile-menu-overlay" hidden></div>
<aside id="mobileMenu" class="mobile-menu" aria-hidden="true">
    <div class="mobile-menu-header">
        <span>Menu</span>
        <button id="mobileMenuClose" aria-label="Close menu"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="mobile-menu-section">
        <h4>Menu</h4>
        <ul class="mobile-menu-list">
            <li><a href="/index.php">Home</a></li>
            <li><a href="/live-tv.php">Live TV</a></li>
            <li><a href="/movies.php">Movies</a></li>
            <li><a href="/series-list.php">Series</a></li>
            <li><a href="/my-list.php">My List</a></li>
        </ul>
    </div>
    <div class="mobile-menu-section">
        <details class="mobile-menu-accordion">
            <summary>Categories</summary>
            <ul id="mobileCategoriesList" class="mobile-menu-list"></ul>
        </details>
    </div>
</aside>
<script>
    const profileBtn = document.getElementById('profileBtn');
    const profileMenu = document.getElementById('profileMenu');
    profileBtn?.addEventListener('click', () => {
        profileMenu.style.display = profileMenu.style.display === 'block' ? 'none' : 'block';
    });
    document.addEventListener('click', (e) => {
        if (profileMenu && !profileMenu.contains(e.target) && e.target !== profileBtn) {
            profileMenu.style.display = 'none';
        }
    });
</script>

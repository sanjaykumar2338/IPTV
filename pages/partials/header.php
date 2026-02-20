<?php
if (!isset($site_name)) {
    $site_name = getSetting($pdo, 'site_name', 'Premium IPTV');
}
?>
<header class="header" style="position:sticky; top:0; z-index:1200;">
    <nav class="navbar">
        <div class="nav-container" style="height:72px;">
            <div class="nav-left">
                <button class="mobile-menu-btn" id="mobileMenuBtn" aria-label="Open menu">☰</button>
                <div class="nav-logo">
                    <a href="/index.php">
                        <i class="fas fa-tv"></i>
                        <?php echo $site_name; ?>
                    </a>
                </div>
            </div>
            <ul class="nav-menu">
                <li class="nav-item"><a href="/index.php" class="nav-link">Home</a></li>
                <li class="nav-item"><a href="/live-tv.php" class="nav-link">Live TV</a></li>
                <li class="nav-item"><a href="/movies.php" class="nav-link">Movies</a></li>
                <li class="nav-item"><a href="/series-list.php" class="nav-link">Series</a></li>
                <li class="nav-item"><a href="/my-list.php" class="nav-link">My List</a></li>
            </ul>
            <div class="nav-actions" style="display:flex; align-items:center; gap:14px;">
                <div class="search" style="position:relative;">
                    <input type="text" id="globalSearch" placeholder="Search..." style="padding:10px 12px; border-radius:10px; border:1px solid rgba(255,255,255,0.15); background:rgba(255,255,255,0.06); color:white;">
                    <div id="searchSuggestions" style="position:absolute; top:42px; right:0; left:0; background:rgba(8,10,20,0.95); border:1px solid rgba(255,255,255,0.08); border-radius:10px; display:none; z-index:1500;"></div>
                </div>
                <div class="profile" style="position:relative;">
                    <button id="profileBtn" class="profile-btn" aria-label="Profile"><i class="fa-regular fa-circle-user"></i></button>
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

<div class="drawer-overlay" id="drawerOverlay" hidden></div>
<aside class="drawer" id="drawer" aria-hidden="true">
    <div class="drawer-header">
        <span>Menu</span>
        <button class="drawer-close" id="drawerClose" aria-label="Close menu"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="drawer-section">
        <div class="drawer-section-title">Main Menu</div>
        <ul class="drawer-menu">
            <li><a href="/index.php">Home</a></li>
            <li><a href="/live-tv.php">Live TV</a></li>
            <li><a href="/movies.php">Movies</a></li>
            <li><a href="/series-list.php">Series</a></li>
            <li><a href="/my-list.php">My List</a></li>
        </ul>
    </div>
    <div class="drawer-section">
        <button class="drawer-accordion" id="drawerCategoriesToggle" type="button">
            <span>Categories</span>
            <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
        </button>
        <div class="drawer-accordion-body" id="drawerCategories">
            <ul id="drawerCategoriesList" class="drawer-menu"></ul>
        </div>
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

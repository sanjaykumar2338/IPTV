// Main JavaScript for IPTV Website
document.addEventListener('DOMContentLoaded', function() {
    // Mobile navigation toggle
    const navToggle = document.querySelector('.nav-toggle');
    const navMenu = document.querySelector('.nav-menu');
    
    if (navToggle) {
        navToggle.addEventListener('click', function() {
            navMenu.classList.toggle('active');
            this.classList.toggle('active');
        });
    }
    
    // Close mobile menu when clicking on a link
    const navLinks = document.querySelectorAll('.nav-link');
    navLinks.forEach(link => {
        link.addEventListener('click', () => {
            navMenu.classList.remove('active');
            navToggle.classList.remove('active');
        });
    });
    
    // Smooth scrolling for anchor links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });
    
    // Add fade-in animation to elements
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };
    
    const observer = new IntersectionObserver(function(entries) {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('fade-in-up');
            }
        });
    }, observerOptions);
    
    // Observe elements for animation
    document.querySelectorAll('.channel-card, .hero-section, .feature-card').forEach(el => {
        observer.observe(el);
    });
    
    // Search functionality
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const channelCards = document.querySelectorAll('.channel-card');
            
            channelCards.forEach(card => {
                const channelName = card.querySelector('.channel-title').textContent.toLowerCase();
                if (channelName.includes(searchTerm)) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        });
    }
    
    // Category filter functionality
    window.filterChannels = function() {
        const category = document.getElementById('categoryFilter')?.value || 'all';
        const drmType = document.getElementById('drmFilter')?.value || 'all';
        const channels = document.querySelectorAll('.channel-card');
        
        channels.forEach(channel => {
            const channelCategory = channel.getAttribute('data-category');
            const channelDrm = channel.getAttribute('data-drm');
            
            const categoryMatch = category === 'all' || channelCategory === category;
            const drmMatch = drmType === 'all' || 
                            (drmType === 'drm' && channelDrm !== 'none') ||
                            (drmType === 'clearkey' && channelDrm === 'clearkey') ||
                            (drmType === 'none' && channelDrm === 'none');
            
            if (categoryMatch && drmMatch) {
                channel.style.display = 'block';
            } else {
                channel.style.display = 'none';
            }
        });
    }
    
    // Play channel function
    window.playChannel = function(url, name, drmType, licenseKey, licenseUrl) {
        const params = new URLSearchParams({
            stream: url,
            name: name
        });
        
        if (drmType) params.append('drm_type', drmType);
        if (licenseKey) params.append('license_key', licenseKey);
        if (licenseUrl) params.append('license_url', licenseUrl);
        
        window.location.href = `player.php?${params.toString()}`;
    }
    
    // Favorite channel functionality
    window.toggleFavorite = function(channelId) {
        let favorites = JSON.parse(localStorage.getItem('favoriteChannels') || '[]');
        const index = favorites.indexOf(channelId);
        
        if (index > -1) {
            favorites.splice(index, 1);
        } else {
            favorites.push(channelId);
        }
        
        localStorage.setItem('favoriteChannels', JSON.stringify(favorites));
        updateFavoriteIcons();
    }
    
    // Update favorite icons
    function updateFavoriteIcons() {
        const favorites = JSON.parse(localStorage.getItem('favoriteChannels') || '[]');
        document.querySelectorAll('.favorite-btn').forEach(btn => {
            const channelId = btn.getAttribute('data-channel-id');
            if (favorites.includes(parseInt(channelId))) {
                btn.innerHTML = '<i class="fas fa-heart" style="color: #e74c3c;"></i>';
            } else {
                btn.innerHTML = '<i class="far fa-heart"></i>';
            }
        });
    }
    
    // Initialize favorites
    updateFavoriteIcons();
    
    // View count tracking
    window.trackView = function(channelId) {
        fetch(`/api/update_views.php?channel_id=${channelId}`)
            .catch(err => console.log('View tracking failed:', err));
    }
    
    // Load more channels functionality
    let currentPage = 1;
    window.loadMoreChannels = async function() {
        currentPage++;
        try {
            const response = await fetch(`/api/get_channels.php?page=${currentPage}`);
            const channels = await response.json();
            
            if (channels.length > 0) {
                const container = document.getElementById('channelsContainer');
                channels.forEach(channel => {
                    container.appendChild(createChannelCard(channel));
                });
            } else {
                document.getElementById('loadMoreBtn').style.display = 'none';
            }
        } catch (error) {
            console.error('Error loading more channels:', error);
        }
    }
    
    // Create channel card element
    function createChannelCard(channel) {
        const card = document.createElement('div');
        card.className = 'channel-card';
        card.setAttribute('data-category', channel.category);
        card.setAttribute('data-drm', channel.drm_type || 'none');
        
        card.innerHTML = `
            <div class="channel-logo">
                ${channel.logo_url ? 
                    `<img src="${channel.logo_url}" alt="${channel.name}" style="max-width: 100px; max-height: 80px; border-radius: 5px;">` :
                    `<i class="fas fa-tv"></i>`
                }
            </div>
            <div class="channel-info">
                <h3 class="channel-title">${channel.name}</h3>
                <p><strong>Category:</strong> ${channel.category}</p>
                ${channel.drm_type ? 
                    `<p style="color: #e74c3c; font-size: 0.9em;">
                        <i class="fas fa-shield-alt"></i> ${channel.drm_type.toUpperCase()} Protected
                    </p>` :
                    `<p style="color: #2ecc71; font-size: 0.9em;">
                        <i class="fas fa-lock-open"></i> No DRM
                    </p>`
                }
                <div class="channel-stats">
                    <span><i class="fas fa-eye"></i> ${channel.views || 0} views</span>
                    <button onclick="toggleFavorite(${channel.id})" class="favorite-btn" data-channel-id="${channel.id}" style="background: none; border: none; color: #666; cursor: pointer;">
                        <i class="far fa-heart"></i>
                    </button>
                </div>
                <button onclick="playChannel('${channel.stream_url}', '${channel.name}', '${channel.drm_type}', '${channel.license_key}', '${channel.license_url}')" 
                        class="cta-button" style="padding: 8px 15px; font-size: 0.9rem; margin-top: 10px;">
                    <i class="fas fa-play"></i> Watch Now
                </button>
            </div>
        `;
        
        return card;
    }
    
    // Auto-hide header on scroll
    let lastScrollTop = 0;
    const header = document.querySelector('.header');
    
    if (header) {
        window.addEventListener('scroll', function() {
            const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
            
            if (scrollTop > lastScrollTop && scrollTop > 100) {
                header.style.transform = 'translateY(-100%)';
            } else {
                header.style.transform = 'translateY(0)';
            }
            
            lastScrollTop = scrollTop;
        });
    }

    // Drawer (mobile nav + categories)
    (function () {
        const toggleBtn = document.getElementById('mobileMenuBtn');
        const closeBtn = document.getElementById('drawerClose');
        const overlay = document.getElementById('drawerOverlay');
        const drawer = document.getElementById('drawer');
        const categoriesSource = document.getElementById('categoriesList');
        const categoriesDest = document.getElementById('drawerCategoriesList');
        const categoriesToggle = document.getElementById('drawerCategoriesToggle');
        const categoriesBody = document.getElementById('drawerCategories');
        const categoriesSection = categoriesBody?.closest('.drawer-section');

        if (!toggleBtn || !overlay || !drawer) return;

        const openDrawer = () => {
            document.body.classList.add('drawer-open');
            overlay.hidden = false;
            overlay.style.display = 'block';
            drawer.classList.add('open');
            drawer.setAttribute('aria-hidden', 'false');
            toggleBtn.setAttribute('aria-expanded', 'true');
        };

        const closeDrawer = () => {
            document.body.classList.remove('drawer-open');
            overlay.hidden = true;
            overlay.style.display = 'none';
            drawer.classList.remove('open');
            drawer.setAttribute('aria-hidden', 'true');
            toggleBtn.setAttribute('aria-expanded', 'false');
        };

        toggleBtn.addEventListener('click', () => {
            if (document.body.classList.contains('drawer-open')) closeDrawer();
            else openDrawer();
        });

        overlay.addEventListener('click', closeDrawer);
        if (closeBtn) closeBtn.addEventListener('click', closeDrawer);

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') closeDrawer();
        });

        if (categoriesToggle && categoriesBody) {
            categoriesToggle.addEventListener('click', () => {
                const isOpen = categoriesBody.classList.toggle('open');
                categoriesToggle.classList.toggle('open', isOpen);
            });
        }

        const applyCategoryMarkup = (markup) => {
            if (!categoriesDest) return false;
            if (!markup) return false;
            categoriesDest.innerHTML = markup;
            categoriesDest.querySelectorAll('a').forEach(a => a.addEventListener('click', closeDrawer));
            categoriesSection?.classList.remove('is-hidden');
            return true;
        };

        const syncCategories = () => {
            if (!categoriesSource || !categoriesDest) return false;
            if (!categoriesSource.children.length) return false;
            const markup = categoriesSource.innerHTML;
            return applyCategoryMarkup(markup);
        };

        if (!syncCategories() && categoriesSource && categoriesDest) {
            const observer = new MutationObserver(() => {
                if (syncCategories()) observer.disconnect();
            });
            observer.observe(categoriesSource, { childList: true });
        }

        // Fallback: fetch genres if no categories present on the page
        if (categoriesDest && (!categoriesSource || !categoriesSource.children.length)) {
            fetch('/api/home.php', { headers: { Accept: 'application/json' } })
                .then(res => res.ok ? res.json() : Promise.reject())
                .then(data => {
                    const genres = Array.isArray(data.genres) ? data.genres : [];
                    if (!genres.length) {
                        categoriesSection?.classList.add('is-hidden');
                        return;
                    }
                    const markup = genres.map(row => {
                        const genre = (row.genre || 'More to Watch').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
                        const slug = (row.genre || 'more-to-watch').toLowerCase().trim().replace(/[^a-z0-9]+/g,'-').replace(/^-+|-+$/g,'');
                        return `<li><a href="/index.php#genre-${slug}">${genre}</a></li>`;
                    }).join('');
                    applyCategoryMarkup(markup);
                })
                .catch(() => categoriesSection?.classList.add('is-hidden'));
        }

        drawer.addEventListener('click', (e) => {
            const link = e.target.closest('a');
            if (link) closeDrawer();
        });
    })();
});

// Utility functions
const IPTV = {
    // Format number with commas
    formatNumber: function(num) {
        return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
    },
    
    // Debounce function for search
    debounce: function(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    },
    
    // Get URL parameters
    getUrlParams: function() {
        const params = new URLSearchParams(window.location.search);
        const result = {};
        for (const [key, value] of params) {
            result[key] = value;
        }
        return result;
    },
    
    // Show notification
    showNotification: function(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.innerHTML = `
            <div class="notification-content">
                <i class="fas fa-${type === 'success' ? 'check' : type === 'error' ? 'exclamation-triangle' : 'info'}"></i>
                <span>${message}</span>
                <button onclick="this.parentElement.parentElement.remove()">&times;</button>
            </div>
        `;
        
        document.body.appendChild(notification);
        
        setTimeout(() => {
            if (notification.parentElement) {
                notification.remove();
            }
        }, 5000);
    }
};

// Add notification styles
const notificationStyles = `
    .notification {
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 10000;
        min-width: 300px;
        background: white;
        border-radius: 8px;
        box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        animation: slideInRight 0.3s ease;
    }
    
    .notification-success {
        border-left: 4px solid #27ae60;
    }
    
    .notification-error {
        border-left: 4px solid #e74c3c;
    }
    
    .notification-info {
        border-left: 4px solid #3498db;
    }
    
    .notification-content {
        padding: 15px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .notification-content button {
        background: none;
        border: none;
        font-size: 1.2rem;
        cursor: pointer;
        margin-left: auto;
    }
    
    @keyframes slideInRight {
        from { transform: translateX(100%); }
        to { transform: translateX(0); }
    }
`;

const styleSheet = document.createElement('style');
styleSheet.textContent = notificationStyles;
document.head.appendChild(styleSheet);

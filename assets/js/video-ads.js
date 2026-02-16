class VideoAdManager {
    constructor(player, config = {}) {
        this.player = player;
        this.config = config;
        this.ads = [];
        this.currentAd = null;
        this.adContainer = null;
        this.isPlayingAd = false;
        this.adCategory = config.category || '';
        
        this.init();
    }
    
    async init() {
        await this.loadAds();
        this.createAdContainer();
        this.setupEventListeners();
    }
    
    async loadAds() {
        try {
            const url = '../api/video-ads.php' + (this.adCategory ? '?category=' + encodeURIComponent(this.adCategory) : '');
            const response = await fetch(url);
            const data = await response.json();
            
            if (data.success) {
                this.ads = data.data.ads;
                console.log('Loaded', this.ads.length, 'video ads for category:', this.adCategory);
            } else {
                console.error('Failed to load ads:', data.error);
            }
        } catch (error) {
            console.error('Error loading video ads:', error);
        }
    }
    
    createAdContainer() {
        this.adContainer = document.createElement('div');
        this.adContainer.className = 'video-ad-container';
        this.adContainer.style.cssText = `
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: #000;
            z-index: 1000;
            display: none;
        `;
        
        this.adContainer.innerHTML = `
            <video id="ad-video" style="width: 100%; height: 100%; object-fit: contain;"></video>
            <div class="ad-controls">
                <span id="ad-time">0:00</span>
                <button id="skip-ad" style="display: none;">Skip Ad</button>
            </div>
            <div class="ad-overlay">
                <span>Advertisement</span>
            </div>
        `;
        
        // Find player container and append ad container
        const playerContainer = document.querySelector('.player-container');
        if (playerContainer) {
            playerContainer.style.position = 'relative'; // Ensure proper positioning
            playerContainer.appendChild(this.adContainer);
            
            this.adVideo = document.getElementById('ad-video');
            this.skipButton = document.getElementById('skip-ad');
            this.adTime = document.getElementById('ad-time');
            
            // Make ad clickable if it has a target URL
            this.adVideo.addEventListener('click', () => {
                if (this.currentAd && this.currentAd.target_url) {
                    this.trackClick(this.currentAd);
                }
            });
            
            this.setupAdEvents();
        } else {
            console.error('Player container not found');
        }
    }
    
    setupEventListeners() {
        // Listen for player events to trigger ads
        if (this.player && this.player.addEventListener) {
            this.player.addEventListener('load', () => {
                setTimeout(() => this.maybePlayPreRoll(), 1000);
            });
            
            this.player.addEventListener('timeupdate', () => {
                this.checkForMidRoll();
            });
            
            this.player.addEventListener('ended', () => {
                this.maybePlayPostRoll();
            });
        } else {
            console.warn('Player event listeners not available');
            // Fallback: try to play pre-roll after a delay
            setTimeout(() => this.maybePlayPreRoll(), 3000);
        }
    }
    
    setupAdEvents() {
        if (!this.adVideo) return;
        
        this.adVideo.addEventListener('timeupdate', () => {
            this.updateAdUI();
        });
        
        this.adVideo.addEventListener('ended', () => {
            this.finishAd();
        });
        
        this.adVideo.addEventListener('loadedmetadata', () => {
            console.log('Ad video metadata loaded');
        });
        
        this.adVideo.addEventListener('canplay', () => {
            console.log('Ad video can play');
        });
        
        if (this.skipButton) {
            this.skipButton.addEventListener('click', (e) => {
                e.stopPropagation();
                this.skipAd();
            });
        }
        
        // Handle ad errors
        this.adVideo.addEventListener('error', (e) => {
            console.error('Ad playback error:', e, this.adVideo.error);
            this.finishAd();
        });
    }
    
    maybePlayPreRoll() {
        if (this.isPlayingAd || this.ads.length === 0) return;
        
        const preRollAds = this.getAdsByType('pre-roll');
        if (preRollAds.length > 0) {
            const randomAd = preRollAds[Math.floor(Math.random() * preRollAds.length)];
            this.playAd(randomAd);
            return true;
        }
        return false;
    }
    
    checkForMidRoll() {
        if (this.isPlayingAd || this.ads.length === 0) return;
        
        try {
            const currentTime = this.player.currentTime || 0;
            const duration = this.player.duration || 0;
            
            if (duration === 0) return;
            
            // Check for mid-roll ads at 25%, 50%, 75% of content
            const midPoints = [0.25, 0.5, 0.75];
            for (const point of midPoints) {
                const triggerTime = duration * point;
                if (Math.abs(currentTime - triggerTime) < 2) { // 2-second window
                    const midRollAds = this.getAdsByType('mid-roll');
                    if (midRollAds.length > 0) {
                        const randomAd = midRollAds[Math.floor(Math.random() * midRollAds.length)];
                        this.playAd(randomAd);
                        break;
                    }
                }
            }
            
            // Check for random ads (5% chance every 60 seconds)
            if (Math.random() < 0.05 && currentTime > 60 && currentTime % 60 < 1) {
                const randomAds = this.getAdsByType('random');
                if (randomAds.length > 0) {
                    const randomAd = randomAds[Math.floor(Math.random() * randomAds.length)];
                    this.playAd(randomAd);
                }
            }
        } catch (error) {
            console.error('Error checking for mid-roll:', error);
        }
    }
    
    maybePlayPostRoll() {
        if (this.isPlayingAd || this.ads.length === 0) return;
        
        const postRollAds = this.getAdsByType('post-roll');
        if (postRollAds.length > 0) {
            const randomAd = postRollAds[Math.floor(Math.random() * postRollAds.length)];
            this.playAd(randomAd);
            return true;
        }
        return false;
    }
    
    getAdsByType(type) {
        return this.ads.filter(ad => 
            ad.ad_type === type && 
            ad.is_active && 
            this.isAdRelevant(ad)
        );
    }
    
    isAdRelevant(ad) {
        // If ad has specific categories, check if current channel category matches
        if (ad.categories && this.adCategory) {
            const adCategories = ad.categories.split(',');
            return adCategories.includes(this.adCategory) || adCategories.includes('');
        }
        
        return true; // Show ad if no specific categories are set
    }
    
    async playAd(ad) {
        if (this.isPlayingAd || !this.adVideo) return;
        
        this.isPlayingAd = true;
        this.currentAd = ad;
        
        console.log('Playing ad:', ad.ad_name, 'Type:', ad.ad_type, 'URL:', ad.video_url);
        
        // Pause main content
        if (this.player && this.player.pause) {
            this.player.pause();
        }
        
        // Show ad container
        this.adContainer.style.display = 'block';
        
        // Load and play ad
        this.adVideo.src = ad.video_url;
        this.adVideo.currentTime = 0;
        
        // Add clickable class if ad has target URL
        if (ad.target_url) {
            this.adVideo.style.cursor = 'pointer';
            this.adVideo.title = 'Click to visit advertiser';
        } else {
            this.adVideo.style.cursor = 'default';
            this.adVideo.title = '';
        }
        
        // Show skip button if allowed
        if (this.skipButton) {
            this.skipButton.style.display = 'none';
            if (ad.skip_after > 0) {
                setTimeout(() => {
                    if (this.skipButton) {
                        this.skipButton.style.display = 'inline-block';
                    }
                }, ad.skip_after * 1000);
            }
        }
        
        try {
            await this.adVideo.play();
            this.trackImpression(ad);
            console.log('Ad started playing successfully');
        } catch (error) {
            console.error('Error playing ad:', error);
            this.finishAd();
        }
    }
    
    skipAd() {
        console.log('Ad skipped');
        if (this.currentAd) {
            this.trackSkip(this.currentAd);
        }
        this.finishAd();
    }
    
    finishAd() {
        console.log('Finishing ad');
        this.isPlayingAd = false;
        this.currentAd = null;
        
        // Hide ad container
        if (this.adContainer) {
            this.adContainer.style.display = 'none';
        }
        
        // Reset ad video
        if (this.adVideo) {
            this.adVideo.pause();
            this.adVideo.currentTime = 0;
            this.adVideo.src = '';
            this.adVideo.style.cursor = 'default';
            this.adVideo.title = '';
        }
        
        // Hide skip button
        if (this.skipButton) {
            this.skipButton.style.display = 'none';
        }
        
        // Resume main content
        if (this.player && this.player.play) {
            this.player.play().catch(error => {
                console.error('Error resuming content:', error);
            });
        }
    }
    
    updateAdUI() {
        if (!this.adVideo || !this.adTime) return;
        
        const current = this.adVideo.currentTime;
        const duration = this.adVideo.duration;
        
        if (duration && !isNaN(duration)) {
            this.adTime.textContent = this.formatTime(current) + ' / ' + this.formatTime(duration);
        } else {
            this.adTime.textContent = this.formatTime(current);
        }
    }
    
    formatTime(seconds) {
        if (isNaN(seconds)) return '0:00';
        const mins = Math.floor(seconds / 60);
        const secs = Math.floor(seconds % 60);
        return mins + ':' + (secs < 10 ? '0' : '') + secs;
    }
    
    trackImpression(ad) {
        fetch('../api/track-ad.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ ad_id: ad.id, type: 'impression' })
        }).catch(error => console.error('Error tracking impression:', error));
    }
    
    trackSkip(ad) {
        fetch('../api/track-ad.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ ad_id: ad.id, type: 'skip' })
        }).catch(error => console.error('Error tracking skip:', error));
    }
    
    trackClick(ad) {
        fetch('../api/track-ad.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ ad_id: ad.id, type: 'click' })
        }).catch(error => console.error('Error tracking click:', error));
        
        if (ad.target_url) {
            window.open(ad.target_url, '_blank');
        }
    }
}
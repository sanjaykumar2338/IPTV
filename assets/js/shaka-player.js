// Shaka Player Configuration and Utilities
class IPTVPlayer {
    constructor(videoElementId, config = {}) {
        this.videoElement = document.getElementById(videoElementId);
        this.player = null;
        this.ui = null;
        this.config = {
            debug: false,
            drm: {
                clearkey: {},
                widevine: {},
                playready: {}
            },
            streaming: {
                bufferingGoal: 30,
                rebufferingGoal: 2
            },
            ...config
        };
        this.ready = this.init();
    }
    
    async init() {
        if (!this.videoElement) {
            console.error('Video element not found');
            return;
        }
        // Check browser support
        if (!shaka.Player.isBrowserSupported()) {
            this.showError('Shaka Player is not supported in this browser. Please use Chrome, Firefox, or Edge.');
            return;
        }
        
        try {
            // Create Shaka Player instance
            this.player = new shaka.Player(this.videoElement);
            
            // Install UI if available
            if (shaka.ui) {
                this.ui = new shaka.ui.Overlay(
                    this.player,
                    this.videoElement,
                    this.videoElement.parentElement
                );
            }
            
            // Configure player
            this.configurePlayer();
            
            // Set up event listeners
            this.setupEventListeners();
            
            console.log('Shaka Player initialized successfully');
            
        } catch (error) {
            console.error('Error initializing Shaka Player:', error);
            this.showError('Failed to initialize video player: ' + error.message);
        }
    }
    
    configurePlayer() {
        const playerConfig = {
            drm: {
                clearKeys: this.config.drm.clearkey
            },
            streaming: {
                bufferingGoal: this.config.streaming.bufferingGoal,
                rebufferingGoal: this.config.streaming.rebufferingGoal
            },
            manifest: {
                dash: {
                    ignoreMinBufferTime: true
                }
            }
        };
        
        // Add license servers if configured
        if (this.config.drm.widevine.licenseServer) {
            playerConfig.drm.servers = playerConfig.drm.servers || {};
            playerConfig.drm.servers['com.widevine.alpha'] = this.config.drm.widevine.licenseServer;
        }
        
        if (this.config.drm.playready.licenseServer) {
            playerConfig.drm.servers = playerConfig.drm.servers || {};
            playerConfig.drm.servers['com.microsoft.playready'] = this.config.drm.playready.licenseServer;
        }
        
        this.player.configure(playerConfig);
        
        // Enable debug logging if configured
        if (this.config.debug) {
            shaka.log.setLevel(shaka.log.Level.DEBUG);
        }
    }
    
    setupEventListeners() {
        // Player event listeners
        this.player.addEventListener('error', (event) => {
            this.onPlayerError(event);
        });
        
        this.player.addEventListener('loading', () => {
            this.onPlayerLoading();
        });
        
        this.player.addEventListener('loaded', () => {
            this.onPlayerLoaded();
        });
        
        this.player.addEventListener('buffering', (event) => {
            this.onPlayerBuffering(event);
        });
        
        // Network event listeners
        this.videoElement.addEventListener('loadstart', () => {
            this.updateStatus('Loading stream...');
        });
        
        this.videoElement.addEventListener('canplay', () => {
            this.updateStatus('Stream ready');
        });
        
        this.videoElement.addEventListener('waiting', () => {
            this.updateStatus('Buffering...');
        });
        
        this.videoElement.addEventListener('playing', () => {
            this.updateStatus('Playing');
        });
    }
    
    async loadStream(streamUrl, drmConfig = {}) {
        await this.ready;
        if (!this.player) {
            this.showError('Player not initialized.');
            return;
        }
        try {
            this.updateStatus('Loading stream...');
            
            // Update DRM configuration if provided
            if (drmConfig.clearkey) {
                this.player.configure({
                    drm: {
                        clearKeys: drmConfig.clearkey
                    }
                });
            }
            
            // Load the stream
            await this.player.load(streamUrl);
            
            this.updateStatus('Stream loaded successfully');
            console.log('Stream loaded successfully:', streamUrl);
            
        } catch (error) {
            console.error('Error loading stream:', error);
            this.handleLoadError(error);
        }
    }
    
    onPlayerError(event) {
        console.error('Player error:', event.detail);
        
        const error = event.detail;
        let errorMessage = 'An error occurred while playing the stream.';
        
        if (error.code === shaka.util.Error.Code.BLOCKED_BY_CLIENT) {
            errorMessage = 'Playback blocked by browser. Please check your ad blocker.';
        } else if (error.code === shaka.util.Error.Code.NETWORK_ERROR) {
            errorMessage = 'Network error. Please check your internet connection.';
        } else if (error.code === shaka.util.Error.Code.DRM_SYSTEM_CREATION_FAILED) {
            errorMessage = 'DRM system not supported. Please try a different browser.';
        } else if (error.code === shaka.util.Error.Code.LICENSE_REQUEST_FAILED) {
            errorMessage = 'DRM license request failed. The stream may be unavailable.';
        }
        
        this.showError(errorMessage);
    }
    
    onPlayerLoading() {
        this.showLoadingSpinner();
    }
    
    onPlayerLoaded() {
        this.hideLoadingSpinner();
    }
    
    onPlayerBuffering(event) {
        if (event.buffering) {
            this.showLoadingSpinner();
        } else {
            this.hideLoadingSpinner();
        }
    }
    
    handleLoadError(error) {
        let errorMessage = 'Failed to load stream. ';
        
        switch (error.code) {
            case shaka.util.Error.Code.MANIFEST_LOAD_FAILED:
                errorMessage += 'Stream manifest not found.';
                break;
            case shaka.util.Error.Code.VIDEO_ERROR:
                errorMessage += 'Video format not supported.';
                break;
            case shaka.util.Error.Code.DRM_LICENSE_REQUEST_FAILED:
                errorMessage += 'DRM license acquisition failed.';
                break;
            default:
                errorMessage += error.message;
        }
        
        this.showError(errorMessage);
    }
    
    showError(message) {
        this.updateStatus('Error: ' + message, 'error');
        
        // Create error display
        const errorDiv = document.createElement('div');
        errorDiv.className = 'player-error';
        errorDiv.innerHTML = `
            <div style="text-align: center; padding: 20px; color: #e74c3c;">
                <i class="fas fa-exclamation-triangle" style="font-size: 3rem; margin-bottom: 15px;"></i>
                <h3>Playback Error</h3>
                <p>${message}</p>
                <button onclick="this.closest('.player-error').remove()" class="cta-button">
                    Try Again
                </button>
            </div>
        `;
        
        this.videoElement.parentElement.appendChild(errorDiv);
    }
    
    showLoadingSpinner() {
        let spinner = this.videoElement.parentElement.querySelector('.loading-spinner');
        if (!spinner) {
            spinner = document.createElement('div');
            spinner.className = 'loading-spinner';
            spinner.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            this.videoElement.parentElement.appendChild(spinner);
        }
        spinner.style.display = 'block';
    }
    
    hideLoadingSpinner() {
        const spinner = this.videoElement.parentElement.querySelector('.loading-spinner');
        if (spinner) {
            spinner.style.display = 'none';
        }
    }
    
    updateStatus(message, type = 'info') {
        const statusElement = document.getElementById('player-status');
        if (statusElement) {
            statusElement.innerHTML = `<p class="status-${type}">${message}</p>`;
        }
        
        // Also update console if debug enabled
        if (this.config.debug) {
            console.log(`Player Status [${type}]:`, message);
        }
    }
    
    // Public methods
    play() {
        this.videoElement.play().catch(error => {
            console.error('Play failed:', error);
        });
    }
    
    pause() {
        this.videoElement.pause();
    }
    
    stop() {
        this.pause();
        this.videoElement.currentTime = 0;
    }
    
    setVolume(volume) {
        this.videoElement.volume = Math.max(0, Math.min(1, volume));
    }
    
    destroy() {
        if (this.player) {
            this.player.destroy();
        }
        if (this.ui) {
            this.ui.destroy();
        }
    }
}

// Utility function to parse ClearKey license
function parseClearKeyLicense(licenseKey) {
    if (!licenseKey) return {};
    
    try {
        const parts = licenseKey.split(':');
        if (parts.length === 2) {
            return {
                [parts[0]]: parts[1]
            };
        }
    } catch (error) {
        console.error('Error parsing ClearKey license:', error);
    }
    
    return {};
}

// Initialize player when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Auto-initialize player if video element exists
    const videoElement = document.getElementById('video-player');
    if (videoElement) {
        // Get DRM configuration from URL parameters
        const urlParams = new URLSearchParams(window.location.search);
        const drmType = urlParams.get('drm_type');
        const licenseKey = urlParams.get('license_key');
        const licenseUrl = urlParams.get('license_url');
        
        const drmConfig = {};
        
        if (drmType === 'clearkey' && licenseKey) {
            drmConfig.clearkey = parseClearKeyLicense(licenseKey);
        }
        
        // Create player instance
        window.iptvPlayer = new IPTVPlayer('video-player', {
            debug: true,
            drm: {
                widevine: {
                    licenseServer: licenseUrl || 'https://widevine-proxy.appspot.com/proxy'
                },
                playready: {
                    licenseServer: licenseUrl || 'https://playready.directtaps.net/pr/svc/rightsmanager.asmx'
                }
            }
        });
        
        // Load the stream after init completes
        const streamUrl = urlParams.get('stream');
        if (streamUrl) {
            window.iptvPlayer.ready.then(() => {
                window.iptvPlayer.loadStream(streamUrl, drmConfig);
            });
        }
    }
});

// Global player controls
window.playerControls = {
    play: function() {
        if (window.iptvPlayer) window.iptvPlayer.play();
    },
    
    pause: function() {
        if (window.iptvPlayer) window.iptvPlayer.pause();
    },
    
    stop: function() {
        if (window.iptvPlayer) window.iptvPlayer.stop();
    },
    
    setVolume: function(volume) {
        if (window.iptvPlayer) window.iptvPlayer.setVolume(volume);
    },
    
    toggleFullscreen: function() {
        const videoContainer = document.querySelector('.player-container');
        if (!document.fullscreenElement) {
            videoContainer.requestFullscreen().catch(err => {
                console.error('Error attempting to enable fullscreen:', err);
            });
        } else {
            document.exitFullscreen();
        }
    }
};

class IPTVPlayer {
    constructor(videoElementId, config = {}) {
        this.videoElement = document.getElementById(videoElementId);
        this.player = null;
        this.ui = null;
        this.errorOverlay = null;

        const defaultStreaming = {
            bufferingGoal: 60,
            rebufferingGoal: 8,
            bufferBehind: 30,
            retryParameters: {
                maxAttempts: 6,
                baseDelay: 800,
                backoffFactor: 2,
                fuzzFactor: 0.5,
                timeout: 20000,
                stallTimeout: 5000,
                connectionTimeout: 10000
            }
        };

        this.config = {
            debug: false,
            loadTimeoutMs: 45000,
            autoProxyFallback: true,
            drm: {
                clearkey: {},
                widevine: {},
                playready: {}
            },
            streaming: defaultStreaming,
            manifestRetryParameters: {
                maxAttempts: 5,
                baseDelay: 600,
                backoffFactor: 2,
                fuzzFactor: 0.5,
                timeout: 20000,
                stallTimeout: 5000,
                connectionTimeout: 10000
            },
            drmRetryParameters: {
                maxAttempts: 4,
                baseDelay: 600,
                backoffFactor: 2,
                fuzzFactor: 0.5,
                timeout: 15000,
                stallTimeout: 4000,
                connectionTimeout: 8000
            },
            ...config
        };

        this.config.drm = {
            clearkey: {},
            widevine: {},
            playready: {},
            ...(config.drm || {})
        };

        this.config.streaming = {
            ...defaultStreaming,
            ...(config.streaming || {})
        };

        this.config.streaming.retryParameters = {
            ...defaultStreaming.retryParameters,
            ...((config.streaming && config.streaming.retryParameters) || {})
        };

        this.ready = this.init();
    }

    async init() {
        if (!this.videoElement) {
            console.error("Video element not found");
            return;
        }

        if (!shaka.Player.isBrowserSupported()) {
            this.showError("This browser is not supported for secure streaming. Please use the latest Chrome, Edge, or Firefox.");
            return;
        }

        try {
            this.player = new shaka.Player(this.videoElement);

            if (shaka.ui) {
                this.ui = new shaka.ui.Overlay(
                    this.player,
                    this.videoElement,
                    this.videoElement.parentElement
                );
            }

            this.configurePlayer();
            this.setupEventListeners();
            this.updateStatus("Player ready");

            if (this.config.debug) {
                console.log("Shaka player initialized");
            }
        } catch (error) {
            console.error("Player init failed:", error);
            this.showError("Could not initialize the video player.");
        }
    }

    configurePlayer() {
        if (!this.player) return;

        const playerConfig = {
            abr: {
                enabled: true
            },
            drm: {
                clearKeys: this.config.drm.clearkey,
                retryParameters: this.config.drmRetryParameters
            },
            streaming: {
                bufferingGoal: this.config.streaming.bufferingGoal,
                rebufferingGoal: this.config.streaming.rebufferingGoal,
                bufferBehind: this.config.streaming.bufferBehind,
                retryParameters: this.config.streaming.retryParameters,
                stallEnabled: true,
                stallThreshold: 10,
                stallSkip: 0.1
            },
            manifest: {
                retryParameters: this.config.manifestRetryParameters,
                dash: {
                    ignoreMinBufferTime: true
                }
            }
        };

        if (this.config.drm.widevine.licenseServer) {
            playerConfig.drm.servers = playerConfig.drm.servers || {};
            playerConfig.drm.servers["com.widevine.alpha"] = this.config.drm.widevine.licenseServer;
        }

        if (this.config.drm.playready.licenseServer) {
            playerConfig.drm.servers = playerConfig.drm.servers || {};
            playerConfig.drm.servers["com.microsoft.playready"] = this.config.drm.playready.licenseServer;
        }

        this.player.configure(playerConfig);

        if (this.config.debug) {
            shaka.log.setLevel(shaka.log.Level.DEBUG);
        }
    }

    setupEventListeners() {
        if (!this.player || !this.videoElement) return;

        this.player.addEventListener("error", (event) => {
            this.onPlayerError(event.detail);
        });

        this.player.addEventListener("loading", () => {
            this.showLoadingSpinner();
            this.updateStatus("Loading stream...");
        });

        this.player.addEventListener("loaded", () => {
            this.hideLoadingSpinner();
            this.updateStatus("Stream loaded");
        });

        this.player.addEventListener("buffering", (event) => {
            if (event.buffering) {
                this.showLoadingSpinner();
                this.updateStatus("Buffering...");
            } else {
                this.hideLoadingSpinner();
                this.updateStatus("Playing");
            }
        });

        this.videoElement.addEventListener("waiting", () => {
            this.updateStatus("Buffering...");
        });

        this.videoElement.addEventListener("playing", () => {
            this.clearError();
            this.updateStatus("Playing");
        });

        this.videoElement.addEventListener("error", () => {
            this.showError("Playback failed. Please retry the stream.");
        });
    }

    async loadStream(streamUrl, drmConfig = {}) {
        await this.ready;
        if (!this.player) {
            this.showError("Player is not initialized.");
            return false;
        }

        if (drmConfig.clearkey) {
            this.player.configure({
                drm: { clearKeys: drmConfig.clearkey }
            });
        }

        const candidates = this.buildLoadCandidates(streamUrl);
        let lastError = null;

        for (let i = 0; i < candidates.length; i++) {
            const candidate = candidates[i];
            const attempt = i + 1;

            try {
                this.clearError();
                this.showLoadingSpinner();
                this.updateStatus(`Loading stream (attempt ${attempt}/${candidates.length})...`);

                await this.loadWithTimeout(candidate, this.config.loadTimeoutMs);
                await this.tryPlay();
                this.hideLoadingSpinner();
                this.updateStatus("Playing");

                if (this.config.debug) {
                    console.log("Stream loaded:", candidate);
                }

                return true;
            } catch (error) {
                lastError = error;
                await this.safeUnload();

                if (this.config.debug) {
                    console.warn("Stream load attempt failed:", attempt, candidate, error);
                }

                if (attempt < candidates.length) {
                    this.updateStatus("Primary stream failed. Retrying with fallback source...", "warn");
                }
            }
        }

        this.hideLoadingSpinner();
        this.handleLoadError(lastError);
        return false;
    }

    buildLoadCandidates(streamUrl) {
        const candidates = [];
        const push = (url) => {
            if (url && !candidates.includes(url)) {
                candidates.push(url);
            }
        };

        push(streamUrl);

        if (!this.config.autoProxyFallback) {
            return candidates;
        }

        const isHls = (url) => /\.m3u8(?:\?|$)/i.test(url || "");
        const decodedSource = this.extractOriginalUrl(streamUrl);

        if (streamUrl.includes("/proxy.php?url=")) {
            if (decodedSource && isHls(decodedSource)) {
                push(decodedSource);
            }
        } else if (/^https?:\/\//i.test(streamUrl) && isHls(streamUrl)) {
            push(this.buildProxyUrl(streamUrl));
        }

        return candidates;
    }

    buildProxyUrl(url) {
        return `/proxy.php?url=${encodeURIComponent(url)}`;
    }

    extractOriginalUrl(url) {
        if (!url || !url.includes("/proxy.php?url=")) {
            return null;
        }

        try {
            const parsed = new URL(url, window.location.origin);
            const original = parsed.searchParams.get("url");
            return original ? decodeURIComponent(original) : null;
        } catch (error) {
            return null;
        }
    }

    async loadWithTimeout(url, timeoutMs) {
        let timeoutId;

        const timeoutPromise = new Promise((_, reject) => {
            timeoutId = setTimeout(() => {
                reject({ code: "LOAD_TIMEOUT" });
            }, timeoutMs);
        });

        try {
            await Promise.race([
                this.player.load(url),
                timeoutPromise
            ]);
        } finally {
            clearTimeout(timeoutId);
        }
    }

    async safeUnload() {
        if (!this.player) return;
        try {
            await this.player.unload();
        } catch (error) {
            if (this.config.debug) {
                console.warn("Unload after failed load returned:", error);
            }
        }
    }

    async tryPlay() {
        if (!this.videoElement || typeof this.videoElement.play !== "function") return;
        try {
            await this.videoElement.play();
        } catch (error) {
            if (this.config.debug) {
                console.warn("Autoplay not allowed or interrupted:", error);
            }
        }
    }

    onPlayerError(error) {
        if (this.config.debug) {
            console.error("Shaka player error:", error);
        }
        this.handleLoadError(error);
    }

    handleLoadError(error) {
        const friendly = this.getFriendlyError(error);
        this.showError(friendly.message, friendly.code);
    }

    getFriendlyError(error) {
        // friendly playback messages
        const code = error && (typeof error.code === "number" || typeof error.code === "string")
            ? error.code
            : null;

        if (code === "LOAD_TIMEOUT") {
            return {
                code,
                message: "The stream timed out while loading. Please retry in a few seconds."
            };
        }

        if (code === 4000) {
            return {
                code,
                message: "The stream source responded with unstable data. This is usually a provider issue. Please retry."
            };
        }

        if (typeof code === "number" && code >= 1000 && code < 2000) {
            return {
                code,
                message: "Network issue while fetching the stream. Please check connectivity and retry."
            };
        }

        if (typeof code === "number" && code >= 3000 && code < 4000) {
            return {
                code,
                message: "The DRM/license check failed for this stream. Please try another stream or retry later."
            };
        }

        if (typeof code === "number" && code >= 4000 && code < 5000) {
            return {
                code,
                message: "The stream manifest is unavailable or malformed. Please retry shortly."
            };
        }

        return {
            code,
            message: "We could not play this stream right now. Please retry."
        };
    }

    showError(message, code = null) {
        const details = code !== null ? ` (code: ${code})` : "";
        this.updateStatus(`Playback issue${details}`, "error");

        const parent = this.videoElement ? this.videoElement.parentElement : null;
        if (!parent) return;

        this.clearError();

        const errorDiv = document.createElement("div");
        errorDiv.className = "player-error";
        errorDiv.style.padding = "20px";
        errorDiv.style.textAlign = "center";
        errorDiv.style.color = "#e74c3c";

        const icon = document.createElement("i");
        icon.className = "fas fa-exclamation-triangle";
        icon.style.fontSize = "3rem";
        icon.style.marginBottom = "15px";

        const title = document.createElement("h3");
        title.textContent = "Playback Error";

        const text = document.createElement("p");
        text.textContent = message;

        const retryButton = document.createElement("button");
        retryButton.className = "cta-button";
        retryButton.type = "button";
        retryButton.textContent = "Retry Stream";
        retryButton.addEventListener("click", () => window.location.reload());

        errorDiv.appendChild(icon);
        errorDiv.appendChild(title);
        errorDiv.appendChild(text);
        errorDiv.appendChild(retryButton);

        parent.appendChild(errorDiv);
        this.errorOverlay = errorDiv;
    }

    clearError() {
        if (this.errorOverlay && this.errorOverlay.parentNode) {
            this.errorOverlay.parentNode.removeChild(this.errorOverlay);
        }
        this.errorOverlay = null;
    }

    showLoadingSpinner() {
        const parent = this.videoElement ? this.videoElement.parentElement : null;
        if (!parent) return;

        let spinner = parent.querySelector(".loading-spinner");
        if (!spinner) {
            spinner = document.createElement("div");
            spinner.className = "loading-spinner";
            spinner.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            parent.appendChild(spinner);
        }
        spinner.style.display = "block";
    }

    hideLoadingSpinner() {
        const parent = this.videoElement ? this.videoElement.parentElement : null;
        if (!parent) return;

        const spinner = parent.querySelector(".loading-spinner");
        if (spinner) {
            spinner.style.display = "none";
        }
    }

    updateStatus(message, type = "info") {
        const statusElement = document.getElementById("player-status");
        if (statusElement) {
            statusElement.textContent = message;
            statusElement.dataset.statusType = type;
        }

        if (this.config.debug) {
            console.log(`Player status [${type}]:`, message);
        }
    }

    play() {
        if (!this.videoElement) return;
        this.videoElement.play().catch((error) => {
            if (this.config.debug) {
                console.warn("Play failed:", error);
            }
        });
    }

    pause() {
        if (this.videoElement) {
            this.videoElement.pause();
        }
    }

    stop() {
        if (!this.videoElement) return;
        this.pause();
        this.videoElement.currentTime = 0;
    }

    setVolume(volume) {
        if (!this.videoElement) return;
        this.videoElement.volume = Math.max(0, Math.min(1, volume));
    }

    destroy() {
        this.clearError();
        if (this.player) {
            this.player.destroy();
            this.player = null;
        }
        if (this.ui) {
            this.ui.destroy();
            this.ui = null;
        }
    }
}

function parseClearKeyLicense(licenseKey) {
    if (!licenseKey) return {};

    try {
        const parts = licenseKey.split(":");
        if (parts.length === 2) {
            return { [parts[0]]: parts[1] };
        }
    } catch (error) {
        console.error("Error parsing ClearKey license:", error);
    }

    return {};
}

window.playerControls = {
    play() {
        if (window.iptvPlayer) window.iptvPlayer.play();
    },

    pause() {
        if (window.iptvPlayer) window.iptvPlayer.pause();
    },

    stop() {
        if (window.iptvPlayer) window.iptvPlayer.stop();
    },

    setVolume(volume) {
        if (window.iptvPlayer) window.iptvPlayer.setVolume(volume);
    },

    toggleFullscreen() {
        const videoContainer = document.querySelector(".player-container");
        if (!videoContainer) return;

        if (!document.fullscreenElement) {
            videoContainer.requestFullscreen().catch((error) => {
                console.error("Error enabling fullscreen:", error);
            });
        } else {
            document.exitFullscreen();
        }
    }
};

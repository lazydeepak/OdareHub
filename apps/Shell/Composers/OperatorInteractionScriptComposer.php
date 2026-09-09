<?php
declare(strict_types=1);

namespace Apps\Shell\Composers;

final class OperatorInteractionScriptComposer
{
    /**
     * @param array<string,mixed> $data
     * @param array<string,string> $i18n
     * @param array<int|string,string> $operatorThemeChoices
     */
    public static function render(
        array $data,
        array $i18n,
        string $operatorSearchScope,
        string $operatorDefaultThemePreference,
        array $operatorThemeChoices,
        string $username
    ): string {
        ob_start();
        ?>
    <script>
        // Hamburger menu toggle + sidebar collapse
        // Must match CSS @media (max-width: 820px) mobile breakpoint.
        const DESKTOP_BREAKPOINT = 820;
        const DESKTOP_SIDEBAR_STATE_KEY = 'operatorSidebarCollapsed';
        const hamburgerToggle = document.getElementById('hamburgerToggle');
        const hamburgerMenu = document.getElementById('hamburgerMenu');
        const hamburgerBackdrop = document.getElementById('hamburgerBackdrop');
        const appShell = document.querySelector('.app-shell');
        const contextualSidebar = document.getElementById('contextualSidebar');
        const mainContent = document.querySelector('.main-content');
        const headerRouteSearch = document.getElementById('headerRouteSearch');
        const operatorSearchResults = document.getElementById('operatorSearchResults');
        const headerOriginalScanButton = document.getElementById('headerOriginalScanButton');
        const headerAvatarButton = document.getElementById('headerAvatarButton');
        const headerAvatarPanel = document.getElementById('headerAvatarPanel');
        const operatorLangSelect = null;
        const operatorCurrencySelect = null;
        const operatorThemeSelect = null;
        const OPERATOR_I18N = <?php echo json_encode($i18n, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
        const DAILY_ORDER_CHART = <?php echo json_encode((array)($data['daily_order_chart'] ?? []), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
        const OPERATOR_SEARCH_SCOPE = <?php echo json_encode($operatorSearchScope, JSON_UNESCAPED_SLASHES); ?>;
        const THEME_FALLBACK_PREFERENCE = <?php echo json_encode($operatorDefaultThemePreference, JSON_UNESCAPED_SLASHES); ?>;
        const THEME_ALLOWED_PREFERENCES = <?php echo json_encode(array_values(array_keys($operatorThemeChoices)), JSON_UNESCAPED_SLASHES); ?>;
        const THEME_APPLY_URL = <?php echo json_encode('/u/' . rawurlencode((string)($data['username'] ?? $username)) . '/theme/apply', JSON_UNESCAPED_SLASHES); ?>;
        const OVERLAY_EFFECT_STRENGTH_STORAGE_KEY = 'shell-overlay-effect-strength';
        const DEFAULT_OVERLAY_EFFECT_STRENGTH = 40;
        let operatorSystemThemeMediaQuery = null;
        let operatorSearchRequestSequence = 0;
        let operatorSearchActiveIndex = -1;
        let operatorSearchDebounceTimer = null;
        let operatorSearchResultRows = [];
        const operatorSearchResultsAdapterFactory = window.OdareHubOS
            && window.OdareHubOS.ShellOverlayAdapters
            && window.OdareHubOS.ShellOverlayAdapters.createOperatorSearchResultsAdapter;
        const operatorSearchResultsAdapter = typeof operatorSearchResultsAdapterFactory === 'function'
            ? operatorSearchResultsAdapterFactory({
                triggerId: 'headerRouteSearch',
                surfaceId: 'operatorSearchResults',
                onControllerClose: function () { hideOperatorSearchResults(); },
            })
            : null;

        const operatorHamburgerDrawerAdapterFactory = window.OdareHubOS
            && window.OdareHubOS.ShellOverlayAdapters
            && window.OdareHubOS.ShellOverlayAdapters.createOperatorHamburgerDrawerAdapter;
        const operatorHamburgerDrawerAdapter = typeof operatorHamburgerDrawerAdapterFactory === 'function'
            ? operatorHamburgerDrawerAdapterFactory({
                triggerId: 'hamburgerToggle',
                surfaceId: 'hamburgerMenu',
                backdropId: 'hamburgerBackdrop',
                scrollTargetSelector: '.main-content',
                setLegacyOpen: setHamburgerMenuOpen,
            })
            : null;

        const operatorAvatarPanelAdapterFactory = window.OdareHubOS
            && window.OdareHubOS.ShellOverlayAdapters
            && window.OdareHubOS.ShellOverlayAdapters.createOperatorAvatarPanelAdapter;
        const operatorAvatarPanelAdapter = typeof operatorAvatarPanelAdapterFactory === 'function'
            ? operatorAvatarPanelAdapterFactory({
                triggerId: 'headerAvatarButton',
                surfaceId: 'headerAvatarPanel',
                backdropId: 'avatarBackdrop',
                scrollTargetSelector: '.main-content',
                setLegacyOpen: setAvatarPanelOpen,
            })
            : null;

        function switchQueryParam(key, value) {
            const url = new URL(window.location.href);
            url.searchParams.set(key, value);
            // Match global header navigation semantics.
            window.location.href = url.toString();
        }

        function switchLang(value) {
            switchQueryParam('lang', value);
        }

        function switchCurrency(value) {
            switchQueryParam('currency', value);
        }

        const THEME_STORAGE_KEY = 'erp-theme-preference';

        function normalizeThemePreference(value) {
            const raw = (value || '').toString().trim().toLowerCase();
            const fallbackParts = (THEME_FALLBACK_PREFERENCE || 'system-liquid-glass').split('-');
            const fallbackStyle = fallbackParts.slice(1).join('-') || 'liquid-glass';
            if (THEME_ALLOWED_PREFERENCES.includes(raw)) {
                return raw;
            }
            // Backward compatibility with simplified selector values.
            if (raw === 'light') {
                return 'light-' + fallbackStyle;
            }
            if (raw === 'dark') {
                return 'dark-' + fallbackStyle;
            }
            if (raw === 'system') {
                return 'system-' + fallbackStyle;
            }
            return THEME_FALLBACK_PREFERENCE || 'system-liquid-glass';
        }

        function getThemeModeFromPreference(pref) {
            if (pref.startsWith('dark-')) {
                return 'dark';
            }
            if (pref.startsWith('light-')) {
                return 'light';
            }
            return 'system';
        }

        function resolveSystemThemeMode() {
            return (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches)
                ? 'dark'
                : 'light';
        }

        function saveOperatorThemePreference(value) {
            try {
                window.localStorage.setItem(THEME_STORAGE_KEY, normalizeThemePreference(value));
            } catch (error) {
                // Ignore storage failures.
            }
        }

        function persistOperatorThemePreference(value) {
            const normalized = normalizeThemePreference(value);
            if (!csrfToken || !THEME_APPLY_URL) {
                return Promise.resolve({ ok: false });
            }

            const payload = new URLSearchParams();
            payload.set('csrf', csrfToken);
            payload.set('theme', normalized);

            return fetch(THEME_APPLY_URL, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: payload.toString(),
            }).then((response) => {
                if (!response.ok) {
                    throw new Error('theme_apply_http_' + String(response.status));
                }
                return response.json();
            }).then((body) => {
                if (!body || body.ok !== true) {
                    throw new Error('theme_apply_rejected');
                }
                const serverTheme = normalizeThemePreference(body.theme || normalized);
                saveOperatorThemePreference(serverTheme);
                applyOperatorThemePreference(serverTheme);
                const themeSelect = document.getElementById('operatorThemeSelect');
                if (themeSelect) {
                    themeSelect.value = serverTheme;
                }
                return { ok: true, theme: serverTheme };
            }).catch(() => {
                return { ok: false, theme: normalized };
            });
        }

        function applyOperatorThemePreference(value) {
            const normalizedPreference = normalizeThemePreference(value);
            const mode = getThemeModeFromPreference(normalizedPreference);
            const effectiveMode = mode === 'system' ? resolveSystemThemeMode() : mode;
            const colorStyle = normalizedPreference.split('-').slice(1).join('-') || 'liquid-glass';

            document.documentElement.setAttribute('data-theme-preference', normalizedPreference);
            document.documentElement.setAttribute('data-theme-mode', mode);
            document.documentElement.setAttribute('data-theme', effectiveMode);
            document.documentElement.setAttribute('data-color-style', colorStyle);
            document.documentElement.style.colorScheme = effectiveMode;

            return normalizedPreference;
        }

        function handleOperatorSystemThemeChange() {
            if (document.documentElement.getAttribute('data-theme-mode') !== 'system') {
                return;
            }
            const currentPreference = document.documentElement.getAttribute('data-theme-preference') || THEME_FALLBACK_PREFERENCE;
            applyOperatorThemePreference(currentPreference);
        }

        function initOperatorSystemThemeListener() {
            if (!window.matchMedia || operatorSystemThemeMediaQuery) {
                return;
            }
            operatorSystemThemeMediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
            if (typeof operatorSystemThemeMediaQuery.addEventListener === 'function') {
                operatorSystemThemeMediaQuery.addEventListener('change', handleOperatorSystemThemeChange);
            } else if (typeof operatorSystemThemeMediaQuery.addListener === 'function') {
                operatorSystemThemeMediaQuery.addListener(handleOperatorSystemThemeChange);
            }
        }

        function loadOperatorThemePreference() {
            let storedPreference = THEME_FALLBACK_PREFERENCE;
            try {
                storedPreference = window.localStorage.getItem(THEME_STORAGE_KEY) || THEME_FALLBACK_PREFERENCE;
            } catch (error) {
                storedPreference = THEME_FALLBACK_PREFERENCE;
            }

            const normalizedPreference = normalizeThemePreference(storedPreference);
            const appliedPreference = applyOperatorThemePreference(normalizedPreference);

            const themeSelect = document.getElementById('operatorThemeSelect');
            if (themeSelect) {
                themeSelect.value = appliedPreference;
            }

            initOperatorSystemThemeListener();
        }

        function clampOverlayEffectStrength(value) {
            const numeric = Number(value);
            if (!Number.isFinite(numeric)) {
                return DEFAULT_OVERLAY_EFFECT_STRENGTH;
            }
            return Math.max(0, Math.min(100, Math.round(numeric)));
        }

        function loadOverlayEffectStrength() {
            try {
                const storedStrength = window.localStorage.getItem(OVERLAY_EFFECT_STRENGTH_STORAGE_KEY);
                return storedStrength === null ? DEFAULT_OVERLAY_EFFECT_STRENGTH : clampOverlayEffectStrength(storedStrength);
            } catch (error) {
                return DEFAULT_OVERLAY_EFFECT_STRENGTH;
            }
        }

        function saveOverlayEffectStrength(value) {
            const strength = clampOverlayEffectStrength(value);
            try {
                window.localStorage.setItem(OVERLAY_EFFECT_STRENGTH_STORAGE_KEY, String(strength));
            } catch (error) {
                // Ignore storage failures.
            }
            return strength;
        }

        function applyOverlayEffectStrength(value) {
            const strength = clampOverlayEffectStrength(value);
            const shellOverlay = window['OdareHubOS.ShellOverlay'];
            const controller = shellOverlay && shellOverlay.visualEffects;
            if (controller && typeof controller.setStrength === 'function') {
                controller.setStrength(strength);
            }
            document.querySelectorAll('[data-overlay-effect-strength]').forEach((slider) => {
                slider.value = String(strength);
            });
            document.querySelectorAll('[data-overlay-effect-strength-value]').forEach((output) => {
                output.textContent = String(strength);
            });
            return strength;
        }

        function initOverlayEffectStrengthControls() {
            const initialStrength = applyOverlayEffectStrength(loadOverlayEffectStrength());
            document.querySelectorAll('[data-overlay-effect-strength]').forEach((slider) => {
                slider.value = String(initialStrength);
                slider.addEventListener('input', () => {
                    applyOverlayEffectStrength(slider.value);
                });
                slider.addEventListener('change', () => {
                    applyOverlayEffectStrength(saveOverlayEffectStrength(slider.value));
                });
            });
        }

        function initOperatorPreferences() {
            loadOperatorThemePreference();
            initOverlayEffectStrengthControls();
        }

        function setAvatarPanelOpen(shouldOpen) {
            var panel = document.getElementById('headerAvatarPanel');
            if (!panel) {
                return;
            }
            panel.classList.toggle('open', shouldOpen);
            const backdrop = document.getElementById('avatarBackdrop');
            if (backdrop) {
                backdrop.classList.toggle('open', shouldOpen);
            }
            var btn = document.getElementById('headerAvatarButton');
            if (btn) {
                btn.setAttribute('aria-expanded', shouldOpen ? 'true' : 'false');
            }
            if (shouldOpen) {
                const themeSelect = document.getElementById('operatorThemeSelect');
                if (themeSelect) {
                    const activeTheme = normalizeThemePreference(document.documentElement.getAttribute('data-theme-preference') || THEME_FALLBACK_PREFERENCE);
                    themeSelect.value = activeTheme;
                }
            }
        }

        function toggleAvatarPanel(forceOpen) {
            var panel = document.getElementById('headerAvatarPanel');
            if (!panel) return;
            const shouldOpen = typeof forceOpen === 'boolean' ? forceOpen : !panel.classList.contains('open');
            if (operatorAvatarPanelAdapter && typeof operatorAvatarPanelAdapter.syncState === 'function') {
                operatorAvatarPanelAdapter.syncState(shouldOpen, shouldOpen ? 'toggle-open' : 'toggle-close');
                return;
            }
            setAvatarPanelOpen(shouldOpen);
        }

        function normalizeSearchText(value) {
            const raw = (value || '').toString().toLowerCase();
            const normalized = raw.normalize('NFKD').replace(/[\u0300-\u036f]/g, '');
            return normalized
                .replace(/[^\p{L}\p{N}]+/gu, ' ')
                .replace(/\s+/g, ' ')
                .trim();
        }

        function getOperatorCurrentRoute() {
            return window.location.pathname + window.location.search;
        }

        function mergeQueryParamsIntoRoute(route, params) {
            const routeValue = String(route || '').trim();
            if (!routeValue) {
                return routeValue;
            }

            try {
                const url = new URL(routeValue, window.location.origin);
                Object.keys(params || {}).forEach((key) => {
                    const rawValue = params[key];
                    const value = String(rawValue || '').trim();
                    if (!value) {
                        return;
                    }
                    url.searchParams.set(key, value);
                });
                return url.pathname + (url.search || '') + (url.hash || '');
            } catch (_) {
                return routeValue;
            }
        }

        function promptManualScanValue() {
            const manual = window.prompt(OPERATOR_I18N.scan_prompt_manual || 'Enter scanned value');
            const value = (manual || '').trim();
            if (value === '') {
                return { ok: false, reason: 'manual-cancelled' };
            }
            return { ok: true, value };
        }

        let jsQrLoaderPromise = null;
        function loadJsQrDecoder() {
            if (typeof window.jsQR === 'function') {
                return Promise.resolve(window.jsQR);
            }
            if (jsQrLoaderPromise) {
                return jsQrLoaderPromise;
            }

            jsQrLoaderPromise = new Promise((resolve, reject) => {
                const script = document.createElement('script');
                script.src = 'https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.min.js';
                script.async = true;
                script.onload = () => {
                    if (typeof window.jsQR === 'function') {
                        resolve(window.jsQR);
                        return;
                    }
                    reject(new Error('jsQR not available'));
                };
                script.onerror = () => reject(new Error('Unable to load jsQR'));
                document.head.appendChild(script);
            });

            return jsQrLoaderPromise;
        }

        function createOperatorLaunchCameraScan() {
            return new Promise((resolve) => {
                if (!navigator.mediaDevices || typeof navigator.mediaDevices.getUserMedia !== 'function') {
                    resolve(promptManualScanValue());
                    return;
                }

                const cameraScanAdapterFactory = window.OdareHubOS
                    && window.OdareHubOS.ShellOverlayAdapters
                    && window.OdareHubOS.ShellOverlayAdapters.createCameraScanOverlayAdapter;
                const cameraScanAdapter = typeof cameraScanAdapterFactory === 'function'
                    ? cameraScanAdapterFactory({
                        sourceSurface: 'operator',
                        surfaceClass: 'camera-scan-overlay',
                        onControllerClose: () => close({ ok: false, reason: 'outside-click' }),
                    })
                    : null;
                if (cameraScanAdapter && typeof cameraScanAdapter.open === 'function') {
                    cameraScanAdapter.open('launch');
                }

                const overlay = document.createElement('div');
                overlay.className = 'camera-scan-overlay';

                const panel = document.createElement('div');
                panel.className = 'camera-scan-panel';

                const title = document.createElement('div');
                title.textContent = OPERATOR_I18N.scan_panel_title || 'Scan code';
                title.className = 'camera-scan-title';

                const status = document.createElement('div');
                status.textContent = OPERATOR_I18N.scan_starting_camera || 'Starting camera...';
                status.className = 'camera-scan-status';

                const videoWrap = document.createElement('div');
                videoWrap.className = 'camera-scan-video-wrap';

                const video = document.createElement('video');
                video.setAttribute('playsinline', 'true');
                video.autoplay = true;
                video.muted = true;
                video.className = 'camera-scan-video';

                const actionRow = document.createElement('div');
                actionRow.className = 'camera-scan-actions';

                const manualBtn = document.createElement('button');
                manualBtn.type = 'button';
                manualBtn.textContent = OPERATOR_I18N.scan_enter_value || 'Enter value';
                manualBtn.className = 'camera-scan-btn camera-scan-btn-manual';

                const cancelBtn = document.createElement('button');
                cancelBtn.type = 'button';
                cancelBtn.textContent = OPERATOR_I18N.cancel || 'Cancel';
                cancelBtn.className = 'camera-scan-btn camera-scan-btn-cancel';

                actionRow.appendChild(manualBtn);
                actionRow.appendChild(cancelBtn);
                videoWrap.appendChild(video);
                panel.appendChild(title);
                panel.appendChild(status);
                panel.appendChild(videoWrap);
                panel.appendChild(actionRow);
                overlay.appendChild(panel);
                document.body.appendChild(overlay);

                const canvas = document.createElement('canvas');
                let stream = null;
                let rafId = null;
                let closed = false;

                const close = (result) => {
                    if (closed) {
                        return;
                    }
                    closed = true;
                    if (rafId) {
                        window.cancelAnimationFrame(rafId);
                    }
                    if (stream) {
                        stream.getTracks().forEach((track) => track.stop());
                    }
                    overlay.remove();
                    if (cameraScanAdapter && typeof cameraScanAdapter.close === 'function') {
                        cameraScanAdapter.close(result && result.reason ? result.reason : 'cleanup');
                    }
                    resolve(result || { ok: false, reason: 'cancelled' });
                };

                cancelBtn.addEventListener('click', () => close({ ok: false, reason: 'cancelled' }));
                manualBtn.addEventListener('click', () => close(promptManualScanValue()));
                const tryJsQrLoop = () => {
                    loadJsQrDecoder()
                        .then((jsQrFn) => {
                            const scanFrame = () => {
                                if (closed) {
                                    return;
                                }

                                if (!video.videoWidth || !video.videoHeight) {
                                    rafId = window.requestAnimationFrame(scanFrame);
                                    return;
                                }

                                canvas.width = video.videoWidth;
                                canvas.height = video.videoHeight;
                                const ctx = canvas.getContext('2d');
                                if (!ctx) {
                                    rafId = window.requestAnimationFrame(scanFrame);
                                    return;
                                }

                                ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
                                const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
                                const code = jsQrFn(imageData.data, imageData.width, imageData.height, { inversionAttempts: 'attemptBoth' });

                                if (code && code.data) {
                                    close({ ok: true, value: String(code.data) });
                                    return;
                                }

                                rafId = window.requestAnimationFrame(scanFrame);
                            };

                            status.textContent = OPERATOR_I18N.scan_scanning || 'Scanning...';
                            rafId = window.requestAnimationFrame(scanFrame);
                        })
                        .catch(() => {
                            status.textContent = OPERATOR_I18N.scan_unavailable || 'Scanner unavailable';
                        });
                };

                navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' }, audio: false })
                    .then((mediaStream) => {
                        stream = mediaStream;
                        video.srcObject = mediaStream;
                        return video.play().catch(() => {});
                    })
                    .then(() => {
                        status.textContent = OPERATOR_I18N.scan_scanning || 'Scanning...';
                        if (typeof window.BarcodeDetector === 'function') {
                            const detector = new window.BarcodeDetector({ formats: ['qr_code', 'code_128', 'code_39', 'ean_13', 'ean_8', 'upc_a', 'upc_e'] });
                            const scanFrame = () => {
                                if (closed) {
                                    return;
                                }

                                detector.detect(video)
                                    .then((codes) => {
                                        if (codes && codes.length > 0) {
                                            const rawValue = String(codes[0].rawValue || '').trim();
                                            if (rawValue !== '') {
                                                close({ ok: true, value: rawValue });
                                                return;
                                            }
                                        }
                                        rafId = window.requestAnimationFrame(scanFrame);
                                    })
                                    .catch(() => {
                                        tryJsQrLoop();
                                    });
                            };
                            rafId = window.requestAnimationFrame(scanFrame);
                            return;
                        }

                        tryJsQrLoop();
                    })
                    .catch(() => {
                        close(promptManualScanValue());
                    });
            });
        }

        if (typeof window.launchCameraScan !== 'function') {
            window.launchCameraScan = createOperatorLaunchCameraScan;
        }

        function hideOperatorSearchResults() {
            if (!operatorSearchResults) {
                return;
            }
            operatorSearchRequestSequence += 1;
            operatorSearchResults.hidden = true;
            operatorSearchResults.innerHTML = '';
            operatorSearchResultRows = [];
            operatorSearchActiveIndex = -1;
            if (headerRouteSearch) {
                headerRouteSearch.removeAttribute('aria-activedescendant');
            }
            if (operatorSearchResultsAdapter && typeof operatorSearchResultsAdapter.syncState === 'function') {
                operatorSearchResultsAdapter.syncState('hidden', 'hide');
            }
        }

        function escapeOperatorSearchValue(value) {
            return String(value || '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function renderOperatorSearchResponse(data) {
            if (!operatorSearchResults) {
                return [];
            }

            const rows = [];
            const groups = data && Array.isArray(data.groups) ? data.groups : [];
            groups.forEach((group) => {
                if (!group || !Array.isArray(group.items)) {
                    return;
                }
                group.items.forEach((item) => {
                    if (!item || typeof item !== 'object') {
                        return;
                    }
                    rows.push({
                        kind: String(item.kind || item.type || group.type || 'result'),
                        route: String(item.url || item.route || ''),
                        label: String(item.label || item.url || ''),
                        meta: String(item.meta || item.url || ''),
                        entityType: String(item.entity_type || ''),
                        entityId: String(item.entity_id || item.id || ''),
                    });
                });
            });

            if (rows.length === 0) {
                operatorSearchResults.innerHTML = '<div class="operator-search-empty">' + String(OPERATOR_I18N.search_no_match || OPERATOR_I18N.search_no_route || '') + '</div>';
                operatorSearchResults.hidden = false;
                operatorSearchResultRows = [];
                operatorSearchActiveIndex = -1;
                if (operatorSearchResultsAdapter && typeof operatorSearchResultsAdapter.syncState === 'function') {
                    operatorSearchResultsAdapter.syncState('empty', 'empty');
                }
                return [];
            }

            operatorSearchResults.innerHTML = rows.map((item, idx) => {
                const typeText = item.kind === 'route'
                    ? String(OPERATOR_I18N.search_result_route || 'Route')
                    : (item.kind === 'entity'
                        ? String(OPERATOR_I18N.search_result_entity || 'Entity')
                        : String(OPERATOR_I18N.search_result_content || 'Content'));
                return [
                    '<button type="button" class="operator-search-row" data-search-row-index="', idx, '" data-search-kind="', escapeOperatorSearchValue(item.kind), '" aria-label="',
                    escapeOperatorSearchValue(item.label), '">',
                    '<span class="operator-search-row-head">',
                    '<span class="operator-search-row-label">', escapeOperatorSearchValue(item.label), '</span>',
                    '<span class="operator-search-row-type">', escapeOperatorSearchValue(typeText), '</span>',
                    '</span>',
                    '<span class="operator-search-row-meta">', escapeOperatorSearchValue(item.meta), '</span>',
                    '</button>'
                ].join('');
            }).join('');

            operatorSearchResults.hidden = false;
            operatorSearchResultRows = rows;
            operatorSearchActiveIndex = -1;
            if (operatorSearchResultsAdapter && typeof operatorSearchResultsAdapter.syncState === 'function') {
                operatorSearchResultsAdapter.syncState('results', 'results');
            }
            return operatorSearchResultRows;
        }

        function renderOperatorSearchResults(rawQuery) {
            const query = String(rawQuery || '').trim();
            if (!operatorSearchResults || query.length < 2 || !OPERATOR_SEARCH_SCOPE) {
                hideOperatorSearchResults();
                return Promise.resolve([]);
            }

            const requestSequence = ++operatorSearchRequestSequence;
            const url = '/api/search?q=' + encodeURIComponent(query) + '&scope=' + encodeURIComponent(OPERATOR_SEARCH_SCOPE);
            return fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
                .then((response) => {
                    if (!response.ok) {
                        throw new Error('Search failed');
                    }
                    return response.json();
                })
                .then((data) => {
                    if (requestSequence !== operatorSearchRequestSequence) {
                        return [];
                    }
                    return renderOperatorSearchResponse(data);
                })
                .catch(() => {
                    if (requestSequence !== operatorSearchRequestSequence) {
                        return [];
                    }
                    return renderOperatorSearchResponse({ groups: [] });
                });
        }

        function syncOperatorSearchActiveRow() {
            if (!operatorSearchResults) {
                return;
            }
            const rowNodes = Array.from(operatorSearchResults.querySelectorAll('[data-search-row-index]'));
            rowNodes.forEach((row, idx) => {
                const isActive = idx === operatorSearchActiveIndex;
                row.classList.toggle('is-active', isActive);
                if (isActive) {
                    row.scrollIntoView({ block: 'nearest' });
                    if (headerRouteSearch) {
                        if (!row.id) {
                            row.id = 'operatorSearchRow' + String(idx + 1);
                        }
                        headerRouteSearch.setAttribute('aria-activedescendant', row.id);
                    }
                }
            });
        }

        function openOperatorSearchCandidate(candidate) {
            if (!candidate) {
                return;
            }

            if (candidate.kind === 'entity' && candidate.route) {
                const targetRoute = mergeQueryParamsIntoRoute(candidate.route, {
                    part_id: candidate.entityType === 'part' ? candidate.entityId : '',
                });
                window.location.assign(targetRoute || candidate.route);
                return;
            }

            if (candidate.kind === 'route') {
                window.location.assign(candidate.route);
                return;
            }

            if (candidate.route && candidate.route !== getOperatorCurrentRoute()) {
                window.location.assign(candidate.route);
                return;
            }

            if (candidate.elementId) {
                const target = document.getElementById(candidate.elementId);
                if (target) {
                    target.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    target.classList.add('operator-search-hit');
                    window.setTimeout(() => target.classList.remove('operator-search-hit'), 1200);
                }
            }
            hideOperatorSearchResults();
        }

        function tryLocatePartFromQuery() {
            const params = new URLSearchParams(window.location.search || '');
            const partId = normalizeSearchText(params.get('find_part_id') || '');
            const partQuery = normalizeSearchText(params.get('find_part_q') || '');
            const partNumber = normalizeSearchText(params.get('find_part_number') || '');

            if (!partId && !partQuery && !partNumber) {
                return;
            }

            const rows = Array.from(document.querySelectorAll('[data-part-search]'));
            if (!rows.length) {
                return;
            }

            const normalizedAttr = (row, key) => normalizeSearchText(row.getAttribute(key) || '');
            const normalizedSearch = (row) => normalizeSearchText(row.getAttribute('data-part-search') || row.textContent || '');

            let target = null;
            if (partId) {
                target = rows.find((row) => normalizedAttr(row, 'data-part-id') === partId) || null;
            }
            if (!target && partNumber) {
                target = rows.find((row) => normalizedAttr(row, 'data-part-number') === partNumber) || null;
            }
            if (!target && partQuery) {
                target = rows.find((row) => normalizedSearch(row).includes(partQuery)) || null;
            }

            if (!target) {
                return;
            }

            target.scrollIntoView({ behavior: 'smooth', block: 'center' });
            target.classList.add('operator-search-hit');
            window.setTimeout(() => target.classList.remove('operator-search-hit'), 1400);

            ['find_part_id', 'find_part_q', 'find_part_number'].forEach((key) => params.delete(key));
            const nextQuery = params.toString();
            const nextRoute = window.location.pathname + (nextQuery ? ('?' + nextQuery) : '') + (window.location.hash || '');
            const currentRoute = window.location.pathname + window.location.search + (window.location.hash || '');
            if (nextRoute !== currentRoute) {
                window.history.replaceState({}, document.title, nextRoute);
            }
        }

        function submitOperatorRouteSearch() {
            const query = headerRouteSearch ? headerRouteSearch.value : '';
            if (operatorSearchResultRows.length > 0) {
                openOperatorSearchCandidate(operatorSearchResultRows[0]);
                return;
            }
            renderOperatorSearchResults(query).then((rows) => {
                if (rows.length > 0) {
                    openOperatorSearchCandidate(rows[0]);
                    return;
                }
                if (headerRouteSearch) {
                    headerRouteSearch.setCustomValidity(OPERATOR_I18N.search_no_match || OPERATOR_I18N.search_no_route || 'No matching route found');
                    headerRouteSearch.reportValidity();
                    window.setTimeout(() => headerRouteSearch.setCustomValidity(''), 1200);
                }
            });
        }

        function openOriginalScan() {
            const scanFn = typeof window.launchCameraScan === 'function'
                ? window.launchCameraScan
                : null;

            if (!scanFn) {
                if (headerRouteSearch) {
                    headerRouteSearch.setCustomValidity(OPERATOR_I18N.scan_function_unavailable || 'Scan function is unavailable on this surface');
                    headerRouteSearch.reportValidity();
                    window.setTimeout(() => headerRouteSearch.setCustomValidity(''), 1400);
                }
                return;
            }

            scanFn()
                .then((result) => {
                    if (!result || !result.ok || !result.value || !headerRouteSearch) {
                        return;
                    }
                    headerRouteSearch.value = String(result.value);
                    renderOperatorSearchResults(headerRouteSearch.value);
                })
                .catch(() => {
                    // Keep scan failures non-blocking for operator surface interactions.
                });
        }

        function hydrateOperatorBars(root) {
            const host = root || document;
            host.querySelectorAll('.overview-bar-fill[data-bar-width], .critical-focus-bar-fill[data-bar-width], .recent-focus-bar-fill[data-bar-width]').forEach((bar) => {
                const raw = Number(bar.getAttribute('data-bar-width') || 0);
                const width = Math.max(0, Math.min(100, raw));
                bar.style.width = width + '%';
            });
            host.querySelectorAll('.daily-order-bar[data-bar-height]').forEach((bar) => {
                const raw = Number(bar.getAttribute('data-bar-height') || 0);
                const height = Math.max(0, Math.min(100, raw));
                bar.style.height = height + '%';
            });
        }

        function renderDailyOrderChart() {
            const chartRoot = document.getElementById('dailyOrderBars');
            const yAxisRoot = document.getElementById('dailyOrderYAxis');
            const legendRoot = document.getElementById('dailyOrderLegend');
            const modelSelect = document.getElementById('dailyOrderModelFilter');
            const filterSelect = document.getElementById('dailyOrderProductFilter');
            const fromDateInput = document.getElementById('dailyOrderFromDate');
            const toDateInput = document.getElementById('dailyOrderToDate');

            if (!chartRoot || !yAxisRoot || !legendRoot || !modelSelect || !filterSelect || !fromDateInput || !toDateInput) {
                return;
            }

            const dates = Array.isArray(DAILY_ORDER_CHART.dates) ? DAILY_ORDER_CHART.dates : [];
            const products = Array.isArray(DAILY_ORDER_CHART.products) ? DAILY_ORDER_CHART.products : [];
            if (!dates.length || !products.length) {
                yAxisRoot.innerHTML = '';
                chartRoot.style.setProperty('--date-count', '1');
                chartRoot.classList.remove('is-single');
                chartRoot.innerHTML = '<div class="daily-order-empty">' + String(DAILY_ORDER_CHART.empty_label || OPERATOR_I18N.search_no_route || '') + '</div>';
                legendRoot.innerHTML = '';
                return;
            }

            const minDate = String(dates[0] || '');
            const maxDate = String(dates[dates.length - 1] || '');
            fromDateInput.min = minDate;
            fromDateInput.max = maxDate;
            toDateInput.min = minDate;
            toDateInput.max = maxDate;

            if (!fromDateInput.value) {
                fromDateInput.value = minDate;
            }
            if (!toDateInput.value) {
                toDateInput.value = maxDate;
            }

            let rangeFrom = String(fromDateInput.value || minDate);
            let rangeTo = String(toDateInput.value || maxDate);
            if (rangeFrom > rangeTo) {
                const swap = rangeFrom;
                rangeFrom = rangeTo;
                rangeTo = swap;
                fromDateInput.value = rangeFrom;
                toDateInput.value = rangeTo;
            }

            const visibleDateIndex = [];
            dates.forEach((dateLabel, idx) => {
                if (dateLabel >= rangeFrom && dateLabel <= rangeTo) {
                    visibleDateIndex.push(idx);
                }
            });

            if (!visibleDateIndex.length) {
                yAxisRoot.innerHTML = '';
                chartRoot.style.setProperty('--date-count', '1');
                chartRoot.classList.remove('is-single', 'is-total');
                chartRoot.innerHTML = '<div class="daily-order-empty">' + String(DAILY_ORDER_CHART.empty_label || OPERATOR_I18N.search_no_route || '') + '</div>';
                legendRoot.innerHTML = '';
                return;
            }

            const visibleDates = visibleDateIndex.map((idx) => String(dates[idx] || ''));

            const selectedModelKey = String(modelSelect.value || 'all');
            const modelScopedProducts = selectedModelKey === 'all'
                ? products
                : products.filter((product) => String(product.model_key || '') === selectedModelKey);

            const selectedKey = String(filterSelect.value || 'all');
            const showTotalOnly = selectedKey === 'all';
            let activeProducts = showTotalOnly
                ? modelScopedProducts
                : modelScopedProducts.filter((product) => String(product.key || '') === selectedKey);

            const previousSelection = selectedKey;
            const productOptions = ['<option value="all">' + String(DAILY_ORDER_CHART.all_label || 'All') + '</option>']
                .concat(modelScopedProducts.map((product) => {
                    const optionKey = String(product.key || '');
                    const selectedAttr = optionKey === previousSelection ? ' selected' : '';
                    return '<option value="' + optionKey.replace(/"/g, '&quot;') + '"' + selectedAttr + '>' + String(product.label || '').replace(/</g, '&lt;').replace(/>/g, '&gt;') + '</option>';
                }));
            filterSelect.innerHTML = productOptions.join('');

            if (previousSelection !== 'all' && !modelScopedProducts.some((product) => String(product.key || '') === previousSelection)) {
                filterSelect.value = 'all';
            }
            const effectiveSelectedKey = String(filterSelect.value || 'all');
            const effectiveShowTotalOnly = effectiveSelectedKey === 'all';
            activeProducts = effectiveShowTotalOnly
                ? modelScopedProducts
                : modelScopedProducts.filter((product) => String(product.key || '') === effectiveSelectedKey);

            if (!activeProducts.length) {
                if (modelScopedProducts.length) {
                    activeProducts = [modelScopedProducts[0]];
                } else if (products.length) {
                    activeProducts = [products[0]];
                }
            }

            const totalSeries = visibleDateIndex.map((dateIndex) => {
                let total = 0;
                modelScopedProducts.forEach((product) => {
                    const series = Array.isArray(product.series) ? product.series : [];
                    total += Number(series[dateIndex] || 0);
                });
                return total;
            });

            let maxQty = 0;
            if (effectiveShowTotalOnly) {
                totalSeries.forEach((qty) => {
                    const value = Number(qty || 0);
                    if (value > maxQty) {
                        maxQty = value;
                    }
                });
            } else {
                activeProducts.forEach((product) => {
                    const series = Array.isArray(product.series) ? product.series : [];
                    series.forEach((qty) => {
                        const value = Number(qty || 0);
                        if (value > maxQty) {
                            maxQty = value;
                        }
                    });
                });
            }
            if (maxQty <= 0) {
                maxQty = 1;
            }

            const tickCount = 4;
            const yTicks = [];
            for (let tick = tickCount; tick >= 0; tick -= 1) {
                const value = Math.round((maxQty * tick) / tickCount);
                yTicks.push('<span class="daily-order-y-tick">' + value.toLocaleString() + '</span>');
            }
            yAxisRoot.innerHTML = yTicks.join('');

            chartRoot.style.setProperty('--date-count', String(visibleDates.length));
            chartRoot.classList.toggle('is-single', !effectiveShowTotalOnly && activeProducts.length === 1);
            chartRoot.classList.toggle('is-total', effectiveShowTotalOnly);

            const qtyLabel = String(DAILY_ORDER_CHART.qty_label || 'Qty');
            const groupHtml = visibleDates.map((dateLabel, visibleIndex) => {
                const dateIndex = visibleDateIndex[visibleIndex];
                const barsHtml = effectiveShowTotalOnly
                    ? (() => {
                        const qty = Number(totalSeries[visibleIndex] || 0);
                        const height = Math.max(2, Math.round((qty / maxQty) * 100));
                        const title = dateLabel + ' | ' + String(DAILY_ORDER_CHART.all_label || 'All') + ' | ' + qtyLabel + ': ' + qty.toLocaleString();
                        return '<div class="daily-order-bar c0" data-bar-height="' + height + '" title="' + title.replace(/"/g, '&quot;') + '"></div>';
                    })()
                    : activeProducts.map((product) => {
                        const series = Array.isArray(product.series) ? product.series : [];
                        const qty = Number(series[dateIndex] || 0);
                        const height = Math.max(2, Math.round((qty / maxQty) * 100));
                        const colorIndex = Number(product.color_index || 0) % 4;
                        const productLabel = String(product.label || '');
                        const title = dateLabel + ' | ' + productLabel + ' | ' + qtyLabel + ': ' + qty.toLocaleString();
                        return '<div class="daily-order-bar c' + colorIndex + '" data-bar-height="' + height + '" title="' + title.replace(/"/g, '&quot;') + '"></div>';
                    }).join('');
                return [
                    '<div class="daily-order-date-group">',
                    '<div class="daily-order-bar-group">',
                    barsHtml,
                    '</div>',
                    '<div class="daily-order-date-label" title="' + String(dateLabel).replace(/"/g, '&quot;') + '">' + String(dateLabel) + '</div>',
                    '</div>'
                ].join('');
            }).join('');

            chartRoot.innerHTML = groupHtml;
            hydrateOperatorBars(chartRoot);

            const legendHtml = effectiveShowTotalOnly
                ? '<span class="daily-order-legend-item"><span class="daily-order-legend-dot daily-order-bar c0"></span>' + String(DAILY_ORDER_CHART.all_label || 'All') + '</span>'
                : activeProducts.map((product) => {
                    const colorIndex = Number(product.color_index || 0) % 4;
                    const label = String(product.label || '');
                    return '<span class="daily-order-legend-item"><span class="daily-order-legend-dot daily-order-bar c' + colorIndex + '"></span>' + label + '</span>';
                }).join('');
            legendRoot.innerHTML = legendHtml;
        }

        function isDesktop() {
            return window.innerWidth > DESKTOP_BREAKPOINT;
        }

        function setDesktopCollapsed(collapsed) {
            appShell.classList.toggle('sidebar-collapsed', collapsed);
            appShell.classList.remove('sidebar-peek');
            hamburgerToggle.classList.toggle('active', collapsed);
            try {
                localStorage.setItem(DESKTOP_SIDEBAR_STATE_KEY, collapsed ? '1' : '0');
            } catch (error) {
                // Ignore storage errors and continue with in-memory state.
            }
        }

        function getStoredDesktopCollapsed() {
            try {
                const saved = localStorage.getItem(DESKTOP_SIDEBAR_STATE_KEY);
                if (saved === '1') return true;
                if (saved === '0') return false;
            } catch (error) {
                // Ignore storage errors and use default behavior.
            }

            // Default desktop behavior: start folded with icon rail.
            return true;
        }

        function syncLayoutForViewport() {
            if (isDesktop()) {
                // Desktop must never keep mobile drawer state.
                if (operatorHamburgerDrawerAdapter && typeof operatorHamburgerDrawerAdapter.syncState === 'function') {
                    operatorHamburgerDrawerAdapter.syncState(false, 'viewport-desktop');
                } else {
                    setHamburgerMenuOpen(false);
                }
                setDesktopCollapsed(getStoredDesktopCollapsed());
            } else {
                // Mobile should not keep desktop peek state.
                appShell.classList.remove('sidebar-peek');
                hamburgerToggle.classList.toggle('active', hamburgerMenu.classList.contains('open'));
            }
        }

        function setHamburgerMenuOpen(isOpen) {
            hamburgerMenu.classList.toggle('open', isOpen);
            hamburgerBackdrop.classList.toggle('open', isOpen);
            hamburgerToggle.classList.toggle('active', isOpen);
            // Close avatar + action panels when opening hamburger
            if (isOpen) {
                toggleAvatarPanel(false);
                var mobilePanel = document.getElementById('mobileActionPanel');
                var mobileBackdrop = document.getElementById('actionPanelBackdrop');
                var mobileBtn = document.getElementById('mobileActionBtn');
                if (mobilePanel) mobilePanel.classList.remove('open');
                if (mobileBackdrop) mobileBackdrop.classList.remove('open');
                if (mobileBtn) mobileBtn.setAttribute('aria-expanded', 'false');
                if (window.OdareHubOS
                    && window.OdareHubOS.ShellOverlayAdapters
                    && window.OdareHubOS.ShellOverlayAdapters.operatorMobileActionSheetAdapter
                    && typeof window.OdareHubOS.ShellOverlayAdapters.operatorMobileActionSheetAdapter.syncState === 'function') {
                    window.OdareHubOS.ShellOverlayAdapters.operatorMobileActionSheetAdapter.syncState(false, 'peer-opened');
                }
            }
        }

        function toggleHamburgerMenu() {
            if (isDesktop()) {
                const isCollapsed = !appShell.classList.contains('sidebar-collapsed');
                setDesktopCollapsed(isCollapsed);
                return;
            }
            const isOpen = !hamburgerMenu.classList.contains('open');
            if (operatorHamburgerDrawerAdapter && typeof operatorHamburgerDrawerAdapter.syncState === 'function') {
                operatorHamburgerDrawerAdapter.syncState(isOpen, isOpen ? 'trigger-open' : 'trigger-close');
                return;
            }
            setHamburgerMenuOpen(isOpen);
        }

        function closeHamburgerMenu() {
            if (operatorHamburgerDrawerAdapter && typeof operatorHamburgerDrawerAdapter.syncState === 'function') {
                operatorHamburgerDrawerAdapter.syncState(false, 'close');
                return;
            }
            setHamburgerMenuOpen(false);
        }

        hamburgerToggle.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            toggleHamburgerMenu();
        });

        if (headerOriginalScanButton) {
            headerOriginalScanButton.addEventListener('click', (e) => {
                e.preventDefault();
                openOriginalScan();
            });
        }

        if (headerAvatarButton) {
            headerAvatarButton.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                // Close hamburger and action panel when opening avatar
                closeHamburgerMenu();
                var apEl = document.getElementById('mobileActionPanel');
                var apBackdrop = document.getElementById('actionPanelBackdrop');
                var apBtn = document.getElementById('mobileActionBtn');
                if (apEl) apEl.classList.remove('open');
                if (apBackdrop) apBackdrop.classList.remove('open');
                if (apBtn) apBtn.setAttribute('aria-expanded', 'false');
                if (window.OdareHubOS
                    && window.OdareHubOS.ShellOverlayAdapters
                    && window.OdareHubOS.ShellOverlayAdapters.operatorMobileActionSheetAdapter
                    && typeof window.OdareHubOS.ShellOverlayAdapters.operatorMobileActionSheetAdapter.syncState === 'function') {
                    window.OdareHubOS.ShellOverlayAdapters.operatorMobileActionSheetAdapter.syncState(false, 'peer-opened');
                }
                toggleAvatarPanel();
            });
        }

        document.addEventListener('change', (e) => {
            const target = e.target;
            if (!(target instanceof HTMLSelectElement)) {
                return;
            }

            if (target.id === 'operatorLangSelect') {
                switchLang(target.value);
                return;
            }

            if (target.id === 'operatorCurrencySelect') {
                switchCurrency(target.value);
                return;
            }

            if (target.id === 'operatorThemeSelect') {
                const nextPreference = applyOperatorThemePreference(target.value);
                saveOperatorThemePreference(nextPreference);
                target.value = normalizeThemePreference(target.value);
                persistOperatorThemePreference(nextPreference);
            }
        });

        if (headerRouteSearch) {
            headerRouteSearch.addEventListener('input', () => {
                clearTimeout(operatorSearchDebounceTimer);
                hideOperatorSearchResults();
                operatorSearchDebounceTimer = window.setTimeout(() => {
                    renderOperatorSearchResults(headerRouteSearch.value || '');
                }, 300);
            });

            headerRouteSearch.addEventListener('focus', () => {
                renderOperatorSearchResults(headerRouteSearch.value || '');
            });

            headerRouteSearch.addEventListener('keydown', (e) => {
                const hasRows = Array.isArray(operatorSearchResultRows) && operatorSearchResultRows.length > 0;
                if (e.key === 'ArrowDown' && hasRows) {
                    e.preventDefault();
                    operatorSearchActiveIndex = operatorSearchActiveIndex < operatorSearchResultRows.length - 1
                        ? operatorSearchActiveIndex + 1
                        : 0;
                    syncOperatorSearchActiveRow();
                    return;
                }

                if (e.key === 'ArrowUp' && hasRows) {
                    e.preventDefault();
                    operatorSearchActiveIndex = operatorSearchActiveIndex > 0
                        ? operatorSearchActiveIndex - 1
                        : operatorSearchResultRows.length - 1;
                    syncOperatorSearchActiveRow();
                    return;
                }

                if (e.key === 'Enter') {
                    e.preventDefault();
                    if (hasRows && operatorSearchActiveIndex >= 0 && operatorSearchResultRows[operatorSearchActiveIndex]) {
                        openOperatorSearchCandidate(operatorSearchResultRows[operatorSearchActiveIndex]);
                        return;
                    }
                    submitOperatorRouteSearch();
                }

                if (e.key === 'Escape') {
                    hideOperatorSearchResults();
                }
            });
        }

        if (operatorSearchResults) {
            operatorSearchResults.addEventListener('click', (event) => {
                const row = event.target.closest('[data-search-row-index]');
                if (!row) {
                    return;
                }
                const index = Number(row.getAttribute('data-search-row-index'));
                if (!Number.isInteger(index) || index < 0 || index >= operatorSearchResultRows.length) {
                    return;
                }
                openOperatorSearchCandidate(operatorSearchResultRows[index]);
            });
        }

        document.querySelectorAll('[data-part-detail-url]').forEach((row) => {
            row.addEventListener('click', (event) => {
                const target = event.target;
                if (target && target.closest('a,button,input,select,textarea,label')) {
                    return;
                }
                const route = String(row.getAttribute('data-part-detail-url') || '').trim();
                if (route) {
                    window.location.assign(route);
                }
            });

            row.addEventListener('keydown', (event) => {
                if (event.key !== 'Enter') {
                    return;
                }
                event.preventDefault();
                const route = String(row.getAttribute('data-part-detail-url') || '').trim();
                if (route) {
                    window.location.assign(route);
                }
            });
        });

        document.querySelectorAll('[data-row-href]').forEach((row) => {
            row.addEventListener('click', (event) => {
                const target = event.target;
                if (target && target.closest('a,button,input,select,textarea,label')) {
                    return;
                }
                const route = String(row.getAttribute('data-row-href') || '').trim();
                if (route) {
                    window.location.assign(route);
                }
            });

            row.addEventListener('keydown', (event) => {
                if (event.key !== 'Enter') {
                    return;
                }
                event.preventDefault();
                const route = String(row.getAttribute('data-row-href') || '').trim();
                if (route) {
                    window.location.assign(route);
                }
            });
        });

        const dailyOrderFilter = document.getElementById('dailyOrderProductFilter');
        if (dailyOrderFilter) {
            dailyOrderFilter.addEventListener('change', renderDailyOrderChart);
        }

        const dailyOrderModelFilter = document.getElementById('dailyOrderModelFilter');
        if (dailyOrderModelFilter) {
            dailyOrderModelFilter.addEventListener('change', renderDailyOrderChart);
        }

        const dailyOrderFromDate = document.getElementById('dailyOrderFromDate');
        if (dailyOrderFromDate) {
            dailyOrderFromDate.addEventListener('change', renderDailyOrderChart);
        }

        const dailyOrderToDate = document.getElementById('dailyOrderToDate');
        if (dailyOrderToDate) {
            dailyOrderToDate.addEventListener('change', renderDailyOrderChart);
        }

        // Desktop hover-open behavior when collapsed: icon rail expands sidebar on mouseover
        function isSidebarRailHoverIntent(event) {
            if (!isDesktop() || !appShell.classList.contains('sidebar-collapsed')) {
                return false;
            }

            const sidebarRect = contextualSidebar.getBoundingClientRect();
            const railWidth = 72;
            const maxRailX = sidebarRect.left + Math.min(sidebarRect.width, railWidth);
            return event.clientX >= sidebarRect.left && event.clientX <= maxRailX;
        }

        contextualSidebar.addEventListener('mouseenter', (event) => {
            if (isSidebarRailHoverIntent(event)) {
                appShell.classList.add('sidebar-peek');
            }
        });

        contextualSidebar.addEventListener('mouseleave', () => {
            if (isDesktop()) {
                appShell.classList.remove('sidebar-peek');
            }
        });

        // Close desktop sidebar by clicking in content area.
        mainContent.addEventListener('click', (e) => {
            if (isDesktop() && !appShell.classList.contains('sidebar-collapsed')) {
                const sidebar = document.querySelector('.app-sidebar');
                if (!sidebar.contains(e.target)) {
                    setDesktopCollapsed(true);
                }
            }
        });

        // Close menu when clicking menu items
        document.querySelectorAll('.menu-item').forEach(item => {
            item.addEventListener('click', closeHamburgerMenu);
        });

        window.addEventListener('resize', syncLayoutForViewport);

        document.querySelectorAll('[data-processing-toggle], [data-panel-toggle]').forEach((button) => {
            button.addEventListener('click', () => {
                const targetId = String(
                    button.getAttribute('data-processing-toggle')
                    || button.getAttribute('data-panel-toggle')
                    || ''
                ).trim();
                if (!targetId) {
                    return;
                }
                const panel = document.getElementById(targetId);
                if (!panel) {
                    return;
                }

                const isOpen = !panel.hasAttribute('hidden');
                if (isOpen) {
                    panel.setAttribute('hidden', 'hidden');
                    button.setAttribute('aria-expanded', 'false');
                } else {
                    panel.removeAttribute('hidden');
                    button.setAttribute('aria-expanded', 'true');
                }
            });
        });

        const preparationForm = document.getElementById('preparationReadyForm');
        if (preparationForm) {
            const dispatchDateInput = document.getElementById('preparationDispatchDate');
            const destinationInput = document.getElementById('preparationDestination');
            const partsRowsHost = document.getElementById('preparationPartsRows');
            const partsHint = document.getElementById('preparationPartsHint');
            const readySaveButton = document.getElementById('preparationReadySaveButton');
            const lineInputHolder = document.getElementById('preparationLineInputHolder');
            const fallbackProductId = document.getElementById('preparationFallbackProductId');
            const fallbackQty = document.getElementById('preparationFallbackQty');
            const driverInput = document.getElementById('preparationDriverName');
            const truckInput = document.getElementById('preparationTruckNo');
            const datasetNode = document.getElementById('preparationFlowDataset');

            let flowData = {
                ready_index: {},
                destination_defaults: {},
                default_driver_name: '',
                default_truck_no: '',
                labels: {
                    no_parts: '',
                    select_prompt: '',
                    order_qty: '',
                    bundle_qty: '',
                    status_ok: '',
                    status_adjust: '',
                    invalid_qty: '',
                },
            };

            if (datasetNode && datasetNode.textContent) {
                try {
                    const parsed = JSON.parse(datasetNode.textContent);
                    if (parsed && typeof parsed === 'object') {
                        flowData = parsed;
                    }
                } catch (error) {
                    // Keep resilient defaults when dataset JSON is invalid.
                }
            }

            const labels = (flowData && flowData.labels) || {};
            let destinationDefaultMap = {};
            Object.keys((flowData && flowData.destination_defaults) || {}).forEach((key) => {
                destinationDefaultMap[String(key).trim().toLowerCase()] = flowData.destination_defaults[key] || {};
            });

            const normalizePairKey = (dispatchDate, destination) => {
                return [String(dispatchDate || '').trim().toLowerCase(), String(destination || '').trim().toLowerCase()].join('|');
            };

            const findDestinationDefaults = (destination) => {
                const key = String(destination || '').trim().toLowerCase();
                if (!key) {
                    return null;
                }
                return destinationDefaultMap[key] || null;
            };

            const renderSelectedRows = () => {
                if (!dispatchDateInput || !destinationInput || !partsRowsHost) {
                    return;
                }

                const pairKey = normalizePairKey(dispatchDateInput.value, destinationInput.value);
                const partRows = (flowData.ready_index && flowData.ready_index[pairKey]) || [];
                partsRowsHost.innerHTML = '';

                if (!Array.isArray(partRows) || partRows.length === 0) {
                    if (partsHint) {
                        partsHint.textContent = String(labels.no_parts || '');
                    }
                    if (readySaveButton) {
                        readySaveButton.disabled = true;
                    }
                    return;
                }

                partRows.forEach((partRow, idx) => {
                    const productId = Number(partRow.product_id || 0);
                    const readyQty = Number(partRow.ready_qty || 0);
                    if (!Number.isFinite(productId) || productId <= 0 || !Number.isFinite(readyQty) || readyQty <= 0) {
                        return;
                    }

                    const wrapper = document.createElement('div');
                    wrapper.className = 'u-flex-wrap u-items-center u-gap-8 u-mb-6';
                    wrapper.setAttribute('data-prep-part-row', '1');
                    wrapper.setAttribute('data-product-id', String(productId));
                    wrapper.setAttribute('data-ready-qty', String(readyQty));

                    const checkbox = document.createElement('input');
                    checkbox.type = 'checkbox';
                    checkbox.className = 'daily-order-checkbox';
                    checkbox.checked = true;
                    checkbox.setAttribute('data-prep-part-check', '1');

                    const label = document.createElement('span');
                    const partName = String(partRow.part_name || '').trim();
                    const partNumber = String(partRow.part_number || '').trim();
                    label.textContent = partName + ' (' + (partNumber || '-') + ')';

                    const qtyLabel = document.createElement('span');
                    qtyLabel.className = 'u-muted-compact';
                    qtyLabel.textContent = String(labels.order_qty || '') + ': ' + readyQty.toFixed(2);

                    const qtyInput = document.createElement('input');
                    qtyInput.type = 'number';
                    qtyInput.min = '0.01';
                    qtyInput.step = '0.01';
                    qtyInput.value = readyQty.toFixed(2);
                    qtyInput.className = 'daily-order-date-input';
                    qtyInput.setAttribute('data-prep-part-qty', '1');
                    qtyInput.setAttribute('aria-label', String(labels.bundle_qty || ''));

                    const status = document.createElement('span');
                    status.className = 'u-muted-compact';
                    status.setAttribute('data-prep-part-status', '1');

                    wrapper.appendChild(checkbox);
                    wrapper.appendChild(label);
                    wrapper.appendChild(qtyLabel);
                    wrapper.appendChild(qtyInput);
                    wrapper.appendChild(status);
                    partsRowsHost.appendChild(wrapper);

                    const refreshRow = () => {
                        const qtyValue = Number(qtyInput.value || 0);
                        const isSelected = checkbox.checked;
                        if (!isSelected) {
                            status.textContent = '';
                            qtyInput.disabled = true;
                            return;
                        }

                        qtyInput.disabled = false;
                        if (!Number.isFinite(qtyValue) || qtyValue <= 0) {
                            status.textContent = String(labels.invalid_qty || '');
                            return;
                        }

                        if (Math.abs(qtyValue - readyQty) < 0.0001) {
                            status.textContent = String(labels.status_ok || '');
                        } else {
                            status.textContent = String(labels.status_adjust || '');
                        }
                    };

                    checkbox.addEventListener('change', refreshRow);
                    qtyInput.addEventListener('input', refreshRow);
                    refreshRow();
                });

                const hasRows = partsRowsHost.querySelectorAll('[data-prep-part-row]').length > 0;
                if (partsHint) {
                    partsHint.textContent = hasRows ? '' : labels.no_parts;
                }
                if (readySaveButton) {
                    readySaveButton.disabled = !hasRows;
                }
            };

            const applyDriverTruckDefaults = () => {
                const destination = destinationInput ? destinationInput.value : '';
                const defaults = findDestinationDefaults(destination) || {};
                const fallbackDriver = String(flowData.default_driver_name || '');
                const fallbackTruck = String(flowData.default_truck_no || '');

                if (driverInput && !driverInput.dataset.manualTouched) {
                    const defaultDriver = String(defaults.driver_name || fallbackDriver || '');
                    if (defaultDriver) {
                        driverInput.value = defaultDriver;
                    }
                }

                if (truckInput && !truckInput.dataset.manualTouched) {
                    const defaultTruck = String(defaults.truck_no || fallbackTruck || '');
                    if (defaultTruck) {
                        truckInput.value = defaultTruck;
                    }
                }
            };

            if (driverInput) {
                driverInput.addEventListener('input', () => {
                    driverInput.dataset.manualTouched = '1';
                });
            }
            if (truckInput) {
                truckInput.addEventListener('input', () => {
                    truckInput.dataset.manualTouched = '1';
                });
            }

            if (dispatchDateInput) {
                dispatchDateInput.addEventListener('change', () => {
                    renderSelectedRows();
                });
            }
            if (destinationInput) {
                destinationInput.addEventListener('change', () => {
                    renderSelectedRows();
                    applyDriverTruckDefaults();
                });
                destinationInput.addEventListener('blur', () => {
                    renderSelectedRows();
                    applyDriverTruckDefaults();
                });
            }

            preparationForm.addEventListener('submit', (event) => {
                const selectedRows = Array.from(preparationForm.querySelectorAll('[data-prep-part-row]')).filter((row) => {
                    const checkbox = row.querySelector('[data-prep-part-check]');
                    return checkbox && checkbox.checked;
                });

                if (lineInputHolder) {
                    lineInputHolder.innerHTML = '';
                }

                if (selectedRows.length === 0) {
                    event.preventDefault();
                    if (partsHint) {
                        partsHint.textContent = String(labels.select_prompt || '');
                    }
                    return;
                }

                let hasInvalidQty = false;
                selectedRows.forEach((row, idx) => {
                    const productId = Number(row.getAttribute('data-product-id') || 0);
                    const qtyInput = row.querySelector('[data-prep-part-qty]');
                    const qty = Number((qtyInput && qtyInput.value) || 0);

                    if (!Number.isFinite(productId) || productId <= 0 || !Number.isFinite(qty) || qty <= 0) {
                        hasInvalidQty = true;
                        return;
                    }

                    if (idx === 0) {
                        if (fallbackProductId) {
                            fallbackProductId.value = String(productId);
                        }
                        if (fallbackQty) {
                            fallbackQty.value = String(qty);
                        }
                    }

                    if (!lineInputHolder) {
                        return;
                    }

                    const productField = document.createElement('input');
                    productField.type = 'hidden';
                    productField.name = 'line_product_ids[]';
                    productField.value = String(productId);

                    const qtyField = document.createElement('input');
                    qtyField.type = 'hidden';
                    qtyField.name = 'line_qtys[]';
                    qtyField.value = String(qty);

                    lineInputHolder.appendChild(productField);
                    lineInputHolder.appendChild(qtyField);
                });

                if (hasInvalidQty) {
                    event.preventDefault();
                    if (partsHint) {
                        partsHint.textContent = String(labels.invalid_qty || '');
                    }
                }
            });

            renderSelectedRows();
            applyDriverTruckDefaults();
        }

        renderDailyOrderChart();
        tryLocatePartFromQuery();
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initOperatorPreferences, { once: true });
        } else {
            initOperatorPreferences();
        }
        hydrateOperatorBars(document);
        syncLayoutForViewport();
    </script>
        <?php
        return ob_get_clean() ?: '';
    }
}

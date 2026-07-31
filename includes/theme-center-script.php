<script>
    // ── Root-relative API URL ── works from any page depth ──
    (function() {
        const parts = window.location.pathname.split('/');
        const smsIdx = parts.findIndex(p => p.toLowerCase() === 'sms');
        if (smsIdx !== -1) {
            window._CLAYON_API_BASE = '/' + parts.slice(1, smsIdx + 1).join('/') + '/api/theme-api.php';
        } else {
            const depth = parts.length - 2;
            window._CLAYON_API_BASE = '../'.repeat(Math.max(0, depth - 1)) + 'api/theme-api.php';
        }
    })();

    function themeApiUrl(action, extra) {
        let url = window._CLAYON_API_BASE + '?action=' + action;
        if (extra) url += '&' + extra;
        return url;
    }

    let currentTheme = '<?php echo htmlspecialchars($_SESSION["CLAYON_THEME"] ?? "default"); ?>';
    let customColors = {};
    let isSystemTheme = false;
    const STORAGE_KEYS = {
        theme: 'clayon_selected_theme',
        system: 'clayon_system_theme',
        colors: 'clayon_custom_colors'
    };

    function getStoredTheme() {
        return localStorage.getItem(STORAGE_KEYS.theme) || null;
    }

    function setStoredTheme(themeName) {
        localStorage.setItem(STORAGE_KEYS.theme, themeName);
    }

    function getStoredColors() {
        try {
            return JSON.parse(localStorage.getItem(STORAGE_KEYS.colors) || '{}');
        } catch (e) {
            return {};
        }
    }

    function setStoredColors(colors) {
        localStorage.setItem(STORAGE_KEYS.colors, JSON.stringify(colors));
    }

    function updateThemeCards() {
        document.querySelectorAll('.theme-card').forEach(card => {
            card.classList.toggle('active', card.dataset.themeId === currentTheme);
        });
    }

    async function initThemeCenter() {
        try {
            const storedTheme = getStoredTheme();
            const storedColors = getStoredColors();
            if (storedTheme && storedTheme !== currentTheme) {
                await switchTheme(storedTheme, null, { silent: true });
            } else {
                await silentApplyThemeCss();
            }

            if (storedColors && Object.keys(storedColors).length) {
                customColors = { ...storedColors };
            } else {
                await loadCurrentColors();
            }

            if (localStorage.getItem(STORAGE_KEYS.system) === 'true') {
                isSystemTheme = true;
                const toggle = document.getElementById('systemThemeToggle');
                if (toggle) toggle.checked = true;
            }
            updateThemeCards();
        } catch (e) {
            console.error('Theme init error:', e);
        }
    }

    async function silentApplyThemeCss() {
        try {
            const res = await fetch(themeApiUrl('css', 't=' + Date.now()));
            const css = await res.text();
            const styleTag = document.getElementById('theme-dynamic-css');
            if (styleTag) styleTag.innerHTML = css;
        } catch (e) {
            /* silent fail */
        }
    }

    async function loadCurrentColors() {
        try {
            const response = await fetch(themeApiUrl('colors'));
            const data = await response.json();
            customColors = { ...(data.colors || {}) };
            populateCustomizer();
        } catch (e) {
            console.error('Error loading colors:', e);
        }
    }

    function populateCustomizer() {
        const container = document.getElementById('colorCustomizer');
        if (!container) return;
        container.innerHTML = '';
        const labels = {
            primary: 'Primary Accent',
            secondary: 'Secondary Accent',
            'bg-main': 'Main Background',
            'card-bg': 'Card Surface',
            'card-hover': 'Card Hover',
            'text-primary': 'Main Text',
            'text-muted': 'Muted Text',
            'text-number': 'Stat Numbers',
            border: 'Border',
            'border-light': 'Light Border',
            'accent-success': 'Success',
            'accent-warning': 'Warning',
            'accent-error': 'Error / Danger',
            'accent-info': 'Info'
        };
        Object.entries(customColors).forEach(([key, value]) => {
            if (!labels[key]) return;
            const group = document.createElement('div');
            group.className = 'color-input-group';
            const hexVal = convertToHex(value);
            group.innerHTML = `
                <label for="color-${key}">
                    <span>${labels[key]}</span>
                    <span style="font-family:monospace;font-size:0.72rem">--${key}</span>
                </label>
                <div class="color-picker-row">
                    <input type="color" id="picker-${key}" value="${hexVal}" oninput="syncColorInput('${key}', this.value)">
                    <input type="text" id="text-${key}" value="${value}" onchange="syncColorInput('${key}', this.value)">
                </div>`;
            container.appendChild(group);
        });
    }

    function syncColorInput(key, value) {
        customColors[key] = value;
        setStoredColors(customColors);
        const hexVal = convertToHex(value);
        const picker = document.getElementById('picker-' + key);
        const text = document.getElementById('text-' + key);
        if (picker) picker.value = hexVal;
        if (text) text.value = value;
        document.documentElement.style.setProperty('--' + key, value);
    }

    function convertToHex(color) {
        if (!color) return '#6366f1';
        if (color.startsWith('#')) {
            return color.length === 4
                ? '#' + color[1] + color[1] + color[2] + color[2] + color[3] + color[3]
                : color.slice(0, 7);
        }
        const rgb = color.match(/\d+/g);
        if (rgb && rgb.length >= 3) {
            return '#' +
                parseInt(rgb[0]).toString(16).padStart(2, '0') +
                parseInt(rgb[1]).toString(16).padStart(2, '0') +
                parseInt(rgb[2]).toString(16).padStart(2, '0');
        }
        return '#6366f1';
    }

    async function loadThemes() {
        try {
            const response = await fetch(themeApiUrl('list'));
            const data = await response.json();
            const container = document.getElementById('themesContainer');
            if (!container) return;
            container.innerHTML = '';
            Object.values(data.themes || {}).forEach(theme => {
                const card = document.createElement('div');
                card.className = 'theme-card';
                card.dataset.themeId = theme.id;
                card.innerHTML = `<div class="theme-card-name">${theme.label}</div><div class="theme-card-desc">${theme.description}</div>`;
                card.onclick = () => switchTheme(theme.id, card);
                container.appendChild(card);
            });
            updateThemeCards();
        } catch (e) {
            console.error('loadThemes error:', e);
        }
    }

    async function switchTheme(themeName, element, options = {}) {
        try {
            const response = await fetch(themeApiUrl('switch', 'theme=' + encodeURIComponent(themeName)), { method: 'POST' });
            if (!response.ok) return false;
            currentTheme = themeName;
            setStoredTheme(themeName);
            if (element) {
                document.querySelectorAll('.theme-card').forEach(card => card.classList.remove('active'));
                element.classList.add('active');
            }
            updateThemeCards();
            await loadCurrentColors();
            await reloadThemeCss();
            if (!options.silent && typeof showToast === 'function') {
                showToast('Theme: ' + themeName.charAt(0).toUpperCase() + themeName.slice(1) + ' applied', 'success');
            }
            return true;
        } catch (e) {
            console.error('switchTheme error:', e);
            return false;
        }
    }

    async function reloadThemeCss() {
        try {
            const res = await fetch(themeApiUrl('css', 't=' + Date.now()));
            const css = await res.text();
            const styleTag = document.getElementById('theme-dynamic-css');
            if (styleTag) styleTag.innerHTML = css;
        } catch (e) {
            /* silent */
        }
    }

    async function resetTheme() {
        try {
            const response = await fetch(themeApiUrl('reset'), { method: 'POST' });
            if (response.ok) {
                currentTheme = 'default';
                setStoredTheme('default');
                await loadCurrentColors();
                await loadThemes();
                await reloadThemeCss();
                if (typeof showToast === 'function') showToast('Theme reset to Default Dark', 'info');
            }
        } catch (e) {
            console.error('resetTheme error:', e);
        }
    }

    async function saveCustomTheme() {
        try {
            const response = await fetch(themeApiUrl('save'), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ colors: customColors })
            });
            const data = await response.json();
            if (data.success) {
                currentTheme = 'custom';
                setStoredTheme('custom');
                setStoredColors(customColors);
                await loadThemes();
                await reloadThemeCss();
                if (typeof showToast === 'function') showToast('Custom palette saved!', 'success');
            }
        } catch (e) {
            console.error('saveCustomTheme error:', e);
        }
    }

    function toggleSystemTheme(enabled) {
        isSystemTheme = enabled;
        localStorage.setItem(STORAGE_KEYS.system, enabled ? 'true' : 'false');
        if (enabled) {
            applySystemThemePreference();
            if (typeof showToast === 'function') showToast('System auto-theme enabled', 'info');
        }
    }

    function applySystemThemePreference() {
        if (!isSystemTheme) return;
        const preferredTheme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        if (preferredTheme !== currentTheme) {
            switchTheme(preferredTheme, null, { silent: true });
        }
    }

    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
        if (isSystemTheme) applySystemThemePreference();
    });

    function resetCustomizerColors() { loadCurrentColors(); }

    function openThemeCenter() {
        const modal = document.getElementById('themeCenter');
        if (modal) {
            modal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }
        loadThemes();
        loadCurrentColors();
    }

    function closeThemeCenter() {
        const modal = document.getElementById('themeCenter');
        if (modal) {
            modal.style.display = 'none';
            document.body.style.overflow = '';
        }
    }

    document.addEventListener('DOMContentLoaded', initThemeCenter);

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') closeThemeCenter();
    });
</script>

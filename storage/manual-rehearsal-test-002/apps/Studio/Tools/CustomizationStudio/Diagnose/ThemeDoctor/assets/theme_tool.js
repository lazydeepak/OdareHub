(function(){
  const root = document.documentElement;
  const previewShell = document.querySelector('[data-theme-preview-shell]');
  if (!previewShell) return;

  const themeField = document.querySelector('[data-theme-preview-theme]');
  const styleField = document.querySelector('[data-theme-preview-style]');
  const accentField = document.querySelector('[data-theme-preview-accent]');
  const bgField = document.querySelector('[data-theme-preview-bg]');
  const textField = document.querySelector('[data-theme-preview-text]');
  const fontField = document.querySelector('[data-theme-preview-font]');
  const themeLabelField = document.querySelector('[data-theme-preview-theme-label]');
  const resetBtn = document.querySelector('[data-theme-preview-reset]');
  const copyBtn = document.querySelector('[data-theme-preview-copy]');
  const copyLabelDefault = copyBtn ? (copyBtn.getAttribute('data-copy-label-default') || 'Copy preview config') : 'Copy preview config';
  const copyLabelSuccess = copyBtn ? (copyBtn.getAttribute('data-copy-label-success') || 'Copied') : 'Copied';
  const copyLabelFailed = copyBtn ? (copyBtn.getAttribute('data-copy-label-failed') || 'Copy failed') : 'Copy failed';
  const previewSelectionNode = document.querySelector('[data-preview-selection]');
  const previewColorSummaryNode = document.querySelector('[data-preview-colors-summary]');

  function humanizeKey(value) {
    const normalized = (value || '').trim().replace(/[_-]+/g, ' ');
    if (!normalized) return 'Theme';
    return normalized.replace(/\b\w/g, m => m.toUpperCase());
  }

  function normalizeHexColor(value, fallback) {
    const input = (value || '').trim();
    if (/^#[0-9a-fA-F]{6}$/.test(input)) return input.toLowerCase();
    if (/^#[0-9a-fA-F]{3}$/.test(input)) {
      return ('#' + input[1] + input[1] + input[2] + input[2] + input[3] + input[3]).toLowerCase();
    }
    const match = input.match(/rgba?\(([^)]+)\)/i);
    if (match) {
      const parts = match[1].split(',').map(p => parseFloat(p.trim()));
      if (parts.length >= 3) {
        const toHex = n => Math.max(0, Math.min(255, Math.round(n))).toString(16).padStart(2, '0');
        return ('#' + toHex(parts[0]) + toHex(parts[1]) + toHex(parts[2])).toLowerCase();
      }
    }
    return fallback;
  }

  const runtimeThemeNode = document.querySelector('[data-runtime-theme]');
  const runtimeThemeInitial = runtimeThemeNode ? (runtimeThemeNode.getAttribute('data-runtime-theme-initial') || '').trim() : '';
  const runtimeTheme = runtimeThemeInitial || root.getAttribute('data-theme') || 'unknown';
  const runtimeStyle = root.getAttribute('data-color-style') || 'base';
  const runtimeMode = root.getAttribute('data-theme-mode') || 'system';

  const runtimeStyleNode = document.querySelector('[data-runtime-style]');
  const runtimeModeNode = document.querySelector('[data-runtime-mode]');
  if (runtimeThemeNode) runtimeThemeNode.textContent = runtimeTheme;
  if (runtimeStyleNode) runtimeStyleNode.textContent = runtimeStyle;
  if (runtimeModeNode) runtimeModeNode.textContent = runtimeMode;

  const runtimeAccent = normalizeHexColor(getComputedStyle(root).getPropertyValue('--accent').trim(), '#77a7ff');
  const runtimeBg = normalizeHexColor(getComputedStyle(root).getPropertyValue('--style-content-bg').trim(), '#0f1a30');
  const runtimeText = normalizeHexColor(getComputedStyle(root).getPropertyValue('--text').trim(), '#e8eefc');
  const runtimeFont = getComputedStyle(root).getPropertyValue('--font-sans').trim() || 'system-ui';

  const initialAccent = accentField ? normalizeHexColor(accentField.value, runtimeAccent) : runtimeAccent;
  const initialBg = bgField ? normalizeHexColor(bgField.value, runtimeBg) : runtimeBg;
  const initialText = textField ? normalizeHexColor(textField.value, runtimeText) : runtimeText;
  const initialFont = fontField && fontField.value.trim() ? fontField.value.trim() : runtimeFont;

  function setPreviewVars(next) {
    if (!previewShell) return;
    if (next.accent) previewShell.style.setProperty('--st-preview-accent', next.accent);
    if (next.bg) previewShell.style.setProperty('--st-preview-bg', next.bg);
    if (next.text) previewShell.style.setProperty('--st-preview-text', next.text);
    if (next.font) previewShell.style.setProperty('--st-preview-font', next.font);
  }

  setPreviewVars({ accent: initialAccent, bg: initialBg, text: initialText, font: initialFont });

  if (accentField) accentField.value = initialAccent;
  if (bgField) bgField.value = initialBg;
  if (textField) textField.value = initialText;
  if (fontField) fontField.value = initialFont;

  function updatePreviewSelectionSummary() {
    if (previewSelectionNode) {
      const themeValue = themeField ? (themeField.value || 'default') : 'default';
      const styleValue = styleField ? (styleField.value || 'base') : 'base';
      previewSelectionNode.textContent = `${themeValue} / ${styleValue}`;
    }
    if (previewColorSummaryNode) {
      const accentValue = accentField ? accentField.value : initialAccent;
      const bgValue = bgField ? bgField.value : initialBg;
      const textValue = textField ? textField.value : initialText;
      const accentLabel = previewColorSummaryNode.getAttribute('data-label-accent') || 'accent';
      const bgLabel = previewColorSummaryNode.getAttribute('data-label-bg') || 'bg';
      const textLabel = previewColorSummaryNode.getAttribute('data-label-text') || 'text';
      previewColorSummaryNode.textContent = `${accentLabel} ${accentValue} · ${bgLabel} ${bgValue} · ${textLabel} ${textValue}`;
    }
  }

  if (accentField) accentField.addEventListener('input', () => { setPreviewVars({ accent: accentField.value }); updatePreviewSelectionSummary(); });
  if (bgField) bgField.addEventListener('input', () => { setPreviewVars({ bg: bgField.value }); updatePreviewSelectionSummary(); });
  if (textField) textField.addEventListener('input', () => { setPreviewVars({ text: textField.value }); updatePreviewSelectionSummary(); });
  if (fontField) fontField.addEventListener('input', () => setPreviewVars({ font: fontField.value }));

  if (themeField) {
    themeField.addEventListener('change', () => {
      previewShell.setAttribute('data-preview-theme', themeField.value || 'default');
      if (themeLabelField && !themeLabelField.value.trim()) {
        themeLabelField.value = humanizeKey(themeField.value || 'default');
      }
      updatePreviewSelectionSummary();
    });
  }

  if (styleField) {
    styleField.addEventListener('change', () => {
      previewShell.setAttribute('data-preview-style', styleField.value || 'base');
      updatePreviewSelectionSummary();
    });
  }

  if (resetBtn) {
    resetBtn.addEventListener('click', () => {
      if (accentField) accentField.value = initialAccent;
      if (bgField) bgField.value = initialBg;
      if (textField) textField.value = initialText;
      if (fontField) fontField.value = initialFont;
      if (themeField) themeField.value = runtimeTheme;
      if (themeLabelField) themeLabelField.value = humanizeKey(runtimeTheme);
      if (styleField) styleField.value = runtimeStyle;
      setPreviewVars({ accent: initialAccent, bg: initialBg, text: initialText, font: initialFont });
      previewShell.setAttribute('data-preview-theme', runtimeTheme);
      previewShell.setAttribute('data-preview-style', runtimeStyle);
      updatePreviewSelectionSummary();
    });
  }

  if (copyBtn) {
    copyBtn.addEventListener('click', async () => {
      const payload = {
        theme: themeField ? (themeField.value || 'default') : 'default',
        style: styleField ? (styleField.value || 'base') : 'base',
        accent: accentField ? accentField.value : initialAccent,
        background: bgField ? bgField.value : initialBg,
        text: textField ? textField.value : initialText,
        font: fontField ? fontField.value : initialFont
      };

      const serialized = JSON.stringify(payload, null, 2);
      let copied = false;
      if (navigator.clipboard && navigator.clipboard.writeText) {
        try {
          await navigator.clipboard.writeText(serialized);
          copied = true;
        } catch (err) {
          copied = false;
        }
      }

      if (!copied) {
        const temp = document.createElement('textarea');
        temp.value = serialized;
        temp.setAttribute('readonly', 'readonly');
        temp.style.position = 'fixed';
        temp.style.top = '-1000px';
        document.body.appendChild(temp);
        temp.select();
        try {
          copied = document.execCommand('copy');
        } catch (err) {
          copied = false;
        }
        document.body.removeChild(temp);
      }

      copyBtn.textContent = copied ? copyLabelSuccess : copyLabelFailed;
      window.setTimeout(() => {
        copyBtn.textContent = copyLabelDefault;
      }, 1300);
    });
  }

  if (themeLabelField && !themeLabelField.value.trim()) {
    const value = themeField ? (themeField.value || runtimeTheme) : runtimeTheme;
    themeLabelField.value = humanizeKey(value);
  }

  updatePreviewSelectionSummary();
})();

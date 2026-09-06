(function(){
  /*
   * Browser smoke checklist (run after any changes to this file):
   *   1. Open /apps/studio/tools/customization-studio/design-system/tokens
   *   2. Select Foundation / Global Defaults (:root::1)
   *   3. Confirm token values load from source snapshot endpoint
   *   4. Change one harmless token and save
   *   5. Confirm save success and source backup path appears in flash
   *   6. Confirm compiled /assets/theme.css reflects the saved token value
   *   7. Confirm summary shows source file and source layer (Foundation/Semantic/Variant)
   *   8. Confirm legacy copy "Writes to theme.css" is not visible
   *   9. Confirm semantic.semantic options are not present in theme selectors
   *  10. Confirm source snapshot warning appears if source snapshot endpoint is unreachable
   */
  const workspace = document.querySelector('[data-cte-workspace]');
  if (!workspace) return;

  function escapeHtml(str) {
    if (typeof str !== 'string') return '';
    return str.replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/'/g, '&#39;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
  }

  const selectorField = document.querySelector('[data-cte-selector]');
  const tokenContainer = document.querySelector('[data-cte-tokens]');
  const testBtn = document.querySelector('[data-cte-test]');
  const verifyBtn = document.querySelector('[data-cte-verify]');
  const saveBtn = document.querySelector('[data-cte-save]');
  const resetBtn = document.querySelector('[data-cte-reset]');
  const verifyResults = document.querySelector('[data-cte-verify-results]');
  const summarySelection = document.querySelector('[data-cte-selection]');
  const summarySourceFile = document.querySelector('[data-cte-source-file]');
  const summarySourceLayer = document.querySelector('[data-cte-source-layer]');
  const summaryTokens = document.querySelector('[data-cte-tokens-count]');
  const searchField = document.querySelector('[data-cte-search]');
  const filterButtons = document.querySelectorAll('[data-cte-filter]');
  const diffPanel = document.querySelector('[data-cte-diff-panel]');
  const diffCount = document.querySelector('[data-cte-diff-count]');
  const diffList = document.querySelector('[data-cte-diff-list]');
  const diffTitle = document.querySelector('[data-cte-diff-title]');
  const safetyPanel = document.querySelector('[data-cte-safety-panel]');
  const modeGuideSimple = document.querySelector('[data-cte-mode-guide-simple]');
  const modeGuideAdvanced = document.querySelector('[data-cte-mode-guide-advanced]');
  const tokenData = {};
  const tokenValues = {};
  const touchedTokens = {};
  const groupState = {};
  const effectiveTokenData = {};
  const frequentSet = {
    bg: true,
    panel: true,
    text: true,
    muted: true,
    line: true,
    accent: true,
    'card-surface': true,
    'topbar-bg': true,
    'sidebar-bg': true
  };
  const state = {
    filter: 'all',
    search: '',
    safetyExpanded: false,
    baselineSafety: null,
    mode: (function(){
      try {
        var match = document.cookie.match(/(?:^|;\s*)cte_mode=([^;]*)/);
        return (match && match[1]) || 'simple';
      } catch(e){ return 'simple'; }
    })()
  };
  var previewWindow = null;

  var selectorsData = [];
  var i18nData = {};
  var parsedCssSelectors = [];
  var availableStylesData = {};
  var SOURCE_SNAPSHOT_URL = workspace.getAttribute('data-cte-source-snapshot-url') || '/apps/studio/tools/customization-studio/design-system/tokens/source-snapshot';
  try {
    selectorsData = JSON.parse(workspace.getAttribute('data-cte-selectors') || '[]');
  } catch (e) {}
  try {
    availableStylesData = JSON.parse(workspace.getAttribute('data-cte-available-styles') || '{}');
  } catch (e) {}
  try {
    i18nData = JSON.parse(workspace.getAttribute('data-cte-i18n') || '{}');
  } catch (e) {}

  function buildParsedSelectorsFromData(list) {
    var out = [];
    for (var i = 0; i < list.length; i++) {
      var row = list[i] || {};
      var key = String(row.key || '').trim();
      if (!key) continue;
      var selector = String(row.selector || '').trim().toLowerCase().replace(/\s+/g, ' ');
      var blockIndex = parseInt(row.block_index || '1', 10);
      if (!isFinite(blockIndex) || blockIndex < 1) blockIndex = 1;
      out.push({
        key: key,
        selector: selector,
        tokens: row.tokens && typeof row.tokens === 'object' ? row.tokens : {},
        block_index: blockIndex
      });
    }
    return out;
  }

  parsedCssSelectors = buildParsedSelectorsFromData(selectorsData);

  function collectAvailableStyleKeys() {
    var map = {};
    if (availableStylesData && typeof availableStylesData === 'object') {
      for (var styleKey in availableStylesData) {
        if (!Object.prototype.hasOwnProperty.call(availableStylesData, styleKey)) continue;
        var normalized = String(styleKey || '').trim().toLowerCase();
        if (normalized !== '' && normalized !== 'base') {
          map[normalized] = true;
        }
      }
    }

    for (var i = 0; i < selectorsData.length; i++) {
      var selectorStyle = String(selectorsData[i] && selectorsData[i].style || '').trim().toLowerCase();
      if (selectorStyle !== '' && selectorStyle !== 'base') {
        map[selectorStyle] = true;
      }
    }

    return Object.keys(map);
  }

  var availableStyleKeys = collectAvailableStyleKeys();

  function humanizeStyleName(styleName) {
    var normalized = String(styleName || '').trim().toLowerCase();
    if (normalized === '') return 'Theme';
    var displayLabel = availableStylesData && availableStylesData[normalized];
    if (typeof displayLabel === 'string' && displayLabel.trim() !== '') {
      return displayLabel.trim();
    }
    return normalized
      .split(/[-_.\s]+/)
      .filter(function(part){ return part !== ''; })
      .map(function(part){ return part.charAt(0).toUpperCase() + part.slice(1); })
      .join(' ');
  }

  function showFetchWarning() {
    var existing = document.querySelector('[data-cte-fetch-warning]');
    if (existing) return;
    var warning = document.createElement('div');
    warning.setAttribute('data-cte-fetch-warning', '');
    warning.className = 'cte-fetch-warning';
    warning.textContent = t('fetch_failed_source', 'Could not refresh theme source files. Using currently loaded view data only. Saving will be blocked if source files are unreadable.');
    var editorCard = document.querySelector('.cte-card--editor');
    if (!editorCard) return;
    editorCard.insertBefore(warning, editorCard.firstChild);
  }

  function hideFetchWarning() {
    var existing = document.querySelector('[data-cte-fetch-warning]');
    if (existing) existing.remove();
  }

  function fetchSourceSnapshot() {
    hideFetchWarning();
    return fetch(SOURCE_SNAPSHOT_URL + '?t=' + Date.now())
      .then(function(r) {
        if (!r.ok) throw new Error('HTTP ' + r.status);
        return r.json();
      })
      .then(function(payload) {
        if (!payload || payload.ok !== true || !Array.isArray(payload.selectors)) {
          throw new Error('Invalid payload');
        }
        selectorsData = payload.selectors;
        parsedCssSelectors = buildParsedSelectorsFromData(selectorsData);
        hideFetchWarning();
        return parsedCssSelectors;
      })
      .catch(function() {
        showFetchWarning();
      });
  }

  function getSelectorEntry(key) {
    for (var i = 0; i < selectorsData.length; i++) {
      if (selectorsData[i] && selectorsData[i].key === key) {
        return selectorsData[i];
      }
    }
    return null;
  }

  function getSourceLayerLabel(kind) {
    var normalized = String(kind || '').trim().toLowerCase();
    if (normalized === 'base') return t('source_layer_foundation', 'Foundation');
    if (normalized === 'semantic') return t('source_layer_semantic', 'Semantic');
    return t('source_layer_variant', 'Variant');
  }

  function getTokensFromParsedCss(compoundKey) {
    for (var i = 0; i < parsedCssSelectors.length; i++) {
      if (parsedCssSelectors[i].key === compoundKey) {
        return parsedCssSelectors[i].tokens;
      }
    }
    return null;
  }

  function parseSelectorContext(rawSelector) {
    var selector = String(rawSelector || '').toLowerCase();
    var theme = null;
    var style = null;
    var themeMatch = selector.match(/data-theme="([^"]+)"/i);
    var styleMatch = selector.match(/data-color-style="([^"]+)"/i);
    if (themeMatch) theme = themeMatch[1].trim().toLowerCase();
    if (styleMatch) style = styleMatch[1].trim().toLowerCase();
    return { theme: theme, style: style };
  }

  function selectorAppliesToContext(sourceSelector, targetContext) {
    if (sourceSelector === ':root') return true;
    var sourceContext = parseSelectorContext(sourceSelector);
    if (sourceContext.theme && sourceContext.theme !== targetContext.theme) return false;
    if (sourceContext.style && sourceContext.style !== targetContext.style) return false;
    return !!(sourceContext.theme || sourceContext.style);
  }

  function getEffectiveTokensForSelector(compoundKey, ownTokens) {
    var targetSelector = String(compoundKey || '').split('::')[0];
    var targetContext = parseSelectorContext(targetSelector);
    var effective = {};

    for (var i = 0; i < parsedCssSelectors.length; i++) {
      var entry = parsedCssSelectors[i];
      if (!entry || !entry.selector || !entry.tokens) continue;
      if (selectorAppliesToContext(entry.selector, targetContext)) {
        for (var tokenName in entry.tokens) {
          if (entry.tokens.hasOwnProperty(tokenName)) {
            effective[tokenName] = entry.tokens[tokenName];
          }
        }
      }
      if (entry.key === compoundKey) break;
    }

    for (var ownName in ownTokens) {
      if (ownTokens.hasOwnProperty(ownName)) {
        effective[ownName] = ownTokens[ownName];
      }
    }

    return effective;
  }

  function t(key, fallback) {
    var value = i18nData[key];
    return typeof value === 'string' && value !== '' ? value : fallback;
  }

  function normalizeTokenName(name) {
    return String(name || '').trim().replace(/^--/, '').toLowerCase();
  }

  function categoryForToken(name) {
    var n = normalizeTokenName(name);

    if (frequentSet[n]) return 'frequent';
    if (/^scan-/.test(n)) return 'scan';
    if (/glass|blur|frost|backdrop/.test(n)) return 'glass';
    if (/notify|notification|alert|toast|badge/.test(n)) return 'notifications';
    if (/^space-|spacing|gap|padding|margin|gutter|inset/.test(n)) return 'spacing';
    if (/radius|rounded|rounding/.test(n)) return 'radius';
    if (/font|typography|line-height|letter-spacing|text-size|text-weight/.test(n)) return 'typography';
    if (/card/.test(n)) return 'cards';
    if (/^table-|table|thead|tbody|row|column/.test(n)) return 'tables';
    if (/transition|duration|timing|ease|shadow|elevation/.test(n)) return 'transitions-shadows';
    if (/^style-shell-|^shell-|^chrome-|topbar|sidebar|nav|toolbar|drawer/.test(n)) return 'chrome-shell';
    if (/^style-control-|^control-|input|button|btn|field|checkbox|radio|toggle|select/.test(n)) return 'controls';
    if (/^color-|^tone-|success|warning|danger|info|positive|negative/.test(n)) return 'semantic-colors';
    if (/^bg$|^panel$|^text$|^muted$|^line$|^accent$|color|palette|surface|foreground|background/.test(n)) return 'colors';

    return 'other';
  }

  /* ---- Color display helpers ---- */
  var COLOR_RE_HEX = /^#([0-9a-f]{3,4}|[0-9a-f]{6}|[0-9a-f]{8})$/i;
  var COLOR_RE_FUNC = /^(rgba?|hsla?|hwb|lab|lch|oklch|oklab|color)\(/i;
  var COLOR_RE_NAMED = /^(transparent|currentColor|inherit|initial|revert|unset)$/i;
  var COLOR_RE_VAR = /^var\(--([^,)]+)(?:\s*,\s*([^)]+))?\)$/;

  function isColorValue(value) {
    if (!value || typeof value !== 'string') return false;
    var v = value.trim();
    if (COLOR_RE_HEX.test(v)) return true;
    if (COLOR_RE_FUNC.test(v)) return true;
    if (COLOR_RE_NAMED.test(v)) return true;
    return false;
  }

  function expandHex(value) {
    if (!value || typeof value !== 'string') return null;
    var v = value.trim().toLowerCase();
    var m3 = v.match(/^#([0-9a-f])([0-9a-f])([0-9a-f])$/);
    if (m3) return '#' + m3[1] + m3[1] + m3[2] + m3[2] + m3[3] + m3[3];
    if (/^#[0-9a-f]{6}$/.test(v)) return v;
    return null;
  }

  function resolveVarValue(value, tokenData) {
    if (!value || typeof value !== 'string') return null;
    var match = value.trim().match(COLOR_RE_VAR);
    if (!match) return null;
    var refName = match[1].trim().replace(/^--/, '');
    var fallback = match[2] ? match[2].trim() : null;
    if (tokenData[refName] !== undefined) return tokenData[refName];
    return fallback;
  }

  function couldBeColor(value, tokenData) {
    if (isColorValue(value)) return true;
    var resolved = resolveVarValue(value, tokenData);
    if (resolved && isColorValue(resolved)) return true;
    return false;
  }

  var FRIENDLY_ABBR = {
    bg: 'Background',
    text: 'Text',
    muted: 'Muted Text',
    line: 'Border Line',
    accent: 'Accent',
    'card-surface': 'Card Surface',
    btn: 'Button',
    nav: 'Navigation',
    notif: 'Notification',
    tone: 'Tone',
    info: 'Info',
    success: 'Success',
    danger: 'Danger',
    warning: 'Warning',
    radius: 'Radius',
    shadow: 'Shadow',
    depth: 'Depth',
    blur: 'Blur',
    glass: 'Glass',
    topbar: 'Top Bar',
    sidebar: 'Sidebar',
    shell: 'Shell',
    chrome: 'Chrome',
    table: 'Table',
    icon: 'Icon',
    chip: 'Chip',
    card: 'Card',
    panel: 'Panel',
    control: 'Control',
    focus: 'Focus',
    hover: 'Hover',
    scan: 'Scan',
    overlay: 'Overlay',
    border: 'Border',
    edge: 'Edge',
    specular: 'Specular',
    highlight: 'Highlight',
    transition: 'Transition',
    font: 'Font',
    type: 'Type',
    space: 'Space',
    gap: 'Gap',
    padding: 'Padding',
    margin: 'Margin',
    safe: 'Safe Area',
    frame: 'Frame',
    select: 'Select',
    scheme: 'Color Scheme',
    'card-border': 'Card Border',
    'card-edge': 'Card Edge'
  };

  function friendlyTokenLabel(name) {
    var n = normalizeTokenName(name);
    if (!n) return '';
    if (FRIENDLY_ABBR[n]) return FRIENDLY_ABBR[n];
    var parts = n.split('-');
    var label = parts.map(function(p) {
      if (FRIENDLY_ABBR[p]) return FRIENDLY_ABBR[p];
      if (p === 'color' || p === 'style') return '';
      if (p === 'tone') return 'Tone';
      return p.charAt(0).toUpperCase() + p.slice(1);
    }).filter(function(p) { return p !== ''; }).join(' ');
    return label || n;
  }

  /* ---- Token purpose descriptions ---- */
  var TOKEN_PURPOSE = {
    'color-primary': t('purpose_primary', 'Used for main buttons, active states, and primary actions'),
    'color-accent': t('purpose_accent', 'Used for highlights, links, and interactive elements'),
    'color-background': t('purpose_background', 'Main page and canvas background color'),
    'color-surface': t('purpose_surface', 'Card, panel, and container surface background'),
    'color-text': t('purpose_text', 'Primary text color for readable content'),
    'color-muted': t('purpose_muted', 'Secondary and less prominent text'),
    'color-border': t('purpose_border', 'Dividers, borders, and UI element outlines'),
    'card-surface': t('purpose_card_surface', 'Card and container panel background'),
    'card-border': t('purpose_card_border', 'Card and container border color'),
    'topbar-bg': t('purpose_topbar_bg', 'Top navigation bar background'),
    'sidebar-bg': t('purpose_sidebar_bg', 'Sidebar panel background'),
    'scan-video-bg': t('purpose_scan_bg', 'Camera/scan video preview background'),
    'success': t('purpose_success', 'Indicates successful operations and positive states'),
    'danger': t('purpose_danger', 'Indicates errors, destructive actions, and critical issues'),
    'warning': t('purpose_warning', 'Indicates warnings, cautions, and attention-needed states'),
    'info': t('purpose_info', 'Indicates informational messages and neutral notifications'),
    'scan-line': t('purpose_scan_line', 'Camera scan guideline and overlay border'),
    'scan-corner': t('purpose_scan_corner', 'Camera scan frame corner markers'),
    'shadow': t('purpose_shadow', 'Box shadows for elevation, depth, and layered UI'),
    'line-height': t('purpose_line_height', 'Text line height for readability spacing'),
    'font-size': t('purpose_font_size', 'Base font size for text content'),
    'font-family': t('purpose_font_family', 'Primary typeface for UI text'),
    'radius-scale': t('purpose_radius_scale', 'Global border radius scale for rounded corners'),
    'control-line-height': t('purpose_control_line_height', 'Input and form control minimum height'),
    'control-radius': t('purpose_control_radius', 'Input and form control border radius'),
    'muted': t('purpose_muted_short', 'Secondary text color for less prominent content'),
    'accent': t('purpose_accent_short', 'Interactive accent color for links and highlights')
  };

  function getTokenPurpose(name) {
    var n = normalizeTokenName(name);
    if (TOKEN_PURPOSE[n]) return TOKEN_PURPOSE[n];
    if (/^tone-/.test(n)) return t('purpose_tone', 'Semantic tone color for status indicators');
    if (/^notif-/.test(n)) return t('purpose_notif', 'Notification and alert badge color');
    if (/^glass-/.test(n) || /glass/.test(n)) return t('purpose_glass', 'Glassmorphism and frosted glass effect color');
    if (/^space-/.test(n) || /^gap-/.test(n)) return t('purpose_spacing', 'Layout spacing and gap size');
    if (/^padding-/.test(n) || /^margin-/.test(n)) return t('purpose_padding_margin', 'Element padding and margin size');
    if (/^table-/.test(n)) return t('purpose_table', 'Table row, header, and cell appearance');
    if (/^style-shell-/.test(n) || /^shell-/.test(n)) return t('purpose_shell', 'Application shell and chrome chrome appearance');
    if (/^control-/.test(n)) return t('purpose_control', 'Form control and interactive input appearance');
    if (/^btn-/.test(n) || /^button-/.test(n)) return t('purpose_button', 'Button component appearance');
    if (/nav|sidebar/.test(n)) return t('purpose_nav', 'Navigation and sidebar appearance');
    if (/overlay|modal|dialog|drawer/.test(n)) return t('purpose_overlay', 'Overlay, modal, and dialog appearance');
    if (/transition|duration|timing|ease/.test(n)) return t('purpose_transition', 'Animation timing and transition behavior');
    if (/specular|highlight/.test(n)) return t('purpose_specular', 'Specular highlight and reflective surface effect');
    if (/frame|safe/.test(n)) return t('purpose_safe_area', 'Safe area insets for notched devices');
    if (/border|edge|line/.test(n)) return t('purpose_border_generic', 'Border, divider, and edge line color');
    if (/bg$|background/.test(n)) return t('purpose_bg_generic', 'Background area fill color');
    if (/text/.test(n)) return t('purpose_text_generic', 'Text content color');
    if (/chip|badge|tag/.test(n)) return t('purpose_chip', 'Chip, badge, and tag component appearance');
    if (/avatar/.test(n)) return t('purpose_avatar', 'Avatar and user image placeholder color');
    if (/icon/.test(n)) return t('purpose_icon', 'Icon and symbol fill color');
    if (/checkbox|radio|toggle/.test(n)) return t('purpose_choice', 'Checkbox, radio, and toggle control appearance');
    if (/select|dropdown/.test(n)) return t('purpose_select', 'Select and dropdown menu appearance');
    return '';
  }

  /* ---- Human-readable color name ---- */
  var BASIC_COLORS = {
    '#000000': 'Black', '#ffffff': 'White', '#ff0000': 'Red', '#00ff00': 'Green',
    '#0000ff': 'Blue', '#ffff00': 'Yellow', '#ff00ff': 'Magenta', '#00ffff': 'Cyan',
    '#c0c0c0': 'Silver', '#808080': 'Gray', '#800000': 'Maroon', '#808000': 'Olive',
    '#008000': 'Dark Green', '#000080': 'Navy', '#800080': 'Purple', '#008080': 'Teal',
    '#0f172a': 'Slate 900', '#1e293b': 'Slate 800', '#334155': 'Slate 700',
    '#475569': 'Slate 600', '#64748b': 'Slate 500', '#94a3b8': 'Slate 400',
    '#cbd5e1': 'Slate 300', '#e2e8f0': 'Slate 200', '#f1f5f9': 'Slate 100',
    '#f8fafc': 'Slate 50', '#1f2937': 'Gray 800', '#374151': 'Gray 700',
    '#4b5563': 'Gray 600', '#6b7280': 'Gray 500', '#9ca3af': 'Gray 400',
    '#d1d5db': 'Gray 300', '#e5e7eb': 'Gray 200', '#f3f4f6': 'Gray 100',
    '#f9fafb': 'Gray 50', '#111827': 'Gray 900', '#2563eb': 'Blue 600',
    '#3b82f6': 'Blue 500', '#60a5fa': 'Blue 400', '#93c5fd': 'Blue 300',
    '#bfdbfe': 'Blue 200', '#dbeafe': 'Blue 100', '#eff6ff': 'Blue 50',
    '#1d4ed8': 'Blue 700', '#1e40af': 'Blue 800', '#dc2626': 'Red 600',
    '#ef4444': 'Red 500', '#f87171': 'Red 400', '#fca5a5': 'Red 300',
    '#fecaca': 'Red 200', '#fee2e2': 'Red 100', '#fef2f2': 'Red 50',
    '#b91c1c': 'Red 700', '#16a34a': 'Green 600', '#22c55e': 'Green 500',
    '#4ade80': 'Green 400', '#86efac': 'Green 300', '#bbf7d0': 'Green 200',
    '#dcfce7': 'Green 100', '#f0fdf4': 'Green 50', '#15803d': 'Green 700',
    '#d97706': 'Amber 600', '#f59e0b': 'Amber 500', '#fbbf24': 'Amber 400',
    '#fcd34d': 'Amber 300', '#fde68a': 'Amber 200', '#fef3c7': 'Amber 100',
    '#fffbeb': 'Amber 50', '#b45309': 'Amber 700', '#8b5cf6': 'Violet 500',
    '#a78bfa': 'Violet 400', '#c4b5fd': 'Violet 300', '#ddd6fe': 'Violet 200',
    '#ede9fe': 'Violet 100', '#7c3aed': 'Violet 600', '#6d28d9': 'Violet 700',
    '#ec4899': 'Pink 500', '#f472b6': 'Pink 400', '#f9a8d4': 'Pink 300',
    '#fbcfe8': 'Pink 200', '#fce7f3': 'Pink 100', '#db2777': 'Pink 600',
    '#0891b2': 'Cyan 600', '#06b6d4': 'Cyan 500', '#22d3ee': 'Cyan 400',
    '#67e8f9': 'Cyan 300', '#a5f3fc': 'Cyan 200', '#cffafe': 'Cyan 100',
    '#ecfeff': 'Cyan 50', '#0e7490': 'Cyan 700', '#f97316': 'Orange 500',
    '#fb923c': 'Orange 400', '#fdba74': 'Orange 300', '#fed7aa': 'Orange 200',
    '#ffedd5': 'Orange 100', '#ea580c': 'Orange 600', '#e11d48': 'Rose 600',
    '#f43f5e': 'Rose 500', '#fb7185': 'Rose 400', '#fda4af': 'Rose 300',
    '#fecdd3': 'Rose 200', '#ffe4e6': 'Rose 100', '#fff1f2': 'Rose 50',
    '#78716c': 'Stone 500', '#a8a29e': 'Stone 400', '#d6d3d1': 'Stone 300',
    '#e7e5e4': 'Stone 200', '#f5f5f4': 'Stone 100', '#fafaf9': 'Stone 50',
    '#292524': 'Stone 800', '#44403c': 'Stone 700', '#57534e': 'Stone 600',
    '#1c1917': 'Stone 900'
  };

  function getColorName(hexValue) {
    if (!hexValue || typeof hexValue !== 'string') return '';
    var v = hexValue.trim().toLowerCase();
    if (BASIC_COLORS[v]) return BASIC_COLORS[v];
    var m = v.match(/^#([0-9a-f]{6})$/i);
    if (m) return '#' + m[1].toUpperCase();
    return v;
  }

  function getSimpleColorControlLabel(label) {
    var text = String(label || '').trim();
    if (text === '') return text;
    if (/(color|カラー|色|रङ|रंग)/i.test(text)) return text;
    return text + t('simple_color_label_suffix', ' Color');
  }

  function getSimpleColorDescriptor(value, tokenDataForResolve, inherited) {
    var raw = String(value || '').trim();
    var resolved = resolveVarValue(raw, tokenDataForResolve || {}) || raw;
    var namedHex = expandHex(resolved);
    if (namedHex) {
      var colorName = getColorName(namedHex);
      if (colorName && colorName.charAt(0) !== '#') {
        return colorName + ' ' + t('simple_color_word', 'color');
      }
    }
    return inherited
      ? t('simple_color_inherited', 'Inherited color')
      : t('simple_color_selected', 'Selected color');
  }

  /* ---- Var chain label ---- */
  function getVarChainLabel(value, tokenData) {
    if (!value || typeof value !== 'string') return null;
    var m = value.trim().match(/^var\(--([^,)]+)/);
    if (!m) return null;
    var refName = m[1].trim();
    var friendly = friendlyTokenLabel(refName);
    if (friendly && friendly !== '') {
      return t('uses_token', 'Uses ') + friendly;
    }
    return null;
  }

  var COLOR_CATEGORIES = {
    brand: 'Brand',
    backgrounds: 'Background',
    text: 'Text',
    borders: 'Border',
    status: 'Status',
    effects: 'Effect/Glass',
    'other-colors': 'Other'
  };

  function colorCategory(name) {
    var n = normalizeTokenName(name);
    if (/^(accent$|color-accent)/.test(n)) return 'brand';
    if (/^(bg$|panel$|card$|page-|background|surface)/.test(n) || /-bg$/.test(n) || /-background$/.test(n) || /^color-background/.test(n) || /^color-surface/.test(n) || /^color-card/.test(n)) return 'backgrounds';
    if (/^(text$|muted$|color-text|color-muted)/.test(n) || /-text$/.test(n)) return 'text';
    if (/^(line$|border|edge)/.test(n) || /-border$/.test(n) || /-edge$/.test(n) || /-line$/.test(n) || /^color-border/.test(n)) return 'borders';
    if (/^(success|danger|warning|info|tone|notif|status|positive|negative|error)/.test(n) || /-success/.test(n) || /-danger/.test(n) || /-warning/.test(n) || /-info/.test(n)) return 'status';
    if (/^(glass|shadow|depth|blur|specular|highlight)/.test(n) || /^glass-/.test(n) || /-shadow$/.test(n) || /-blur$/.test(n)) return 'effects';
    return 'other-colors';
  }

  /* ---- Contrast / readability helpers ---- */
  function parseColor(value) {
    if (!value || typeof value !== 'string') return null;
    var v = value.trim();
    var m;
    m = v.match(/^#([0-9a-f]{3,4}|[0-9a-f]{6}|[0-9a-f]{8})$/i);
    if (m) {
      var hex = m[1];
      if (hex.length === 3) hex = hex[0]+hex[0]+hex[1]+hex[1]+hex[2]+hex[2];
      if (hex.length === 4) hex = hex[0]+hex[0]+hex[1]+hex[1]+hex[2]+hex[2]+hex[3]+hex[3];
      return {
        r: parseInt(hex.substr(0,2), 16),
        g: parseInt(hex.substr(2,2), 16),
        b: parseInt(hex.substr(4,2), 16),
        a: hex.length === 8 ? parseInt(hex.substr(6,2), 16) / 255 : 1
      };
    }
    m = v.match(/^rgba?\s*\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)(?:\s*,\s*([\d.]+))?\)/i);
    if (m) {
      return { r: parseInt(m[1], 10), g: parseInt(m[2], 10), b: parseInt(m[3], 10), a: m[4] !== undefined ? parseFloat(m[4]) : 1 };
    }
    return null;
  }

  function compositeOver(fg, bg) {
    var a = fg.a !== undefined ? fg.a : 1;
    if (a >= 0.999) return { r: fg.r, g: fg.g, b: fg.b };
    return {
      r: Math.round(a * fg.r + (1 - a) * bg.r),
      g: Math.round(a * fg.g + (1 - a) * bg.g),
      b: Math.round(a * fg.b + (1 - a) * bg.b)
    };
  }

  function linearize(c) {
    var s = c / 255;
    return s <= 0.03928 ? s / 12.92 : Math.pow((s + 0.055) / 1.055, 2.4);
  }

  function contrastRatio(c1, c2) {
    var l1 = 0.2126 * linearize(c1.r) + 0.7152 * linearize(c1.g) + 0.0722 * linearize(c1.b);
    var l2 = 0.2126 * linearize(c2.r) + 0.7152 * linearize(c2.g) + 0.0722 * linearize(c2.b);
    var lighter = Math.max(l1, l2);
    var darker = Math.min(l1, l2);
    return (lighter + 0.05) / (darker + 0.05);
  }

  var READABILITY_PAIRS = [
    { text: 'text', bg: 'bg', labelInfix: 'Text on Background' },
    { text: 'muted', bg: 'bg', labelInfix: 'Muted on Background' },
    { text: 'accent', bg: 'bg', labelInfix: 'Accent on Background' },
    { text: 'color-text', bg: 'background', labelInfix: 'Text on Background' },
    { text: 'color-text-muted', bg: 'background', labelInfix: 'Muted on Background' }
  ];

  function getReadabilityPair(name, tokenData) {
    var n = normalizeTokenName(name);
    for (var p = 0; p < READABILITY_PAIRS.length; p++) {
      if (n === READABILITY_PAIRS[p].text) {
        var bgTok = READABILITY_PAIRS[p].bg;
        if (tokenData[bgTok] !== undefined) return { bgToken: bgTok, label: READABILITY_PAIRS[p].labelInfix };
      }
    }
    var tm = n.match(/^tone-(\w+)-text$/);
    if (tm) {
      var tb = 'tone-' + tm[1] + '-bg';
      if (tokenData[tb] !== undefined) return { bgToken: tb, label: tm[1].charAt(0).toUpperCase() + tm[1].slice(1) + ' tone' };
    }
    var nm = n.match(/^notif-chip-(\w+)-color$/);
    if (nm) {
      var nb = 'notif-chip-' + nm[1] + '-bg';
      if (tokenData[nb] !== undefined) return { bgToken: nb, label: 'Notif: ' + nm[1] };
    }
    var gm = n.match(/^(.+)-text$/);
    if (gm) {
      var gb = gm[1] + '-bg';
      if (tokenData[gb] !== undefined) return { bgToken: gb, label: 'Text on bg' };
    }
    return null;
  }

  function computeReadability(textValue, bgValue, tokenData) {
    var tVal = resolveVarValue(textValue, tokenData) || textValue;
    var bVal = resolveVarValue(bgValue, tokenData) || bgValue;
    var tC = parseColor(tVal);
    var bC = parseColor(bVal);
    if (!tC || !bC) return { status: 'unknown', ratio: null };
    if ((tC.a !== undefined && tC.a < 0.999) || (bC.a !== undefined && bC.a < 0.999)) {
      var surfaceStr = tokenData['bg'] || tokenData['background'] || null;
      if (surfaceStr) {
        var surfaceResolved = resolveVarValue(surfaceStr, tokenData) || surfaceStr;
        var surface = parseColor(surfaceResolved);
        if (surface) {
          if (bC.a !== undefined && bC.a < 0.999) bC = compositeOver(bC, surface);
          if (tC.a !== undefined && tC.a < 0.999) tC = compositeOver(tC, surface);
        }
      }
    }
    var ratio = contrastRatio(tC, bC);
    if (ratio >= 4.5) return { status: 'good', ratio: ratio };
    return { status: 'low', ratio: ratio };
  }

  function updateTokenReadability(row, tokenData) {
    var readabilityEl = row.querySelector('[data-cte-readability]');
    if (!readabilityEl) return;
    var name = row.getAttribute('data-cte-token-name') || '';
    var pair = getReadabilityPair(name, tokenData);
    if (!pair) { readabilityEl.style.display = 'none'; return; }
    var textValue = tokenValues[name] !== undefined ? tokenValues[name] : (tokenData[name] || '');
    var bgValue = tokenData[pair.bgToken] || '';
    var result = computeReadability(textValue, bgValue, tokenData);
    var indicator = readabilityEl.querySelector('[data-cte-readability-indicator]');
    if (indicator) {
      indicator.className = 'cte-readability-indicator is-' + result.status;
      var dot = indicator.querySelector('.cte-readability-dot');
      var labelSpan = indicator.querySelector('.cte-readability-label');
      if (dot) dot.className = 'cte-readability-dot is-' + result.status;
      if (labelSpan) {
        labelSpan.textContent = result.status === 'good' ? t('readability_good', 'Good readability') : result.status === 'low' ? t('readability_low', 'Low contrast') : t('readability_unknown', 'Check manually');
        if (result.ratio) {
          labelSpan.title = 'Contrast ratio: ' + result.ratio.toFixed(1) + ':1';
        }
      }
    }
    readabilityEl.style.display = '';
  }
  /* ---- end contrast helpers ---- */

  /* ---- Mode helpers ---- */
  function getCurrentSelectorLabel() {
    var key = selectorField ? selectorField.value : '';
    for (var i = 0; i < selectorsData.length; i++) {
      if (selectorsData[i].key === key) return selectorsData[i].label || key;
    }
    return key;
  }

  function getCurrentBlockIndex() {
    var key = selectorField ? selectorField.value : '';
    for (var i = 0; i < parsedCssSelectors.length; i++) {
      if (parsedCssSelectors[i].key === key) return parsedCssSelectors[i].block_index;
    }
    return '';
  }

  function setDisplayMode(mode) {
    state.mode = mode;
    try { document.cookie = 'cte_mode=' + mode + ';path=/;max-age=31536000'; } catch(e) {}
    var modeBtns = document.querySelectorAll('[data-cte-mode]');
    modeBtns.forEach(function(btn) {
      btn.classList.toggle('is-active', btn.getAttribute('data-cte-mode') === mode);
    });
    updateModeGuide();
    renderTokens();
  }
  /* ---- end mode helpers ---- */

  function updateModeGuide() {
    if (modeGuideSimple) {
      modeGuideSimple.hidden = state.mode !== 'simple';
    }
    if (modeGuideAdvanced) {
      modeGuideAdvanced.hidden = state.mode !== 'advanced';
    }
  }

  /* ---- Simple Mode Dashboard ---- */

  function detectSimpleTheme(selectorKey) {
    var key = (selectorKey || '').toLowerCase();
    var selector = key.split('::')[0];
    var context = parseSelectorContext(selector);
    var style = context.style || '';
    if (style && availableStyleKeys.indexOf(style) !== -1) return style;
    for (var i = 0; i < availableStyleKeys.length; i++) {
      if (key.indexOf(availableStyleKeys[i]) !== -1) return availableStyleKeys[i];
    }
    if (style) return style;
    if (availableStyleKeys.length > 0) return availableStyleKeys[0];
    return 'default';
  }

  var SIMPLE_CONTROL_DEFS = [
    { tab: 'basics', key: 'background', labelKey: 'simple_control_background', candidates: ['bg', 'color-background'] },
    { tab: 'basics', key: 'panel', labelKey: 'simple_control_panel', candidates: ['panel', 'color-surface'] },
    { tab: 'basics', key: 'card', labelKey: 'simple_control_card', candidates: ['card', 'color-card', 'card-surface'] },
    { tab: 'basics', key: 'main-text', labelKey: 'simple_control_main_text', candidates: ['text', 'color-text'] },
    { tab: 'basics', key: 'secondary-text', labelKey: 'simple_control_secondary_text', candidates: ['muted', 'color-text-muted', 'color-muted'] },
    { tab: 'basics', key: 'accent', labelKey: 'simple_control_accent', candidates: ['accent', 'color-accent'] },
    { tab: 'basics', key: 'border', labelKey: 'simple_control_border', candidates: ['line', 'color-border'] },
    { tab: 'layout', key: 'text-size', labelKey: 'simple_control_text_size', candidates: ['type-body-size', 'control-font-size', 'font-size'] },
    { tab: 'layout', key: 'corner-roundness', labelKey: 'simple_control_corner_roundness', candidates: ['radius-sm', 'radius-md', 'card-radius', 'control-radius'] },
    { tab: 'layout', key: 'control-size', labelKey: 'simple_control_control_size', candidates: ['control-height', 'control-line-height'] },
    { tab: 'layout', key: 'ui-density', labelKey: 'simple_control_ui_density', candidates: ['space-2', 'space-3', 'control-gap'] },
    { tab: 'components', key: 'top-bar', labelKey: 'simple_control_top_bar', candidates: ['topbar-bg'] },
    { tab: 'components', key: 'sidebar', labelKey: 'simple_control_sidebar', candidates: ['sidebar-bg'] },
    { tab: 'components', key: 'buttons', labelKey: 'simple_control_buttons', candidates: ['style-button-bg', 'accent'] },
    { tab: 'components', key: 'inputs', labelKey: 'simple_control_inputs', candidates: ['style-control-bg', 'control-radius'] },
    { tab: 'components', key: 'tables', labelKey: 'simple_control_tables', candidates: ['style-table-bg'] },
    { tab: 'components', key: 'cards', labelKey: 'simple_control_cards', candidates: ['card-surface', 'card-radius'] },
    { tab: 'theme', theme: 'liquid-glass', key: 'glass-strength', labelKey: 'simple_control_glass_strength', candidates: ['glass-strong'] },
    { tab: 'theme', theme: 'liquid-glass', key: 'glass-transparency', labelKey: 'simple_control_glass_transparency', candidates: ['glass'] },
    { tab: 'theme', theme: 'liquid-glass', key: 'glass-blur', labelKey: 'simple_control_glass_blur', candidates: ['glass-blur', 'glass-blur-card', 'glass-blur-shell'] },
    { tab: 'theme', theme: 'liquid-glass', key: 'glass-reflection', labelKey: 'simple_control_glass_reflection', candidates: ['glass-specular-top', 'glass-specular-bottom'] },
    { tab: 'theme', theme: 'liquid-glass', key: 'glass-highlights', labelKey: 'simple_control_glass_highlights', candidates: ['glass-highlights'] },
    { tab: 'theme', theme: 'liquid-glass', key: 'glass-depth', labelKey: 'simple_control_glass_depth', candidates: ['glass-shadow', 'style-surface-shadow'] },
    { tab: 'theme', theme: 'paper', key: 'paper-contrast', labelKey: 'simple_control_paper_contrast', candidates: ['text', 'accent'] },
    { tab: 'theme', theme: 'paper', key: 'surface-separation', labelKey: 'simple_control_surface_separation', candidates: ['line', 'card-border-base', 'style-border-soft'] },
    { tab: 'theme', theme: 'paper', key: 'paper-depth', labelKey: 'simple_control_paper_depth', candidates: ['style-surface-shadow', 'glass-shadow'] }
  ];

  var SIMPLE_LAYOUT_CONTROL_META = {
    'text-size': { min: 10, max: 32, step: 1, unit: 'px' },
    'corner-roundness': { min: 0, max: 32, step: 1, unit: 'px' },
    'control-size': { min: 20, max: 72, step: 1, unit: 'px' },
    'ui-density': { min: 2, max: 24, step: 1, unit: 'px' }
  };

  function parseSimpleNumericValue(rawValue) {
    var value = String(rawValue || '').trim();
    if (value === '') return null;
    var match = value.match(/^(-?\d+(?:\.\d+)?)([a-z%]*)$/i);
    if (!match) return null;
    var num = parseFloat(match[1]);
    if (!isFinite(num)) return null;
    return { number: num, unit: (match[2] || '').toLowerCase() };
  }

  function clampSimpleNumber(value, min, max) {
    return Math.min(max, Math.max(min, value));
  }

  function formatSimpleNumericValue(numberValue, unit, step) {
    var decimals = (String(step || '').split('.')[1] || '').length;
    var normalized = decimals > 0 ? numberValue.toFixed(decimals) : String(Math.round(numberValue));
    normalized = normalized.replace(/\.0+$/, '').replace(/(\.\d*?)0+$/, '$1');
    return normalized + (unit || '');
  }

  function getSimpleLayoutMeta(controlKey, tokenValue) {
    var parsed = parseSimpleNumericValue(tokenValue);
    if (!parsed) return null;
    var baseMeta = SIMPLE_LAYOUT_CONTROL_META[controlKey];
    if (!baseMeta) return null;
    var unit = parsed.unit || baseMeta.unit;
    var numberValue = clampSimpleNumber(parsed.number, baseMeta.min, baseMeta.max);
    return {
      min: baseMeta.min,
      max: baseMeta.max,
      step: baseMeta.step,
      unit: unit,
      numberValue: numberValue,
      textValue: formatSimpleNumericValue(numberValue, unit, baseMeta.step)
    };
  }

  function getSimpleThemeLabel(theme) {
    if (theme === 'liquid-glass') return t('simple_theme_liquid_glass', 'Liquid Glass');
    if (theme === 'paper') return t('simple_theme_paper', 'Paper');
    return humanizeStyleName(theme);
  }

  function simpleControlLabel(def) {
    return t(def.labelKey, def.key);
  }

  function getSimpleTabLabel(tab) {
    var labels = {
      basics: t('simple_tab_basics', 'Basics'),
      layout: t('simple_tab_layout', 'Layout'),
      components: t('simple_tab_components', 'Components'),
      theme: t('simple_tab_theme_controls', 'Theme Controls')
    };
    return labels[tab] || tab;
  }

  function findSimpleToken(candidates) {
    for (var i = 0; i < candidates.length; i++) {
      var name = normalizeTokenName(candidates[i]);
      if (tokenData.hasOwnProperty(name)) {
        return {
          name: name,
          value: tokenValues[name] !== undefined && touchedTokens[name] ? tokenValues[name] : tokenData[name],
          original: tokenData[name],
          inherited: false
        };
      }
    }
    for (var j = 0; j < candidates.length; j++) {
      var inheritedName = normalizeTokenName(candidates[j]);
      if (effectiveTokenData.hasOwnProperty(inheritedName)) {
        return {
          name: inheritedName,
          value: effectiveTokenData[inheritedName],
          original: effectiveTokenData[inheritedName],
          inherited: true
        };
      }
    }
    return null;
  }

  function valueIsSimpleReadable(value) {
    var v = String(value || '').trim().toLowerCase();
    if (v === '') return true;
    if (v.indexOf('data:image') !== -1 || v.indexOf('url(') !== -1) return false;
    if (v.indexOf('linear-gradient(') !== -1 || v.indexOf('radial-gradient(') !== -1) return false;
    if (v.indexOf('cubic-bezier(') !== -1 || v.indexOf('env(') !== -1) return false;
    if (v.indexOf('inset ') !== -1 || v.length > 72) return false;
    return true;
  }

  function collectSimpleControls(tabId, theme) {
    var q = state.search.trim().toLowerCase();
    var controls = [];
    for (var i = 0; i < SIMPLE_CONTROL_DEFS.length; i++) {
      var def = SIMPLE_CONTROL_DEFS[i];
      if (def.tab !== tabId) continue;
      if (def.tab === 'theme' && def.theme !== theme) continue;
      var label = simpleControlLabel(def);
      var token = findSimpleToken(def.candidates);
      var value = token ? String(token.value || '') : '';
      var readable = token ? valueIsSimpleReadable(value) : false;
      var layoutMeta = null;
      var editable = false;
      var showControl = false;

      if (tabId === 'layout') {
        if (token && !token.inherited) {
          layoutMeta = getSimpleLayoutMeta(def.key, value);
          editable = !!layoutMeta;
          showControl = !!layoutMeta;
        }
      } else if (tabId === 'basics') {
        if (token && value !== '' && readable) {
          if (token.inherited) {
            editable = false;
            showControl = true;
          } else {
            editable = true;
            showControl = true;
          }
        }
      } else {
        if (token && !token.inherited && value !== '' && readable) {
          editable = true;
          showControl = true;
        }
      }

      if (!showControl) continue;
      if (q !== '' && label.toLowerCase().indexOf(q) === -1 && value.toLowerCase().indexOf(q) === -1) continue;
      controls.push({
        def: def,
        label: label,
        token: token,
        editableValue: editable,
        layoutMeta: layoutMeta
      });
    }
    return controls;
  }

  function renderSimpleControl(control) {
    var token = control.token;
    var value = token ? String(token.value || '') : '';
    var changed = token && !token.inherited && value !== token.original;
    var isColor = token && (couldBeColor(value, effectiveTokenData) || couldBeColor(token.original, effectiveTokenData));
    var controlLabel = isColor ? getSimpleColorControlLabel(control.label) : control.label;
    var html = '<div class="cte-token-row cte-simple-token' + (changed ? ' has-changes' : '') + '" data-simple-control="' + escapeHtml(control.def.key) + '"' + (token ? ' data-cte-token-name="' + escapeHtml(token.name) + '"' : '') + '>';
    html += '  <div class="cte-simple-label-row">';
    if (isColor) {
      var swatchVal = resolveVarValue(value, effectiveTokenData) || value;
      html += '    <span class="cte-simple-swatch" style="background:' + escapeHtml(swatchVal) + '"></span>';
    }
    html += '    <span class="cte-simple-label">' + escapeHtml(controlLabel) + '</span>';
    if (token && token.inherited) {
      html += '    <span class="cte-simple-inherited-badge">' + escapeHtml(t('token_info_inherited', 'Inherited')) + '</span>';
    }
    html += '  </div>';
    if (token && token.inherited && isColor) {
      var inheritedResolved = resolveVarValue(value, effectiveTokenData) || value;
      var inheritedPickerHex = expandHex(inheritedResolved);
      html += '  <div class="cte-simple-color-row">';
      if (inheritedPickerHex) {
        html += '    <input class="cte-simple-color-picker" type="color" value="' + escapeHtml(inheritedPickerHex) + '" disabled aria-disabled="true" title="' + escapeHtml(t('color_picker_title', 'Pick a color')) + '">';
      }
      html += '    <span class="cte-simple-color-name" data-simple-color-name>' + escapeHtml(getSimpleColorDescriptor(value, effectiveTokenData, true)) + '</span>';
      html += '    <input class="cte-token-input cte-simple-hidden-input" data-cte-token-input type="text" value="' + escapeHtml(value) + '" spellcheck="false" disabled aria-disabled="true">';
      html += '  </div>';
    } else if (token && token.inherited) {
      html += '  <span class="cte-simple-value-summary">' + escapeHtml(value) + '</span>';
      html += '  <input class="cte-token-input cte-simple-hidden-input" data-cte-token-input type="text" value="' + escapeHtml(value) + '" spellcheck="false" disabled aria-disabled="true">';
    } else if (isColor) {
      var editableResolved = resolveVarValue(value, effectiveTokenData) || value;
      var editablePickerHex = expandHex(editableResolved);
      html += '  <div class="cte-simple-color-row">';
      if (editablePickerHex) {
        html += '    <input class="cte-simple-color-picker" data-simple-color-picker type="color" value="' + escapeHtml(editablePickerHex) + '" title="' + escapeHtml(t('color_picker_title', 'Pick a color')) + '">';
      }
      html += '    <span class="cte-simple-color-name" data-simple-color-name>' + escapeHtml(getSimpleColorDescriptor(value, effectiveTokenData, false)) + '</span>';
      html += '    <input class="cte-token-input cte-simple-hidden-input' + (changed ? ' is-changed' : '') + '" data-cte-token-input type="text" value="' + escapeHtml(value) + '" spellcheck="false">';
      html += '  </div>';
      html += '  <div class="cte-token-info">';
      html += '    <span class="cte-token-status ' + (changed ? 'is-changed' : 'is-unchanged') + '" data-cte-token-status>' + escapeHtml(changed ? t('token_info_changed', 'modified') : t('token_info_unchanged', 'unchanged')) + '</span>';
      html += '  </div>';
    } else if (control.layoutMeta) {
      html += '  <div class="cte-simple-slider-row" data-simple-slider-row data-simple-unit="' + escapeHtml(control.layoutMeta.unit) + '">';
      html += '    <input class="cte-simple-slider" data-simple-slider type="range" min="' + escapeHtml(String(control.layoutMeta.min)) + '" max="' + escapeHtml(String(control.layoutMeta.max)) + '" step="' + escapeHtml(String(control.layoutMeta.step)) + '" value="' + escapeHtml(String(control.layoutMeta.numberValue)) + '">';
      html += '    <input class="cte-simple-number" data-simple-number type="number" min="' + escapeHtml(String(control.layoutMeta.min)) + '" max="' + escapeHtml(String(control.layoutMeta.max)) + '" step="' + escapeHtml(String(control.layoutMeta.step)) + '" value="' + escapeHtml(String(control.layoutMeta.numberValue)) + '">';
      html += '    <span class="cte-simple-unit">' + escapeHtml(control.layoutMeta.unit || t('simple_unit_px', 'px')) + '</span>';
      html += '    <input class="cte-token-input cte-simple-hidden-input' + (changed ? ' is-changed' : '') + '" data-cte-token-input type="text" value="' + escapeHtml(value) + '" spellcheck="false">';
      html += '  </div>';
      html += '  <div class="cte-token-info">';
      html += '    <span class="cte-token-status ' + (changed ? 'is-changed' : 'is-unchanged') + '" data-cte-token-status>' + escapeHtml(changed ? t('token_info_changed', 'modified') : t('token_info_unchanged', 'unchanged')) + '</span>';
      html += '  </div>';
    } else {
      html += '  <input class="cte-token-input' + (changed ? ' is-changed' : '') + '" data-cte-token-input type="text" value="' + escapeHtml(value) + '" spellcheck="false">';
      html += '  <div class="cte-token-info">';
      html += '    <span class="cte-token-status ' + (changed ? 'is-changed' : 'is-unchanged') + '" data-cte-token-status>' + escapeHtml(changed ? t('token_info_changed', 'modified') : t('token_info_unchanged', 'unchanged')) + '</span>';
      html += '  </div>';
    }
    html += '</div>';
    return html;
  }

  function renderSimpleTabPanel(tabId, theme) {
    var controls = collectSimpleControls(tabId, theme);
    if (controls.length === 0) {
      var emptyText = t('simple_no_controls', 'No controls match this view.');
      if (tabId === 'components') {
        emptyText = t('simple_components_empty', 'Component controls are not available for this theme block yet.');
      } else if (tabId === 'theme') {
        emptyText = theme === 'default'
          ? t('simple_theme_default_note', 'Choose a named theme style to see theme controls.')
          : t('simple_theme_no_controls', 'No theme-specific controls for this block.');
      }
      return '<div class="cte-simple-tab-panel" data-simple-panel="' + tabId + '" role="tabpanel"><div class="cte-simple-empty">' + escapeHtml(emptyText) + '</div></div>';
    }
    var html = '<div class="cte-simple-tab-panel" data-simple-panel="' + tabId + '" role="tabpanel">';
    html += '  <div class="cte-simple-grid">';
    for (var j = 0; j < controls.length; j++) {
      html += renderSimpleControl(controls[j]);
    }
    html += '  </div>';
    html += '</div>';
    return html;
  }

  var _simpleTheme = 'default';
  var _simpleActiveTab = 'basics';

  function switchSimpleTab(tabId) {
    _simpleActiveTab = tabId;
    var dashboard = document.querySelector('[data-cte-simple-dashboard]');
    if (!dashboard) return;
    dashboard.querySelectorAll('[data-simple-tab]').forEach(function(t) {
      t.classList.toggle('is-active', t.getAttribute('data-simple-tab') === tabId);
    });
    dashboard.querySelectorAll('[data-simple-panel]').forEach(function(p) {
      p.style.display = p.getAttribute('data-simple-panel') === tabId ? '' : 'none';
    });
  }

  function renderSimpleModeDashboard() {
    var container = document.querySelector('[data-cte-simple-dashboard]');
    if (!container) return;
    var preserved = preserveSimpleRightNodes();
    var selectorKey = selectorField ? selectorField.value : '';
    if (!selectorKey) {
      container.innerHTML = '<div class="cte-empty"><strong>' + escapeHtml(t('no_tokens', 'Select a block')) + '</strong><span>' + escapeHtml(t('no_tokens_desc', 'Choose a theme block from the dropdown.')) + '</span></div>';
      restoreSimpleRightNodes(preserved);
      return;
    }
    var theme = detectSimpleTheme(selectorKey);
    _simpleTheme = theme;
    var componentControls = collectSimpleControls('components', theme);
    var showComponentsTab = componentControls.length > 0;
    var tabs = [
      { id: 'basics', label: getSimpleTabLabel('basics') },
      { id: 'layout', label: getSimpleTabLabel('layout') }
    ];
    if (showComponentsTab) {
      tabs.push({ id: 'components', label: getSimpleTabLabel('components') });
    }
    tabs.push({ id: 'theme', label: getSimpleTabLabel('theme') + (theme !== 'default' ? ': ' + getSimpleThemeLabel(theme) : '') });

    if (!tabs.some(function(tab) { return tab.id === _simpleActiveTab; })) {
      _simpleActiveTab = 'basics';
    }

    var selLabel = getCurrentSelectorLabel();
    var html = '';
    html += '<div class="cte-simple-layout">';
    html += '  <div class="cte-simple-left">';
    html += '    <div class="cte-simple-header">';
    html += '      <span class="cte-simple-title">' + escapeHtml(t('simple_dashboard_title', 'Customization Dashboard')) + '</span>';
    html += '      <span class="cte-simple-block-name">' + escapeHtml(selLabel) + '</span>';
    html += '    </div>';
    html += '    <div class="cte-simple-tabs" role="tablist">';
    for (var ti = 0; ti < tabs.length; ti++) {
      html += '      <button type="button" class="cte-simple-tab" data-simple-tab="' + escapeHtml(tabs[ti].id) + '">' + escapeHtml(tabs[ti].label) + '</button>';
    }
    html += '    </div>';
    html += '    <div class="cte-simple-panels">';
    for (var pi = 0; pi < tabs.length; pi++) {
      html += renderSimpleTabPanel(tabs[pi].id, theme);
    }
    html += '    </div>';
    html += '  </div>';
    html += '  <div class="cte-simple-right" data-cte-simple-right></div>';
    html += '</div>';
    container.innerHTML = html;
    restoreSimpleRightNodes(preserved);
    switchSimpleTab(_simpleActiveTab || 'basics');
    container.querySelectorAll('[data-simple-tab]').forEach(function(tab) {
      tab.addEventListener('click', function() {
        switchSimpleTab(tab.getAttribute('data-simple-tab'));
      });
    });
  }

  /* ---- end Simple Mode Dashboard ---- */

  function groupMeta() {
    return [
      { key: 'frequent', label: t('group_frequently_edited', 'Frequently Edited') },
      { key: 'colors', label: t('group_colors', 'Colors') },
      { key: 'semantic-colors', label: t('group_semantic_colors', 'Semantic Colors') },
      { key: 'glass', label: t('group_glass', 'Glass') },
      { key: 'notifications', label: t('group_notifications', 'Notifications') },
      { key: 'scan', label: t('group_scan', 'Scan') },
      { key: 'spacing', label: t('group_spacing', 'Spacing') },
      { key: 'controls', label: t('group_controls', 'Controls') },
      { key: 'radius', label: t('group_radius', 'Radius') },
      { key: 'typography', label: t('group_typography', 'Typography') },
      { key: 'cards', label: t('group_cards', 'Cards') },
      { key: 'chrome-shell', label: t('group_chrome_shell', 'Chrome/Shell') },
      { key: 'tables', label: t('group_tables', 'Tables') },
      { key: 'transitions-shadows', label: t('group_transitions_shadows', 'Transitions/Shadows') },
      { key: 'other', label: t('group_other', 'Other') }
    ];
  }

  function shouldIncludeGroup(groupKey) {
    if (state.filter === 'all') return true;
    if (state.filter === 'frequent') return groupKey === 'frequent';
    if (state.filter === 'colors') return groupKey === 'colors' || groupKey === 'semantic-colors';
    if (state.filter === 'advanced') {
      return groupKey !== 'frequent' && groupKey !== 'colors' && groupKey !== 'semantic-colors';
    }
    return true;
  }

  function tokenMatchesSearch(name, value) {
    var q = state.search.trim().toLowerCase();
    if (q === '') return true;
    return normalizeTokenName(name).indexOf(q) !== -1 || String(value || '').toLowerCase().indexOf(q) !== -1;
  }

  function setSummaryCount(changedCount, totalCount, inheritedCount) {
    if (!summaryTokens) return;
    if (totalCount === 0) {
      summaryTokens.textContent = t('summary_tokens_ready', '0 ready');
      return;
    }
    if (changedCount > 0) {
      summaryTokens.textContent = changedCount + ' / ' + totalCount + ' ' + t('summary_tokens_modified_suffix', 'modified');
      return;
    }
    if (inheritedCount > 0) {
      summaryTokens.textContent = totalCount + ' ' + t('summary_tokens_ready', 'ready') + ' / ' + inheritedCount + ' ' + t('summary_tokens_inherited_suffix', 'inherited');
      return;
    }
    summaryTokens.textContent = totalCount + ' ' + t('summary_tokens_ready', 'ready');
  }

  function getSelectedTokens() {
    var values = {};
    for (var key in tokenValues) {
      if (tokenValues.hasOwnProperty(key)) {
        values[key] = tokenValues[key];
      }
    }
    return values;
  }

  function applyPreview() {
    var tokens = getSelectedTokens();
    if (previewWindow && !previewWindow.closed) {
      previewWindow.postMessage({
        type: 'cte-preview-tokens',
        tokens: tokens
      }, window.location.origin);
    }
  }

  function openPreviewTab() {
    var previewUrl = testBtn
      ? (testBtn.getAttribute('data-cte-preview-url') || '/apps/studio/tools/customization-studio/design-system/tokens/preview')
      : '/apps/studio/tools/customization-studio/design-system/tokens/preview';
    previewWindow = window.open(previewUrl, 'cte-live-preview');
    if (previewWindow) {
      previewWindow.focus();
      window.setTimeout(applyPreview, 250);
    }
  }

  window.addEventListener('message', function(event) {
    if (event.origin !== window.location.origin) return;
    if (!event.data || event.data.type !== 'cte-preview-ready') return;
    if (!previewWindow || event.source !== previewWindow) return;
    applyPreview();
  });

  function updateTokenStatus() {
    var changedCount = 0;
    var totalCount = 0;
    var rows = [];

    if (state.mode === 'simple') {
      var db = document.querySelector('[data-cte-simple-dashboard]');
      if (db) rows = db.querySelectorAll('.cte-token-row');
    } else if (tokenContainer) {
      rows = tokenContainer.querySelectorAll('.cte-token-row');
    }

    rows.forEach(function(row) {
        var name = row.getAttribute('data-cte-token-name');
        var input = row.querySelector('[data-cte-token-input]');
        var status = row.querySelector('[data-cte-token-status]');
        var original = tokenData[name] || '';
        if (input) {
          var changed = input.value !== original;
          input.classList.toggle('is-changed', changed);
          if (!status && changed) {
            var info = row.querySelector('.cte-token-info');
            if (info) {
              status = document.createElement('span');
              status.className = 'cte-token-status';
              status.setAttribute('data-cte-token-status', '');
              info.insertBefore(status, info.firstChild);
            }
          }
          if (status) {
            status.textContent = changed ? t('token_info_changed', 'modified') : t('token_info_unchanged', 'unchanged');
            status.className = 'cte-token-status ' + (changed ? 'is-changed' : 'is-unchanged');
            if (changed) changedCount++;
          }
        }
        totalCount++;
      });

    setSummaryCount(changedCount, totalCount);
    var safety = evaluateSaveSafety();
    var safetyDiff = summarizeSafetyAgainstBaseline(safety);
    renderSafetyPanel(safety, safetyDiff);
    updateSaveButton(changedCount, safety, safetyDiff);
    renderDiffPanel();
  }

  function updateSaveButton(changedCount, safety, safetyDiff) {
    if (!saveBtn) return;
    var diff = safetyDiff || summarizeSafetyAgainstBaseline(safety);
    var blockedForSafety = !!(state.mode !== 'advanced' && diff.newSevereCount > 0);
    if (changedCount > 0 && !blockedForSafety) {
      saveBtn.removeAttribute('disabled');
      saveBtn.classList.remove('is-disabled');
    } else {
      saveBtn.setAttribute('disabled', 'disabled');
      saveBtn.classList.add('is-disabled');
    }
  }

  function summarizeSafetyIssues(issues) {
    var counts = { severe: 0, warning: 0, ok: 0 };
    for (var i = 0; i < issues.length; i++) {
      var issue = issues[i];
      if (issue && counts.hasOwnProperty(issue.status)) {
        counts[issue.status]++;
      }
    }
    return counts;
  }

  function safetyIssueKey(issue) {
    if (!issue) return '';
    return String(issue.token || '') + '|' + String(issue.bgToken || issue.bg_token || '');
  }

  function summarizeSafetyAgainstBaseline(safety) {
    var currentIssues = safety && Array.isArray(safety.issues) ? safety.issues : [];
    var baselineIssues = state.baselineSafety && Array.isArray(state.baselineSafety.issues)
      ? state.baselineSafety.issues
      : [];

    var baselineSevere = {};
    for (var bi = 0; bi < baselineIssues.length; bi++) {
      var baseIssue = baselineIssues[bi];
      if (!baseIssue || baseIssue.status !== 'severe') continue;
      var baseKey = safetyIssueKey(baseIssue);
      if (baseKey !== '') baselineSevere[baseKey] = true;
    }

    var currentSevereCount = 0;
    var newSevereCount = 0;
    var newSevereIssues = [];
    for (var ci = 0; ci < currentIssues.length; ci++) {
      var currentIssue = currentIssues[ci];
      if (!currentIssue || currentIssue.status !== 'severe') continue;
      currentSevereCount++;
      var currentKey = safetyIssueKey(currentIssue);
      if (currentKey === '' || baselineSevere[currentKey]) continue;
      newSevereCount++;
      newSevereIssues.push(currentIssue);
    }

    var baselineSevereCount = Object.keys(baselineSevere).length;
    var existingSevereCount = currentSevereCount - newSevereCount;
    if (existingSevereCount < 0) existingSevereCount = 0;

    return {
      baselineSevereCount: baselineSevereCount,
      currentSevereCount: currentSevereCount,
      existingSevereCount: existingSevereCount,
      newSevereCount: newSevereCount,
      newSevereIssues: newSevereIssues
    };
  }

  function getSafetyThresholds(kind) {
    if (kind === 'border') {
      return { warning: 1.5, severe: 1.2 };
    }
    return { warning: 4.5, severe: 3.0 };
  }

  function getSafetyPair(name, values) {
    var n = normalizeTokenName(name);
    var bgToken = null;
    var kind = 'text';
    var label = '';

    if (n === 'text' || n === 'color-text') {
      bgToken = values.bg !== undefined ? 'bg' : (values.background !== undefined ? 'background' : null);
      label = 'Text on Background';
    } else if (n === 'muted' || n === 'color-muted' || n === 'color-text-muted') {
      bgToken = values.bg !== undefined ? 'bg' : (values.background !== undefined ? 'background' : null);
      label = 'Muted on Background';
    } else if (n === 'accent' || n === 'color-accent') {
      bgToken = values.bg !== undefined ? 'bg' : (values.background !== undefined ? 'background' : null);
      label = 'Accent on Background';
    } else if (/^tone-([a-z0-9_-]+)-text$/.test(n)) {
      var toneMatch = n.match(/^tone-([a-z0-9_-]+)-text$/);
      bgToken = toneMatch ? 'tone-' + toneMatch[1] + '-bg' : null;
      label = toneMatch ? toneMatch[1].charAt(0).toUpperCase() + toneMatch[1].slice(1) + ' tone' : '';
    } else if (/^notif-chip-([a-z0-9_-]+)-color$/.test(n)) {
      var notifMatch = n.match(/^notif-chip-([a-z0-9_-]+)-color$/);
      bgToken = notifMatch ? 'notif-chip-' + notifMatch[1] + '-bg' : null;
      label = notifMatch ? 'Notif: ' + notifMatch[1] : '';
    } else if (/^(.*)-(text|label|copy)$/.test(n)) {
      var textMatch = n.match(/^(.*)-(text|label|copy)$/);
      bgToken = textMatch ? textMatch[1] + '-bg' : null;
      label = textMatch ? friendlyTokenLabel(textMatch[1]) : '';
    } else if (/^(success|warning|danger|info)$/.test(n)) {
      bgToken = values[n + '-bg'] !== undefined ? n + '-bg' : (values.bg !== undefined ? 'bg' : (values.background !== undefined ? 'background' : null));
      kind = 'status';
      label = n.charAt(0).toUpperCase() + n.slice(1) + ' state';
    } else if (/^(.*)-(border|line|edge|focus|ring|outline)$/.test(n)) {
      bgToken = values.bg !== undefined ? 'bg' : (values.background !== undefined ? 'background' : null);
      kind = 'border';
      label = friendlyTokenLabel(n.replace(/-(border|line|edge|focus|ring|outline)$/, '')) || friendlyTokenLabel(n);
    }

    if (!bgToken || values[bgToken] === undefined) return null;
    return { bgToken: bgToken, kind: kind, label: label || friendlyTokenLabel(name) };
  }

  function evaluateSaveSafety(values) {
    var sourceValues = values && typeof values === 'object' ? values : effectiveTokenData;
    var issues = [];
    var overall = 'ok';
    var seen = {};

    for (var name in sourceValues) {
      if (!sourceValues.hasOwnProperty(name)) continue;
      if (seen[name]) continue;
      var pair = getSafetyPair(name, sourceValues);
      if (!pair) continue;
      var sourceValue = sourceValues[name];
      var bgValue = sourceValues[pair.bgToken];
      if (sourceValue === undefined || bgValue === undefined) continue;

      var result = computeReadability(sourceValue, bgValue, sourceValues);
      var thresholds = getSafetyThresholds(pair.kind);
      var status = 'warning';
      var ratio = result.ratio;

      if (result.status === 'good' && ratio !== null && ratio !== undefined) {
        status = 'ok';
      } else if (result.status === 'low' && ratio !== null && ratio !== undefined) {
        status = ratio >= thresholds.warning ? 'warning' : 'severe';
      } else {
        status = 'warning';
      }

      if (status === 'severe') {
        overall = 'severe';
      } else if (overall !== 'severe' && status === 'warning') {
        overall = 'warning';
      }

      issues.push({
        token: name,
        bgToken: pair.bgToken,
        label: pair.label,
        kind: pair.kind,
        ratio: ratio,
        status: status,
        sourceValue: sourceValue,
        bgValue: bgValue
      });
      seen[name] = true;
    }

    return { overall: overall, issues: issues };
  }

  function renderSafetyPanel(safety, safetyDiff) {
    if (!safetyPanel) return;
    var diff = safetyDiff || summarizeSafetyAgainstBaseline(safety);
    var overall = safety && safety.overall ? safety.overall : 'ok';
    var issues = safety && Array.isArray(safety.issues) ? safety.issues : [];
    var counts = summarizeSafetyIssues(issues);
    var html = '';
    var summaryText = [];

    if (counts.severe > 0) {
      summaryText.push(counts.severe + ' ' + t('validation_severe', 'Severe'));
    }
    if (counts.warning > 0) {
      summaryText.push(counts.warning + ' ' + t('validation_warning', 'Warning'));
    }
    if (summaryText.length === 0) {
      summaryText.push(t('safety_panel_no_issues', 'No visibility issues detected before save.'));
    }

    html += '<div class="cte-safety-panel__header">';
    html += '  <span class="cte-safety-panel__title">' + escapeHtml(t('safety_panel_title', 'Pre-save visibility')) + '</span>';
    html += '  <span class="cte-safety-panel__pill is-' + escapeHtml(overall) + '">' + escapeHtml(overall === 'ok' ? t('safety_panel_ok', 'OK') : overall === 'warning' ? t('safety_panel_warning', 'Warning') : t('safety_panel_severe', 'Severe')) + '</span>';
    html += '</div>';

    html += '<div class="cte-safety-panel__summary-line">';
    html += '  <span class="cte-safety-panel__summary-count">' + escapeHtml(summaryText.join(' / ')) + '</span>';
    html += '</div>';

    if (diff.baselineSevereCount > 0) {
      html += '<div class="cte-safety-panel__summary-line">';
      html += '  <span class="cte-safety-panel__summary-count">' + escapeHtml(t('safety_existing_debt_summary', 'Existing severe in this block: ') + String(diff.baselineSevereCount)) + '</span>';
      html += '</div>';
    }

    if (issues.length > 0 && state.mode === 'advanced') {
      html += '<button type="button" class="cte-safety-panel__toggle" data-cte-safety-toggle aria-expanded="' + (state.safetyExpanded ? 'true' : 'false') + '">';
      html += '  <span>' + escapeHtml(state.safetyExpanded ? t('safety_details_hide', 'Hide details') : t('safety_details_show', 'Review issues')) + '</span>';
      html += '</button>';
      html += '<div class="cte-safety-panel__details" data-cte-safety-details' + (state.safetyExpanded ? '' : ' hidden') + '>';
      html += '  <p class="cte-safety-panel__summary">' + escapeHtml(t('safety_panel_review', 'Review these visibility checks before saving.')) + '</p>';
      html += '  <div class="cte-safety-panel__list">';
      for (var i = 0; i < issues.length; i++) {
        var issue = issues[i];
        var ratioText = issue.ratio === null || issue.ratio === undefined ? t('safety_unknown', 'unknown') : issue.ratio.toFixed(1) + ':1';
        html += '<div class="cte-safety-panel__issue is-' + escapeHtml(issue.status) + '">';
        html += '  <div class="cte-safety-panel__issue-head">';
        html += '    <strong>' + escapeHtml(issue.label || issue.token) + '</strong>';
        html += '    <span>' + escapeHtml(t('safety_issue_pair', 'Pair') + ': --' + issue.token + ' / --' + issue.bgToken) + '</span>';
        html += '  </div>';
        html += '  <div class="cte-safety-panel__issue-meta">';
        html += '    <span>' + escapeHtml(t('safety_issue_ratio', 'Contrast ratio') + ': ' + ratioText) + '</span>';
        html += '    <span>' + escapeHtml(issue.status === 'ok' ? t('validation_ok', 'OK') : issue.status === 'warning' ? t('validation_warning', 'Warning') : t('validation_severe', 'Severe')) + '</span>';
        html += '  </div>';
        html += '</div>';
      }
      html += '  </div>';
      if (overall === 'severe') {
        html += '  <p class="cte-safety-panel__note is-severe">' + escapeHtml(t('safety_issue_simple_blocked', 'Simple mode saves are blocked until severe visibility issues are fixed.')) + '</p>';
        html += '  <p class="cte-safety-panel__note is-severe">' + escapeHtml(t('safety_issue_override_hint', 'Developer mode may save anyway only after explicit confirmation.')) + '</p>';
      } else if (overall === 'warning') {
        html += '  <p class="cte-safety-panel__note is-warning">' + escapeHtml(t('safety_panel_on', 'Warning-level issues are allowed with confirmation.')) + '</p>';
      }
      html += '</div>';
    } else if (issues.length > 0) {
      html += '<p class="cte-safety-panel__summary">' + escapeHtml(t('safety_panel_review', 'Review visibility checks before saving.')) + '</p>';
      if (diff.newSevereCount > 0) {
        html += '<p class="cte-safety-panel__note is-severe">' + escapeHtml(t('safety_issue_simple_blocked_new', 'Simple mode save is blocked because your current edits introduced new severe visibility issues.')) + '</p>';
        html += '<div class="cte-safety-panel__list">';
        for (var si = 0; si < diff.newSevereIssues.length; si++) {
          var blockingIssue = diff.newSevereIssues[si];
          var blockingRatio = blockingIssue.ratio === null || blockingIssue.ratio === undefined
            ? t('safety_unknown', 'unknown')
            : blockingIssue.ratio.toFixed(1) + ':1';
          html += '<div class="cte-safety-panel__issue is-severe">';
          html += '  <div class="cte-safety-panel__issue-head">';
          html += '    <strong>' + escapeHtml(blockingIssue.label || blockingIssue.token) + '</strong>';
          html += '    <span>' + escapeHtml(t('safety_issue_pair', 'Pair') + ': --' + blockingIssue.token + ' / --' + blockingIssue.bgToken) + '</span>';
          html += '  </div>';
          html += '  <div class="cte-safety-panel__issue-meta">';
          html += '    <span>' + escapeHtml(t('safety_issue_ratio', 'Contrast ratio') + ': ' + blockingRatio) + '</span>';
          html += '    <span>' + escapeHtml(t('validation_severe', 'Severe')) + '</span>';
          html += '  </div>';
          html += '</div>';
        }
        html += '</div>';
      } else if (overall === 'severe') {
        html += '<p class="cte-safety-panel__note is-warning">' + escapeHtml(t('safety_existing_debt_note', 'This block already has severe visibility debt. Your current changes did not add new severe issues.')) + '</p>';
      } else if (overall === 'warning') {
        html += '<p class="cte-safety-panel__note is-warning">' + escapeHtml(t('safety_panel_on', 'Warning-level issues are allowed with confirmation.')) + '</p>';
      }
    } else {
      html += '<p class="cte-safety-panel__summary is-ok">' + escapeHtml(t('safety_panel_no_issues', 'No visibility issues detected before save.')) + '</p>';
    }

    safetyPanel.className = 'cte-safety-panel is-' + overall;
    safetyPanel.innerHTML = html;
    safetyPanel.style.display = '';
  }

  if (safetyPanel) {
    safetyPanel.addEventListener('click', function(event) {
      var toggle = event.target && event.target.closest ? event.target.closest('[data-cte-safety-toggle]') : null;
      if (!toggle) return;
      state.safetyExpanded = !state.safetyExpanded;
      var liveSafety = evaluateSaveSafety();
      renderSafetyPanel(liveSafety, summarizeSafetyAgainstBaseline(liveSafety));
    });
  }

  function renderDiffPanel() {
    if (!diffPanel || !diffCount || !diffList) return;
    var lines = [];
    var changedCount = 0;
    for (var key in tokenData) {
      if (!tokenData.hasOwnProperty(key)) continue;
      var current = tokenValues[key] !== undefined ? tokenValues[key] : tokenData[key];
      if (current !== tokenData[key]) {
        lines.push(escapeHtml('--' + key + ': ' + tokenData[key] + '  →  ' + current));
        changedCount++;
      }
    }
    if (changedCount > 0) {
      if (diffTitle) diffTitle.textContent = t('diff_title', 'Token Editor');
      diffPanel.style.display = '';
      diffCount.textContent = changedCount + ' ' + t('summary_tokens_modified_suffix', 'modified');
      diffList.innerHTML = lines.map(function(l) {
        return '<div class="cte-diff-row"><code>' + l + '</code></div>';
      }).join('');
    } else {
      if (diffTitle) diffTitle.textContent = t('diff_title_empty', 'Token Editor');
      diffPanel.style.display = 'none';
      diffCount.textContent = t('diff_no_changes', 'No pending changes');
    }
  }

  function collectVisibleRows() {
    var rows = [];
    for (var key in tokenData) {
      if (!tokenData.hasOwnProperty(key)) continue;
      var value = touchedTokens[key] && tokenValues[key] !== undefined ? tokenValues[key] : (tokenData[key] || '');
      var original = tokenData[key];
      if (state.filter === 'changed' && value === original) continue;
      if (!tokenMatchesSearch(key, value)) continue;
      rows.push({
        name: key,
        value: value,
        original: original,
        group: categoryForToken(key),
        inherited: false
      });
    }
    if (state.filter !== 'changed') {
      for (var effectiveKey in effectiveTokenData) {
        if (!effectiveTokenData.hasOwnProperty(effectiveKey) || tokenData.hasOwnProperty(effectiveKey)) continue;
        var inheritedValue = effectiveTokenData[effectiveKey];
        if (inheritedValue === undefined || inheritedValue === null || String(inheritedValue).trim() === '') continue;
        if (!tokenMatchesSearch(effectiveKey, inheritedValue)) continue;
        rows.push({
          name: effectiveKey,
          value: inheritedValue,
          original: inheritedValue,
          group: categoryForToken(effectiveKey),
          inherited: true
        });
      }
    }
    return rows;
  }

  function renderRows(rows) {
    var meta = groupMeta();
    var grouped = {};
    var changedCount = 0;

    for (var i = 0; i < meta.length; i++) {
      grouped[meta[i].key] = [];
    }

    for (var r = 0; r < rows.length; r++) {
      var row = rows[r];
      if (!grouped[row.group]) grouped[row.group] = [];
      grouped[row.group].push(row);
      if (!row.inherited && row.value !== row.original) changedCount++;
    }

    var html = '<div class="cte-token-groups">';
    var anyRows = false;

    for (var g = 0; g < meta.length; g++) {
      var group = meta[g];
      if (!shouldIncludeGroup(group.key)) continue;

      var items = grouped[group.key] || [];
      if (items.length === 0) continue;

      var grpChangedCount = 0;
      var grpInheritedCount = 0;
      for (var gi = 0; gi < items.length; gi++) {
        if (items[gi].inherited) {
          grpInheritedCount++;
        } else if (items[gi].value !== items[gi].original) {
          grpChangedCount++;
        }
      }

      anyRows = true;
      if (groupState[group.key] === undefined) {
        groupState[group.key] = group.key === 'frequent' || grpChangedCount > 0;
      }

      html += '<section class="cte-token-group' + (grpChangedCount > 0 ? ' has-changes' : '') + '" data-cte-group="' + escapeHtml(group.key) + '">';
      html += '  <button type="button" class="cte-group-toggle" data-cte-group-toggle="' + escapeHtml(group.key) + '" aria-expanded="' + (groupState[group.key] ? 'true' : 'false') + '">';
      html += '    <span class="cte-group-title">' + escapeHtml(group.label) + '</span>';
      html += '    <span class="cte-group-meta">';
      if (grpChangedCount > 0) {
        html += '      <span class="cte-group-count is-changed">' + grpChangedCount + '</span>';
      }
      if (grpInheritedCount > 0) {
        html += '      <span class="cte-group-count is-inherited">' + grpInheritedCount + '</span>';
      }
      html += '      <span class="cte-group-count">' + items.length + '</span>';
      html += '      <span class="cte-group-chevron" aria-hidden="true">' + (groupState[group.key] ? '▾' : '▸') + '</span>';
      html += '    </span>';
      html += '  </button>';
      html += '  <div class="cte-group-body"' + (groupState[group.key] ? '' : ' hidden') + '>';

      var hasSearch = state.search.trim() !== '';
      var isAdvanced = state.mode === 'advanced';
      var selLabel = isAdvanced ? getCurrentSelectorLabel() : '';
      var selIndex = isAdvanced ? getCurrentBlockIndex() : '';
      for (var j = 0; j < items.length; j++) {
        var item = items[j];
        var isInherited = !!item.inherited;
        var isChanged = !isInherited && item.value !== item.original;
        var isSearchMatch = hasSearch && tokenMatchesSearch(item.name, item.value);
        var itemIsColor = couldBeColor(item.value, effectiveTokenData) || couldBeColor(item.original, effectiveTokenData);
        if (itemIsColor) {
          var friendly = friendlyTokenLabel(item.name);
          var cat = colorCategory(item.name);
          var resolved = resolveVarValue(item.value, effectiveTokenData);
          var swatchVal = resolved || item.value;
          var detailsBtnText = isAdvanced ? '▾' : '▸';
          var purpose = getTokenPurpose(item.name);
          var resolvedColor = resolved && isColorValue(resolved) ? resolved : (isColorValue(item.value) ? item.value : null);
          var colorName = resolvedColor ? getColorName(resolvedColor) : '';
          var varChain = getVarChainLabel(item.value, effectiveTokenData);
          var pickerHex = resolvedColor ? expandHex(resolvedColor) : null;
          var canUsePicker = !!pickerHex;
          var displayHex = pickerHex || '';
          html += '<div class="cte-token-row cte-token-row--color' + (isSearchMatch ? ' is-search-match' : '') + (isInherited ? ' is-inherited' : '') + '" data-cte-token-name="' + escapeHtml(item.name) + '">';
          html += '  <div class="cte-color-summary">';
          html += '    <span class="cte-color-swatch" style="background:' + escapeHtml(swatchVal) + '"></span>';
          html += '    <div class="cte-color-summary-info">';
          html += '      <span class="cte-color-label">' + escapeHtml(friendly) + '</span>';
          if (purpose) {
            html += '      <span class="cte-color-purpose">' + escapeHtml(purpose) + '</span>';
          }
          if (isAdvanced) {
            html += '      <span class="cte-color-raw" data-cte-color-raw>' + escapeHtml(item.value) + '</span>';
          }
          if (isAdvanced && varChain) {
            html += '      <span class="cte-color-var-chain">' + escapeHtml(varChain) + '</span>';
          } else if (isAdvanced && resolved) {
            html += '      <span class="cte-color-resolved" data-cte-color-resolved>→ ' + escapeHtml(resolved) + '</span>';
          }
          if (colorName && isAdvanced) {
            html += '      <span class="cte-color-name" data-cte-color-name>' + escapeHtml(t('current_prefix', 'Current') + ': ' + colorName) + '</span>';
          }
          html += '    </div>';
          if (isAdvanced) {
            html += '    <span class="cte-color-cat cte-color-cat--' + cat + '">' + escapeHtml(t('color_cat_' + cat, COLOR_CATEGORIES[cat])) + '</span>';
            html += '    <button type="button" class="cte-color-details-btn is-expanded" data-cte-color-details title="' + escapeHtml(t('color_details_title', 'Show advanced details')) + '">' + escapeHtml(t('color_details', 'Details')) + ' ' + detailsBtnText + '</button>';
          }
          html += '  </div>';
          if (isAdvanced) {
            html += '  <div class="cte-color-readability" data-cte-readability style="display:none">';
            html += '    <span class="cte-readability-indicator is-unknown" data-cte-readability-indicator><span class="cte-readability-dot is-unknown"></span><span class="cte-readability-label">' + escapeHtml(t('readability_unknown', 'Check manually')) + '</span></span>';
            html += '  </div>';
          }
          if (isAdvanced) {
            html += '  <div class="cte-color-advanced">';
            html += '    <div class="cte-token-header">';
            html += '      <span class="cte-token-name">--' + escapeHtml(item.name) + '</span>';
            html += '      <span class="cte-token-original" title="' + escapeHtml(item.original) + '">' + escapeHtml(item.original) + '</span>';
            html += '    </div>';
            if (varChain) {
              html += '    <div class="cte-token-meta">';
              html += '      <span class="cte-token-resolved">' + escapeHtml(t('resolved_label', 'Resolved') + ': ' + (resolved || item.value)) + '</span>';
              html += '    </div>';
            }
            if (selLabel) {
              html += '    <div class="cte-token-meta">';
              html += '      <span class="cte-token-selector">' + escapeHtml(selLabel) + (selIndex ? ' :: block ' + selIndex : '') + '</span>';
              html += '    </div>';
            }
            html += '  </div>';
          }
          html += '  <div class="cte-color-inputs">';
          if (canUsePicker) {
            html += '    <input class="cte-color-picker" ' + (isInherited ? '' : 'data-cte-color-picker ') + 'type="color" value="' + escapeHtml(displayHex) + '" title="' + escapeHtml(t('color_picker_title', 'Pick a color')) + '"' + (isInherited ? ' disabled aria-disabled="true"' : '') + '>';
          }
          html += '    <input class="cte-token-input' + (isChanged ? ' is-changed' : '') + (isInherited ? ' is-inherited' : '') + (item.value === '' && !isInherited ? ' is-empty' : '') + '" ' + (isInherited ? '' : 'data-cte-token-input ') + 'type="text" value="' + escapeHtml(item.value) + '"' + (item.value === '' && !isInherited ? ' placeholder="(empty)"' : '') + ' spellcheck="false"' + (isInherited ? ' disabled aria-disabled="true" title="' + escapeHtml(t('token_inherited_note', 'Inherited from the active cascade. Edit the source block that defines this token.')) + '"' : '') + '>';
          html += '  </div>';
          html += '  <div class="cte-token-info">';
          if (isInherited) {
            html += '    <span class="cte-token-status is-inherited">' + escapeHtml(t('token_info_inherited', 'Inherited / not editable here')) + '</span>';
          }
          if (isAdvanced || isChanged) {
            html += '    <span class="cte-token-status ' + (isChanged ? 'is-changed' : 'is-unchanged') + '" data-cte-token-status>' + escapeHtml(isChanged ? t('token_info_changed', 'modified') : t('token_info_unchanged', 'unchanged')) + '</span>';
          }
          if (isAdvanced) {
            html += '    <span class="cte-token-selector-badge">' + escapeHtml(selLabel ? selLabel + ' #' + selIndex : '') + '</span>';
          }
          html += '  </div>';
          html += '</div>';
        } else {
          html += '<div class="cte-token-row' + (isSearchMatch ? ' is-search-match' : '') + (isInherited ? ' is-inherited' : '') + (item.value === '' ? ' is-empty' : '') + '" data-cte-token-name="' + escapeHtml(item.name) + '">';
          html += '  <div class="cte-token-header">';
          if (isAdvanced) {
            html += '    <span class="cte-token-name">--' + escapeHtml(item.name) + '</span>';
          } else {
            html += '    <span class="cte-token-name">' + escapeHtml(friendlyTokenLabel(item.name)) + '</span>';
            var simplePurpose = getTokenPurpose(item.name);
            if (simplePurpose) {
              html += '    <span class="cte-color-purpose">' + escapeHtml(simplePurpose) + '</span>';
            }
          }
          if (isAdvanced) {
            html += '    <span class="cte-token-original" title="' + escapeHtml(item.original) + '">' + escapeHtml(item.original) + '</span>';
          }
          html += '  </div>';
          html += '  <input class="cte-token-input' + (isChanged ? ' is-changed' : '') + (isInherited ? ' is-inherited' : '') + (item.value === '' && !isInherited ? ' is-empty' : '') + '" ' + (isInherited ? '' : 'data-cte-token-input ') + 'type="text" value="' + escapeHtml(item.value) + '"' + (item.value === '' && !isInherited ? ' placeholder="(empty)"' : '') + ' spellcheck="false"' + (isInherited ? ' disabled aria-disabled="true" title="' + escapeHtml(t('token_inherited_note', 'Inherited from the active cascade. Edit the source block that defines this token.')) + '"' : '') + '>';
          html += '  <div class="cte-token-info">';
          if (isInherited) {
            html += '    <span class="cte-token-status is-inherited">' + escapeHtml(t('token_info_inherited', 'Inherited / not editable here')) + '</span>';
          }
          if (isAdvanced || isChanged) {
            html += '    <span class="cte-token-status ' + (isChanged ? 'is-changed' : 'is-unchanged') + '" data-cte-token-status>' + escapeHtml(isChanged ? t('token_info_changed', 'modified') : t('token_info_unchanged', 'unchanged')) + '</span>';
          }
          if (isAdvanced) {
            html += '    <span class="cte-token-selector-badge">' + escapeHtml(selLabel ? selLabel + ' #' + selIndex : '') + '</span>';
          }
          html += '  </div>';
          html += '</div>';
        }
      }

      html += '  </div>';
      html += '</section>';
    }

    html += '</div>';

    if (!anyRows) {
      tokenContainer.innerHTML = '<div class="cte-empty"><strong>' + escapeHtml(t('no_tokens', 'No tokens')) + '</strong><span>' + escapeHtml(t('no_tokens_desc', 'No tokens available for this block.')) + '</span></div>';
      setSummaryCount(0, 0, 0);
      if (diffTitle) diffTitle.textContent = t('diff_title_empty', 'Token Editor');
      return;
    }

    tokenContainer.innerHTML = html;
    var inheritedCount = rows.filter(function(row) { return row.inherited; }).length;
    setSummaryCount(changedCount, rows.length - inheritedCount, inheritedCount);
    var allRows = tokenContainer.querySelectorAll('[data-cte-readability]');
    for (var ri = 0; ri < allRows.length; ri++) {
      updateTokenReadability(allRows[ri].closest('[data-cte-token-name]') || allRows[ri].parentNode, effectiveTokenData);
    }
  }

  function preserveSimpleRightNodes() {
    var nodes = {};
    var names = {
      safety: '[data-cte-safety-panel]',
      actions: '.cte-preview-action-row',
      verify: '[data-cte-verify-results]',
      summary: '.cte-summary-grid'
    };
    for (var key in names) {
      if (!names.hasOwnProperty(key)) continue;
      var node = document.querySelector(names[key]);
      if (node && node.parentNode) {
        nodes[key] = node;
        node.parentNode.removeChild(node);
      }
    }
    return nodes;
  }

  function restoreSimpleRightNodes(nodes) {
    if (!nodes) return;
    var rightCol = document.querySelector('[data-cte-simple-right]');
    if (!rightCol) return;
    ['safety', 'verify', 'summary', 'actions'].forEach(function(key) {
      if (nodes[key]) rightCol.appendChild(nodes[key]);
    });
  }

  function setupSimpleLayout() {
    try {
      var ws = document.querySelector('.cte-workspace');
      var rightCol = document.querySelector('[data-cte-simple-right]');
      if (!rightCol) return;
      var safety = document.querySelector('[data-cte-safety-panel]');
      if (safety && safety.parentNode !== rightCol) rightCol.appendChild(safety);
      var verify = document.querySelector('[data-cte-verify-results]');
      if (verify && verify.parentNode !== rightCol) rightCol.appendChild(verify);
      var summary = document.querySelector('.cte-summary-grid');
      if (summary && summary.parentNode !== rightCol) rightCol.appendChild(summary);
      var actions = document.querySelector('.cte-preview-action-row');
      if (actions && actions.parentNode !== rightCol) rightCol.appendChild(actions);
      if (ws) ws.setAttribute('data-mode', 'simple');
    } catch(e) {}
  }

  function teardownSimpleLayout() {
    try {
      var ws = document.querySelector('.cte-workspace');
      var editorCard = document.querySelector('.cte-card--editor');
      var safety = document.querySelector('[data-cte-safety-panel]');
      var actions = document.querySelector('.cte-preview-action-row');
      var verify = document.querySelector('[data-cte-verify-results]');
      var summary = document.querySelector('.cte-summary-grid');
      if (verify && editorCard && verify.parentNode !== editorCard) {
        var safetyRef = editorCard.querySelector('[data-cte-safety-panel]');
        if (safetyRef) {
          editorCard.insertBefore(verify, safetyRef);
        } else {
          editorCard.appendChild(verify);
        }
      }
      if (safety && editorCard && safety.parentNode !== editorCard) {
        var tokens = editorCard.querySelector('[data-cte-tokens]');
        if (tokens && tokens.nextSibling) {
          editorCard.insertBefore(safety, tokens.nextSibling);
        } else {
          editorCard.appendChild(safety);
        }
      }
      if (summary && editorCard && summary.parentNode !== editorCard) {
        editorCard.appendChild(summary);
      }
      if (actions && editorCard && actions.parentNode !== editorCard) {
        var sib = editorCard.querySelector('[data-cte-safety-panel]') || editorCard.querySelector('[data-cte-tokens]');
        if (sib && sib.nextSibling) {
          editorCard.insertBefore(actions, sib.nextSibling);
        } else {
          editorCard.appendChild(actions);
        }
      }
      if (ws) ws.removeAttribute('data-mode');
    } catch(e) {}
  }

  function renderTokens() {
    var isSimple = state.mode === 'simple';
    var standardTokens = document.querySelector('[data-cte-tokens]');
    var dashboard = document.querySelector('[data-cte-simple-dashboard]');
    var editorCard = document.querySelector('.cte-card--editor');
    if (isSimple) {
      if (dashboard) dashboard.style.display = '';
      if (standardTokens) standardTokens.style.display = 'none';
      if (editorCard) editorCard.style.display = 'none';
      renderSimpleModeDashboard();
      setupSimpleLayout();
    } else {
      if (dashboard) dashboard.style.display = 'none';
      if (standardTokens) standardTokens.style.display = '';
      if (editorCard) editorCard.style.display = '';
      teardownSimpleLayout();
      renderRows(collectVisibleRows());
    }
    applyPreview();
    updateModeGuide();
    updateTokenStatus();
  }

  function loadTokensForSelector(key) {
    if (!tokenContainer) return;
    state.safetyExpanded = false;

    var tokens = getTokensFromParsedCss(key);

    if (!tokens) {
      var selected = getSelectorEntry(key);
      if (selected && selected.tokens) {
        tokens = selected.tokens;
      }
    }

    if (!tokens || Object.keys(tokens).length === 0) {
      tokenContainer.innerHTML = '<div class="cte-empty"><strong>' + escapeHtml(t('no_tokens', 'No tokens')) + '</strong><span>' + escapeHtml(t('no_tokens_desc', 'No tokens available for this block.')) + '</span></div>';
      state.baselineSafety = null;
      applyPreview();
      if (summarySelection) summarySelection.textContent = t('summary_no_selection', 'No block selected');
      if (summarySourceFile) summarySourceFile.textContent = t('summary_source_file_unknown', '/resources/themes/*');
      if (summarySourceLayer) summarySourceLayer.textContent = t('summary_source_layer_unknown', 'Unknown');
      setSummaryCount(0, 0);
      if (diffTitle) diffTitle.textContent = t('diff_title_empty', 'Token Editor');
      return;
    }

    for (var existing in tokenData) {
      if (tokenData.hasOwnProperty(existing)) {
        delete tokenData[existing];
      }
    }
    for (var current in tokenValues) {
      if (tokenValues.hasOwnProperty(current)) {
        delete tokenValues[current];
      }
    }
    for (var effectiveName in effectiveTokenData) {
      if (effectiveTokenData.hasOwnProperty(effectiveName)) {
        delete effectiveTokenData[effectiveName];
      }
    }
    for (var tt in touchedTokens) {
      if (touchedTokens.hasOwnProperty(tt)) {
        delete touchedTokens[tt];
      }
    }
    for (var grp in groupState) {
      if (groupState.hasOwnProperty(grp)) {
        delete groupState[grp];
      }
    }

    var tokenKeys = Object.keys(tokens);
    for (var ti = 0; ti < tokenKeys.length; ti++) {
      var name = tokenKeys[ti];
      var value = tokens[name];
      tokenData[name] = value;
      tokenValues[name] = value;
    }
    var effectiveTokens = getEffectiveTokensForSelector(key, tokens);
    for (var effectiveKey in effectiveTokens) {
      if (effectiveTokens.hasOwnProperty(effectiveKey)) {
        effectiveTokenData[effectiveKey] = effectiveTokens[effectiveKey];
      }
    }

    state.baselineSafety = evaluateSaveSafety(effectiveTokenData);

    state.search = '';
    if (searchField) searchField.value = '';
    state.filter = 'all';
    updateSaveButton(0);
    filterButtons.forEach(function(btn) {
      btn.classList.toggle('is-active', btn.getAttribute('data-cte-filter') === 'all');
    });

    if (summarySelection) {
      var selectedLabel = key;
      var selectedEntry = getSelectorEntry(key);
      if (selectedEntry) {
        selectedLabel = selectedEntry.label || key;
      }
      summarySelection.textContent = selectedLabel;
      if (summarySourceFile) {
        summarySourceFile.textContent = selectedEntry && selectedEntry.source_path
          ? String(selectedEntry.source_path)
          : t('summary_source_file_unknown', '/resources/themes/*');
      }
      if (summarySourceLayer) {
        summarySourceLayer.textContent = selectedEntry
          ? getSourceLayerLabel(selectedEntry.source_kind)
          : t('summary_source_layer_unknown', 'Unknown');
      }
    }
    renderTokens();
    applyPreview();
  }

  if (selectorField) {
    selectorField.addEventListener('change', function() {
      var key = this.value;
      if (!key) {
        tokenContainer.innerHTML = '<div class="cte-empty"><strong>' + escapeHtml(t('no_tokens', 'No tokens')) + '</strong><span>' + escapeHtml(t('no_tokens_desc', 'No tokens available for this block.')) + '</span></div>';
        applyPreview();
        if (summarySelection) summarySelection.textContent = t('summary_no_selection', 'No block selected');
        setSummaryCount(0, 0);
        return;
      }
      tokenContainer.innerHTML = '<div class="cte-empty"><strong>' + escapeHtml(t('loading', 'Loading...')) + '</strong></div>';
      fetchSourceSnapshot().then(function() {
        loadTokensForSelector(key);
      });
    });
  }

  if (resetBtn) {
    resetBtn.addEventListener('click', function() {
      var key = selectorField ? selectorField.value : '';
      if (key) {
        tokenContainer.innerHTML = '<div class="cte-empty"><strong>' + escapeHtml(t('loading', 'Loading...')) + '</strong></div>';
        fetchSourceSnapshot().then(function() {
          loadTokensForSelector(key);
        });
      }
    });
  }

  if (tokenContainer) {
    tokenContainer.addEventListener('input', function(event) {
      var target = event.target;
      if (!target) return;

      // Handle color picker change
      if (target.matches('[data-cte-color-picker]')) {
        var row = target.closest('.cte-token-row');
        var tokenName = row ? row.getAttribute('data-cte-token-name') : '';
        if (tokenName) {
          var colorVal = target.value;
          tokenValues[tokenName] = colorVal;
          effectiveTokenData[tokenName] = colorVal;
          touchedTokens[tokenName] = true;
          var textInput = row.querySelector('[data-cte-token-input]');
          if (textInput) {
            textInput.value = colorVal;
            textInput.classList.toggle('is-changed', colorVal !== tokenData[tokenName]);
          }
          var swatch = row.querySelector('.cte-color-swatch');
          if (swatch) {
            swatch.style.background = colorVal;
          }
          var rawDisplay = row.querySelector('[data-cte-color-raw]');
          if (rawDisplay) {
            rawDisplay.textContent = colorVal;
          }
          var colorNameEl = row.querySelector('[data-cte-color-name]');
          if (colorNameEl) {
            var cn = getColorName(colorVal);
            colorNameEl.textContent = t('current_prefix', 'Current') + ': ' + (cn || colorVal);
          }
        }
        updateTokenReadability(row, effectiveTokenData);
        updateTokenStatus();
        return;
      }

      if (!target.matches('[data-cte-token-input]')) return;
      var row = target.closest('.cte-token-row');
      var tokenName = row ? row.getAttribute('data-cte-token-name') : '';
      if (tokenName) {
        tokenValues[tokenName] = target.value;
        touchedTokens[tokenName] = true;
      }
      // Live-update color swatch, raw display, color name, var chain, and color picker
      if (row) {
        var swatch = row.querySelector('.cte-color-swatch');
        var val = target.value.trim();
        effectiveTokenData[tokenName] = target.value;
        var resolved = resolveVarValue(val, effectiveTokenData);
        var swatchVal = resolved || val;

        if (swatch) {
          if (isColorValue(swatchVal)) {
            swatch.style.background = swatchVal;
          }
        }
        var rawDisplay = row.querySelector('[data-cte-color-raw]');
        if (rawDisplay) {
          rawDisplay.textContent = val;
        }
        var colorNameEl = row.querySelector('[data-cte-color-name]');
        if (colorNameEl) {
          var resolvedColor = resolved && isColorValue(resolved) ? resolved : (isColorValue(val) ? val : null);
          var cn = resolvedColor ? getColorName(resolvedColor) : '';
          colorNameEl.textContent = t('current_prefix', 'Current') + ': ' + (cn || (resolvedColor || val));
          colorNameEl.style.display = cn || resolvedColor ? '' : 'none';
        }
        var varChainEl = row.querySelector('.cte-color-var-chain');
        if (varChainEl) {
          var vc = getVarChainLabel(val, effectiveTokenData);
          varChainEl.textContent = vc || '';
          varChainEl.style.display = vc ? '' : 'none';
        }
        var resolvedEl = row.querySelector('[data-cte-color-resolved]');
        if (resolvedEl) {
          resolvedEl.textContent = resolved ? '→ ' + resolved : '';
          resolvedEl.style.display = resolved ? '' : 'none';
        }
        // Sync color picker if the value is a valid hex (3 or 6 digit)
        var picker = row.querySelector('[data-cte-color-picker]');
        if (picker) {
          var syncHex = resolved ? expandHex(resolved) : expandHex(val);
          if (syncHex) {
            picker.value = syncHex;
          }
        }
      }
      updateTokenReadability(row, effectiveTokenData);
      updateTokenStatus();
    });

    tokenContainer.addEventListener('blur', function(event) {
      var target = event.target;
      if (!target || !target.matches('[data-cte-token-input]')) return;
      applyPreview();
    }, true);

    tokenContainer.addEventListener('click', function(event) {
      var toggle = event.target.closest('[data-cte-group-toggle]');
      if (toggle) {
        var groupKey = toggle.getAttribute('data-cte-group-toggle') || '';
        if (!groupKey) return;
        groupState[groupKey] = !groupState[groupKey];
        renderTokens();
        return;
      }
      var detailsBtn = event.target.closest('[data-cte-color-details]');
      if (detailsBtn) {
        var row = detailsBtn.closest('.cte-token-row');
        if (!row) return;
        var advanced = row.querySelector('.cte-color-advanced');
        if (!advanced) return;
        var hidden = advanced.hasAttribute('hidden');
        advanced.toggleAttribute('hidden');
        detailsBtn.textContent = t('color_details', 'Details') + (hidden ? ' ▾' : ' ▸');
      }
    });
  }

  if (searchField) {
    searchField.addEventListener('input', function() {
      state.search = this.value || '';
      renderTokens();
    });
  }

  filterButtons.forEach(function(btn) {
    btn.addEventListener('click', function() {
      var filter = btn.getAttribute('data-cte-filter') || 'all';
      state.filter = filter;
      filterButtons.forEach(function(other) {
        other.classList.toggle('is-active', other === btn);
      });
      renderTokens();
    });
  });

  if (workspace) {
    workspace.addEventListener('input', function(e) {
      var target = e.target;
      if (!target) return;

      if (target.matches('[data-simple-color-picker]')) {
        var colorRow = target.closest('.cte-token-row');
        var colorTokenName = colorRow ? colorRow.getAttribute('data-cte-token-name') : '';
        if (!colorTokenName || !tokenData.hasOwnProperty(colorTokenName)) return;

        var nextColor = target.value;
        var hiddenColorInput = colorRow.querySelector('[data-cte-token-input]');
        if (hiddenColorInput) {
          hiddenColorInput.value = nextColor;
        }

        tokenValues[colorTokenName] = nextColor;
        effectiveTokenData[colorTokenName] = nextColor;
        touchedTokens[colorTokenName] = true;

        var simpleSwatch = colorRow.querySelector('.cte-simple-swatch');
        if (simpleSwatch) {
          simpleSwatch.style.background = nextColor;
        }
        var simpleColorName = colorRow.querySelector('[data-simple-color-name]');
        if (simpleColorName) {
          simpleColorName.textContent = getSimpleColorDescriptor(nextColor, effectiveTokenData, false);
        }

        updateTokenStatus();
        applyPreview();
        return;
      }

      if (target.matches('[data-simple-slider], [data-simple-number]')) {
        var sliderRow = target.closest('[data-simple-slider-row]');
        if (!sliderRow) return;
        var slider = sliderRow.querySelector('[data-simple-slider]');
        var numberInput = sliderRow.querySelector('[data-simple-number]');
        var hiddenInput = sliderRow.querySelector('[data-cte-token-input]');
        var row = sliderRow.closest('.cte-token-row');
        var tokenNameForSlider = row ? row.getAttribute('data-cte-token-name') : '';
        if (!slider || !numberInput || !hiddenInput || !tokenNameForSlider || !tokenData.hasOwnProperty(tokenNameForSlider)) return;

        var min = parseFloat(slider.getAttribute('min') || '0');
        var max = parseFloat(slider.getAttribute('max') || '100');
        var step = parseFloat(slider.getAttribute('step') || '1');
        var rawNumber = target.matches('[data-simple-slider]') ? parseFloat(slider.value) : parseFloat(numberInput.value);
        if (!isFinite(rawNumber)) {
          rawNumber = parseFloat(slider.value);
        }
        if (!isFinite(rawNumber)) return;
        var clamped = clampSimpleNumber(rawNumber, min, max);
        slider.value = String(clamped);
        numberInput.value = String(clamped);

        var unit = sliderRow.getAttribute('data-simple-unit') || '';
        var nextValue = formatSimpleNumericValue(clamped, unit, step);
        hiddenInput.value = nextValue;

        tokenValues[tokenNameForSlider] = nextValue;
        effectiveTokenData[tokenNameForSlider] = nextValue;
        touchedTokens[tokenNameForSlider] = true;

        updateTokenStatus();
        applyPreview();
        return;
      }

      if (!target.matches('[data-cte-token-input]')) return;
      var dashboard = target.closest('[data-cte-simple-dashboard]');
      if (!dashboard) return;
      var row = target.closest('.cte-token-row');
      var tokenName = row ? row.getAttribute('data-cte-token-name') : '';
      if (!tokenName || !tokenData.hasOwnProperty(tokenName)) return;

      tokenValues[tokenName] = target.value;
      effectiveTokenData[tokenName] = target.value;
      touchedTokens[tokenName] = true;

      var swatch = row.querySelector('.cte-simple-swatch');
      if (swatch) {
        var resolved = resolveVarValue(target.value.trim(), effectiveTokenData);
        var swatchVal = resolved || target.value.trim();
        if (isColorValue(swatchVal)) {
          swatch.style.background = swatchVal;
        }
      }

      updateTokenStatus();
    });

    workspace.addEventListener('blur', function(e) {
      var target = e.target;
      if (!target || !target.matches('[data-cte-token-input]')) return;
      if (!target.closest('[data-cte-simple-dashboard]')) return;
      applyPreview();
    }, true);

  }

  document.addEventListener('click', function(e) {
    var btn = e.target.closest('[data-cte-mode]');
    if (!btn) return;
    var mode = btn.getAttribute('data-cte-mode') || 'simple';
    setDisplayMode(mode);
  });

  if (testBtn) {
    testBtn.addEventListener('click', function() {
      openPreviewTab();
    });
  }

  if (verifyBtn) {
    verifyBtn.addEventListener('click', function() {
      if (!verifyResults) return;
      var tokens = getSelectedTokens();
      var results = [];

      for (var key in tokens) {
        if (tokens.hasOwnProperty(key)) {
          var exists = !!(tokenData[key] !== undefined);
          var raw = tokens[key];
          var valid = raw !== '' && !(/[{}<>]/.test(raw)) && raw.length <= 4096;
          results.push({
            token: '--' + key,
            selector_exists: true,
            token_exists: exists,
            value_valid: valid,
            changed: raw !== (tokenData[key] || ''),
            pass: exists && valid
          });
        }
      }

      var html = '';
      for (var i = 0; i < results.length; i++) {
        var r = results[i];
        var statusClass = r.pass ? 'cte-verify-pass' : 'cte-verify-fail';
        var statusText = r.pass ? 'OK' : 'FAIL';
        var details = [];
        if (r.token_exists) details.push('exists'); else details.push('missing');
        if (r.value_valid) details.push('valid'); else details.push('invalid');
        html += '<div class="cte-verify-row"><span><code>' + escapeHtml(r.token) + '</code> &middot; ' + details.join(', ') + '</span><span class="' + statusClass + '">' + statusText + '</span></div>';
      }

      verifyResults.innerHTML = html;
      verifyResults.style.display = 'block';
    });
  }

  if (saveBtn) {
    saveBtn.addEventListener('click', function() {
      var safety = evaluateSaveSafety();
      var safetyDiff = summarizeSafetyAgainstBaseline(safety);
      renderSafetyPanel(safety, safetyDiff);

      if (safetyDiff.newSevereCount > 0 && state.mode !== 'advanced') {
        window.alert(t('save_blocked_severe', 'Severe visibility issues block save in Simple mode.'));
        return;
      }

      if (safety.overall === 'warning') {
        if (!window.confirm(t('save_warning_confirm', 'Warning-level visibility issues were found. Save anyway?'))) return;
      } else if (safetyDiff.newSevereCount > 0) {
        if (!window.confirm(t('save_override_confirm', 'Severe visibility issues were found. Developer mode only: save anyway?'))) return;
      } else {
        var confirmText = saveBtn.getAttribute('data-confirm-text') || 'Are you sure you want to save these changes?';
        if (!window.confirm(confirmText)) return;
      }

      var selectorKey = selectorField ? selectorField.value : '';
      var selectedEntry = getSelectorEntry(selectorKey);
      var tokens = getSelectedTokens();
      var form = document.createElement('form');
      form.method = 'POST';
      form.action = '/apps/studio/tools/customization-studio/design-system/tokens/save';

      var modeInput = document.createElement('input');
      modeInput.type = 'hidden';
      modeInput.name = 'mode';
      modeInput.value = state.mode;
      form.appendChild(modeInput);

      var overrideInput = document.createElement('input');
      overrideInput.type = 'hidden';
      overrideInput.name = 'safety_override';
      overrideInput.value = safetyDiff.newSevereCount > 0 && state.mode === 'advanced' ? '1' : '0';
      form.appendChild(overrideInput);

      var csrf = document.querySelector('[name="csrf"]');
      if (csrf) {
        var csrfInput = document.createElement('input');
        csrfInput.type = 'hidden';
        csrfInput.name = 'csrf';
        csrfInput.value = csrf.value;
        form.appendChild(csrfInput);
      }

      var selInput = document.createElement('input');
      selInput.type = 'hidden';
      selInput.name = 'selector_key';
      selInput.value = selectorKey;
      form.appendChild(selInput);

      var sourceIdInput = document.createElement('input');
      sourceIdInput.type = 'hidden';
      sourceIdInput.name = 'source_id';
      sourceIdInput.value = selectedEntry && selectedEntry.source_id ? String(selectedEntry.source_id) : '';
      form.appendChild(sourceIdInput);

      var sourcePathInput = document.createElement('input');
      sourcePathInput.type = 'hidden';
      sourcePathInput.name = 'source_path';
      sourcePathInput.value = selectedEntry && selectedEntry.source_path ? String(selectedEntry.source_path) : '';
      form.appendChild(sourcePathInput);

      for (var key in tokens) {
        if (tokens.hasOwnProperty(key)) {
          var tokenInput = document.createElement('input');
          tokenInput.type = 'hidden';
          tokenInput.name = 'tokens[' + key + ']';
          tokenInput.value = tokens[key];
          form.appendChild(tokenInput);
        }
      }

      document.body.appendChild(form);
      form.submit();
    });
  }

  if (typeof selectorsData !== 'undefined' && selectorsData.length > 0 && selectorField) {
    var firstKey = selectorField.value || selectorsData[0].key;
    if (firstKey) {
      selectorField.value = firstKey;
      tokenContainer.innerHTML = '<div class="cte-empty"><strong>' + escapeHtml(t('loading', 'Loading...')) + '</strong></div>';
      fetchSourceSnapshot().then(function() {
        loadTokensForSelector(firstKey);
        // Sync mode pill active state after initial render
        var modePills = document.querySelectorAll('[data-cte-mode]');
        modePills.forEach(function(p) {
          p.classList.toggle('is-active', p.getAttribute('data-cte-mode') === state.mode);
        });
        updateModeGuide();
      });
    }
  }
})();
